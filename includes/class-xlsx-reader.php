<?php
/**
 * Minimal read-only XLSX reader (ZipArchive + SimpleXML; no other dependencies).
 *
 * Security: formulas are never evaluated (only the cached value is read); XML with a DOCTYPE or
 * ENTITY declaration is refused (XXE / entity expansion); each XML part is size-capped; the file is
 * read from the upload's temp path and nothing is written to disk.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class GCO_PD_Xlsx_Reader
 */
final class GCO_PD_Xlsx_Reader {

	/**
	 * Largest uncompressed XML part we will read (bytes).
	 */
	const MAX_PART_BYTES = 20971520;

	/**
	 * Hard stop on non-empty rows per sheet (the importer's own cap is lower).
	 */
	const MAX_ROWS = 5000;

	const NS_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

	/**
	 * Reads every worksheet.
	 *
	 * @param string $path Path to the .xlsx file.
	 * @return array<string,array<int,array{row:int,cells:array<int,mixed>}>>|WP_Error Sheet name => non-empty rows.
	 */
	public static function read( $path ) {
		if ( ! class_exists( 'ZipArchive' ) || ! function_exists( 'simplexml_load_string' ) ) {
			return new WP_Error( 'gco_pd_no_zip', 'This server needs the PHP zip and simplexml extensions to read .xlsx files. Upload a .csv instead.' );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $path, ZipArchive::RDONLY ) ) {
			return new WP_Error( 'gco_pd_bad_xlsx', 'That file is not a valid .xlsx workbook.' );
		}

		$result = self::read_zip( $zip );
		$zip->close();
		return $result;
	}

	/**
	 * Reads the workbook from an open archive.
	 *
	 * @param ZipArchive $zip Open archive.
	 * @return array|WP_Error
	 */
	private static function read_zip( ZipArchive $zip ) {
		$workbook = self::load_xml( $zip, 'xl/workbook.xml' );
		$rels     = self::load_xml( $zip, 'xl/_rels/workbook.xml.rels' );
		if ( $workbook instanceof WP_Error ) {
			return $workbook;
		}
		if ( $rels instanceof WP_Error ) {
			return $rels;
		}
		if ( ! $workbook || ! $rels || ! isset( $workbook->sheets ) ) {
			return new WP_Error( 'gco_pd_bad_xlsx', 'That file does not look like an Excel workbook.' );
		}

		$targets = array();
		foreach ( $rels->Relationship as $rel ) {
			$targets[ (string) $rel['Id'] ] = (string) $rel['Target'];
		}

		$shared = self::read_shared_strings( $zip );
		if ( $shared instanceof WP_Error ) {
			return $shared;
		}

		$sheets = array();
		foreach ( $workbook->sheets->sheet as $sheet ) {
			$name = (string) $sheet['name'];
			$rid  = (string) $sheet->attributes( self::NS_REL )['id'];
			if ( '' === $rid || ! isset( $targets[ $rid ] ) ) {
				continue;
			}
			$target = ltrim( $targets[ $rid ], '/' );
			$part   = 0 === strpos( $target, 'xl/' ) ? $target : 'xl/' . $target;

			$rows = self::read_sheet( $zip, $part, $shared );
			if ( $rows instanceof WP_Error ) {
				return $rows;
			}
			$sheets[ $name ] = $rows;
		}

		return $sheets;
	}

	/**
	 * Loads one XML part safely.
	 *
	 * @param ZipArchive $zip  Archive.
	 * @param string     $part Part name.
	 * @return SimpleXMLElement|null|WP_Error Null when the part is absent.
	 */
	private static function load_xml( ZipArchive $zip, $part ) {
		$stat = $zip->statName( $part );
		if ( false === $stat ) {
			return null;
		}
		if ( $stat['size'] > self::MAX_PART_BYTES ) {
			return new WP_Error( 'gco_pd_xlsx_too_big', 'That workbook is too large to import.' );
		}
		$xml = $zip->getFromName( $part );
		if ( false === $xml || '' === $xml ) {
			return null;
		}
		// Refuse DTDs and entities outright: nothing in a normal workbook needs them.
		if ( false !== stripos( $xml, '<!DOCTYPE' ) || false !== stripos( $xml, '<!ENTITY' ) ) {
			return new WP_Error( 'gco_pd_bad_xlsx', 'That workbook contains unsupported content and was rejected.' );
		}
		$previous = libxml_use_internal_errors( true );
		$parsed   = simplexml_load_string( $xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		return false === $parsed ? new WP_Error( 'gco_pd_bad_xlsx', 'That workbook could not be read.' ) : $parsed;
	}

	/**
	 * Reads the shared-strings table (absent when every string is inline, as in our template).
	 *
	 * @param ZipArchive $zip Archive.
	 * @return string[]|WP_Error
	 */
	private static function read_shared_strings( ZipArchive $zip ) {
		$xml = self::load_xml( $zip, 'xl/sharedStrings.xml' );
		if ( $xml instanceof WP_Error ) {
			return $xml;
		}
		$strings = array();
		if ( $xml ) {
			foreach ( $xml->si as $si ) {
				$strings[] = self::rich_text( $si );
			}
		}
		return $strings;
	}

	/**
	 * Concatenates the text of a <si> / <is> element, including rich-text runs.
	 *
	 * @param SimpleXMLElement $node Element.
	 * @return string
	 */
	private static function rich_text( SimpleXMLElement $node ) {
		if ( isset( $node->t ) ) {
			return (string) $node->t;
		}
		$text = '';
		foreach ( $node->r as $run ) {
			$text .= (string) $run->t;
		}
		return $text;
	}

	/**
	 * Reads one worksheet into non-empty rows.
	 *
	 * @param ZipArchive $zip    Archive.
	 * @param string     $part   Worksheet part name.
	 * @param string[]   $shared Shared strings.
	 * @return array|WP_Error
	 */
	private static function read_sheet( ZipArchive $zip, $part, array $shared ) {
		$xml = self::load_xml( $zip, $part );
		if ( $xml instanceof WP_Error ) {
			return $xml;
		}
		$rows = array();
		if ( ! $xml || ! isset( $xml->sheetData ) ) {
			return $rows;
		}

		foreach ( $xml->sheetData->row as $row ) {
			$cells    = array();
			$has_data = false;
			$position = 0;

			foreach ( $row->c as $cell ) {
				$ref   = (string) $cell['r'];
				$index = '' !== $ref ? self::column_index( $ref ) : $position;
				$position = $index + 1;

				$value = self::cell_value( $cell, $shared );
				if ( null !== $value && '' !== trim( (string) $value ) ) {
					$has_data = true;
				}
				$cells[ $index ] = $value;
			}

			if ( ! $has_data ) {
				continue;
			}
			if ( count( $rows ) >= self::MAX_ROWS ) {
				return new WP_Error( 'gco_pd_too_many_rows', 'That sheet has too many rows to import.' );
			}
			$rows[] = array(
				'row'   => (int) $row['r'],
				'cells' => $cells,
			);
		}

		return $rows;
	}

	/**
	 * Reads a cell's value. Formulas are not evaluated: the cached <v> is used.
	 *
	 * @param SimpleXMLElement $cell   Cell.
	 * @param string[]         $shared Shared strings.
	 * @return string|int|float|bool|null
	 */
	private static function cell_value( SimpleXMLElement $cell, array $shared ) {
		$type = (string) $cell['t'];

		if ( 'inlineStr' === $type ) {
			return isset( $cell->is ) ? self::rich_text( $cell->is ) : null;
		}
		if ( ! isset( $cell->v ) ) {
			return null;
		}
		$raw = (string) $cell->v;

		switch ( $type ) {
			case 's':
				return $shared[ (int) $raw ] ?? null;
			case 'str':
				return $raw;
			case 'b':
				return '1' === $raw;
			case 'e':
				return null; // Excel error value such as #DIV/0!.
			default:
				if ( '' === $raw || ! is_numeric( $raw ) ) {
					return '' === $raw ? null : $raw;
				}
				return ( (string) (int) $raw === $raw ) ? (int) $raw : (float) $raw;
		}
	}

	/**
	 * Zero-based column index from a cell reference such as "AB12".
	 *
	 * @param string $ref Cell reference.
	 * @return int
	 */
	public static function column_index( $ref ) {
		$letters = preg_replace( '/[^A-Za-z]/', '', $ref );
		$index   = 0;
		$length  = strlen( $letters );
		for ( $i = 0; $i < $length; $i++ ) {
			$index = $index * 26 + ( ord( strtoupper( $letters[ $i ] ) ) - 64 );
		}
		return max( 0, $index - 1 );
	}
}
