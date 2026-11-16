<?php
/**
 * Turns spreadsheet / CSV rows into raw, header-mapped rows. Pure PHP (no WordPress, no database)
 * so it can be unit-tested against the real template.
 *
 * Columns are matched by header NAME, never position, so extra or re-ordered columns are harmless and
 * a changed NPCC layout is reported instead of silently misread.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class GCO_PD_Import_Parser
 */
final class GCO_PD_Import_Parser {

	/**
	 * Largest number of data rows accepted in one file.
	 */
	const MAX_DATA_ROWS = 1000;

	/**
	 * FOI Data sheet: field key => exact header in the agreed template.
	 *
	 * @var array<string,string>
	 */
	const FOI_HEADERS = array(
		'round'              => 'Round',
		'force'              => 'Police Force',
		'foi_ref'            => 'FOI Reference',
		'response_date'      => 'Response Date',
		'sg_grant_days'      => 'Shotgun Grant (days)',
		'sg_grant_status'    => 'Shotgun Grant Status',
		'fac_grant_days'     => 'Firearm Grant (days)',
		'fac_grant_status'   => 'Firearm Grant Status',
		'sg_renew_days'      => 'Shotgun Renewal (days)',
		'sg_renew_status'    => 'Shotgun Renewal Status',
		'fac_renew_days'     => 'Firearm Renewal (days)',
		'fac_renew_status'   => 'Firearm Renewal Status',
		'variation_days'     => 'Variation (days)',
		'variation_status'   => 'Variation Status',
		'sg_holders'         => 'Shotgun Certificate Holders',
		'sg_holders_status'  => 'Shotgun Holders Status',
		'fac_holders'        => 'Firearm Certificate Holders',
		'fac_holders_status' => 'Firearm Holders Status',
		'notes'              => 'Original Response / Notes',
		'pdf_file'           => 'PDF File Name',
	);

	/**
	 * FOI columns that may be absent from a file.
	 *
	 * @var string[]
	 */
	const FOI_OPTIONAL = array( 'foi_ref', 'response_date', 'notes', 'pdf_file' );

	/**
	 * NPCC Data sheet: field key => exact header in the agreed template.
	 *
	 * @var array<string,string>
	 */
	const NPCC_HEADERS = array(
		'quarter'            => 'Quarter',
		'data_date'          => 'Data Date',
		'force'              => 'Police Force',
		'total_holders'      => 'Total Certificate Holders',
		'total_certificates' => 'Total Shotgun, Firearms and RFD Certificates',
		'apps_received_12m'  => 'Applications Received (12 months)',
		'apps_completed_12m' => 'Applications Completed (12 months)',
		'completed_in_sla'   => 'Completed Within SLA',
		'pct_within_sla'     => '% Completed Within SLA',
		'temporary_permits'  => 'Temporary Permits',
		'notes'              => 'Notes',
	);

	/**
	 * NPCC columns that may be absent from a file (their values are then left blank).
	 *
	 * @var string[]
	 */
	const NPCC_OPTIONAL = array( 'data_date', 'total_certificates', 'pct_within_sla', 'temporary_permits', 'notes' );

	/**
	 * Header text => comparable key.
	 *
	 * @param mixed $header Header cell.
	 * @return string
	 */
	public static function normalize_header( $header ) {
		$header = strtolower( trim( (string) $header ) );
		$header = str_replace( array( '%', '&' ), array( ' pct ', ' and ' ), $header );
		return preg_replace( '/[^a-z0-9]+/', '', $header );
	}

	/**
	 * Parses one sheet (or one CSV) into raw rows.
	 *
	 * @param array<int,array{row:int,cells:array<int,mixed>}> $rows Non-empty rows; the first is the header.
	 * @return array{type:?string,rows:array,errors:string[],notices:string[]}
	 */
	public static function parse_sheet( array $rows ) {
		$out = array(
			'type'    => null,
			'rows'    => array(),
			'errors'  => array(),
			'notices' => array(),
		);
		if ( empty( $rows ) ) {
			return $out;
		}

		$header_row = array_shift( $rows );
		$normalized = array();
		foreach ( $header_row['cells'] as $index => $text ) {
			$key = self::normalize_header( $text );
			if ( '' !== $key ) {
				$normalized[ $index ] = $key;
			}
		}

		$type = self::detect_type( $normalized );
		if ( null === $type ) {
			return $out; // Not a data sheet (instructions, forces list, coverage...).
		}
		$out['type'] = $type;

		$definitions = 'foi' === $type ? self::FOI_HEADERS : self::NPCC_HEADERS;
		$optional    = 'foi' === $type ? self::FOI_OPTIONAL : self::NPCC_OPTIONAL;

		$lookup = array();
		foreach ( $definitions as $field => $label ) {
			$lookup[ self::normalize_header( $label ) ] = $field;
		}

		$columns = array(); // Cell index => field key.
		$ignored = array();
		foreach ( $normalized as $index => $key ) {
			if ( isset( $lookup[ $key ] ) && ! in_array( $lookup[ $key ], $columns, true ) ) {
				$columns[ $index ] = $lookup[ $key ];
			} else {
				$ignored[] = trim( (string) $header_row['cells'][ $index ] );
			}
		}

		foreach ( $definitions as $field => $label ) {
			if ( in_array( $field, $columns, true ) ) {
				continue;
			}
			if ( in_array( $field, $optional, true ) ) {
				$out['notices'][] = sprintf( 'Column "%s" was not found; that value will be left blank.', $label );
			} else {
				$out['errors'][] = sprintf( 'Required column "%s" was not found. Keep the template headers exactly as they are.', $label );
			}
		}
		if ( $ignored ) {
			$out['notices'][] = 'Ignored columns: ' . implode( ', ', array_unique( $ignored ) ) . '.';
		}
		if ( $out['errors'] ) {
			return $out;
		}

		if ( count( $rows ) > self::MAX_DATA_ROWS ) {
			$out['errors'][] = sprintf( 'This sheet has %d data rows; the limit is %d per upload.', count( $rows ), self::MAX_DATA_ROWS );
			return $out;
		}

		foreach ( $rows as $row ) {
			$raw = array_fill_keys( array_keys( $definitions ), null );
			foreach ( $columns as $index => $field ) {
				$raw[ $field ] = $row['cells'][ $index ] ?? null;
			}
			$out['rows'][] = self::build_row( $type, (int) $row['row'], $raw );
		}

		return $out;
	}

	/**
	 * Decides whether a header row belongs to an FOI or an NPCC sheet.
	 *
	 * @param array<int,string> $normalized Normalised header cells.
	 * @return string|null
	 */
	private static function detect_type( array $normalized ) {
		$force = self::normalize_header( self::FOI_HEADERS['force'] );
		if ( ! in_array( $force, $normalized, true ) ) {
			return null;
		}
		if ( in_array( self::normalize_header( self::FOI_HEADERS['round'] ), $normalized, true ) ) {
			return 'foi';
		}
		if ( in_array( self::normalize_header( self::NPCC_HEADERS['quarter'] ), $normalized, true ) ) {
			return 'npcc';
		}
		return null;
	}

	/**
	 * Cleans one raw row: text trimmed, dates converted, ready for the validator.
	 *
	 * @param string $type    'foi' or 'npcc'.
	 * @param int    $row_num Spreadsheet row number.
	 * @param array  $raw     Raw values by field key.
	 * @return array{row_num:int,force:string,period:string,data:array,pdf_file:?string,pct:?float,errors:array<string,string>}
	 */
	private static function build_row( $type, $row_num, array $raw ) {
		$errors = array();
		$data   = $raw;
		$period = '';

		if ( 'foi' === $type ) {
			$period = self::to_round_label( $raw['round'] );
			$date   = self::to_date( $raw['response_date'] );
			if ( false === $date ) {
				$errors['response_date'] = 'Response date is not a recognisable date. Use DD/MM/YYYY.';
				$date                    = null;
			}
			$data['response_date'] = $date;
			$data['foi_ref']       = self::to_text( $raw['foi_ref'] );
			$data['notes']         = self::to_text( $raw['notes'] );
			unset( $data['round'], $data['force'], $data['pdf_file'] );
			$pdf = self::to_text( $raw['pdf_file'] );
			$pct = null;
		} else {
			$period = self::to_text( $raw['quarter'] );
			$date   = self::to_date( $raw['data_date'] );
			if ( false === $date ) {
				$errors['data_date'] = 'Data date is not a recognisable date. Use DD/MM/YYYY.';
				$date                = null;
			}
			$data['data_date'] = $date;
			$data['notes']     = self::to_text( $raw['notes'] );
			$pct               = self::to_percent( $raw['pct_within_sla'] );
			unset( $data['quarter'], $data['force'], $data['pct_within_sla'] );
			$pdf = null;
		}

		return array(
			'row_num'  => $row_num,
			'force'    => self::to_text( $raw['force'] ) ?? '',
			'period'   => $period,
			'data'     => $data,
			'pdf_file' => $pdf,
			'pct'      => $pct,
			'errors'   => $errors,
		);
	}

	/**
	 * Trimmed text, or null when blank. Whole-number floats lose their ".0".
	 *
	 * @param mixed $value Cell value.
	 * @return string|null
	 */
	public static function to_text( $value ) {
		if ( null === $value || is_bool( $value ) ) {
			return null;
		}
		if ( is_float( $value ) && floor( $value ) === $value ) {
			$value = (int) $value;
		}
		$text = trim( (string) $value );
		return '' === $text ? null : $text;
	}

	/**
	 * Converts a cell to Y-m-d.
	 *
	 * Accepts an Excel serial number, YYYY-MM-DD (optionally with a time), and DD/MM/YYYY (also - or .).
	 *
	 * @param mixed $value Cell value.
	 * @return string|null|false Date, null when blank, false when unrecognisable.
	 */
	public static function to_date( $value ) {
		if ( null === $value ) {
			return null;
		}
		if ( is_int( $value ) || is_float( $value ) || ( is_string( $value ) && 1 === preg_match( '/^\d+(\.\d+)?$/D', trim( $value ) ) ) ) {
			$serial = (float) $value;
			if ( $serial >= 20000 && $serial <= 100000 ) {
				return gmdate( 'Y-m-d', (int) floor( ( $serial - 25569 ) * 86400 ) );
			}
			return false;
		}
		$text = trim( (string) $value );
		if ( '' === $text ) {
			return null;
		}
		if ( 1 === preg_match( '/^(\d{4})-(\d{2})-(\d{2})(?:[ T].*)?$/D', $text, $m ) ) {
			return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ? sprintf( '%04d-%02d-%02d', $m[1], $m[2], $m[3] ) : false;
		}
		if ( 1 === preg_match( '#^(\d{1,2})[/.\-](\d{1,2})[/.\-](\d{4})$#D', $text, $m ) ) {
			return checkdate( (int) $m[2], (int) $m[1], (int) $m[3] ) ? sprintf( '%04d-%02d-%02d', $m[3], $m[2], $m[1] ) : false;
		}
		return false;
	}

	/**
	 * FOI round label. Excel may turn "2026-06" into a date; that is converted back.
	 *
	 * @param mixed $value Cell value.
	 * @return string Label (validated later by GCO_PD_Validator).
	 */
	public static function to_round_label( $value ) {
		if ( is_int( $value ) || is_float( $value ) ) {
			$serial = (float) $value;
			if ( $serial >= 20000 && $serial <= 100000 ) {
				return gmdate( 'Y-m', (int) floor( ( $serial - 25569 ) * 86400 ) );
			}
		}
		$text = self::to_text( $value ) ?? '';
		if ( 1 === preg_match( '/^(\d{4})-(\d{1,2})$/D', $text, $m ) ) {
			return sprintf( '%04d-%02d', $m[1], $m[2] );
		}
		return $text;
	}

	/**
	 * Reads a % cell as a fraction 0..1 (the template stores 0.8962; a typed "89.62" is also understood).
	 *
	 * @param mixed $value Cell value.
	 * @return float|null
	 */
	public static function to_percent( $value ) {
		if ( null === $value || '' === trim( (string) $value ) ) {
			return null;
		}
		$text = str_replace( array( '%', ',' ), '', trim( (string) $value ) );
		if ( ! is_numeric( $text ) ) {
			return null;
		}
		$number = (float) $text;
		return ( $number > 1.0 ) ? $number / 100 : $number;
	}

	/**
	 * Reads a CSV file into the same row structure the XLSX reader returns.
	 *
	 * Handles a UTF-8 BOM, Windows-1252 exports from Excel, and comma / semicolon / tab delimiters.
	 *
	 * @param string $path File path.
	 * @return array<int,array{row:int,cells:array<int,mixed>}>
	 */
	public static function read_csv( $path ) {
		$content = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $content || '' === $content ) {
			return array();
		}
		if ( 0 === strpos( $content, "\xEF\xBB\xBF" ) ) {
			$content = substr( $content, 3 );
		}
		if ( function_exists( 'mb_check_encoding' ) && ! mb_check_encoding( $content, 'UTF-8' ) ) {
			$content = mb_convert_encoding( $content, 'UTF-8', 'Windows-1252' );
		}

		$first_line = strtok( $content, "\n" );
		$delimiter  = ',';
		$best       = 0;
		foreach ( array( ',', ';', "\t" ) as $candidate ) {
			$count = substr_count( (string) $first_line, $candidate );
			if ( $count > $best ) {
				$best      = $count;
				$delimiter = $candidate;
			}
		}

		$stream = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fwrite( $stream, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		rewind( $stream );

		$rows   = array();
		$number = 0;
		while ( false !== ( $record = fgetcsv( $stream, 0, $delimiter, '"', '' ) ) ) {
			++$number;
			if ( array( null ) === $record ) {
				continue; // Blank line.
			}
			$has_data = false;
			foreach ( $record as $cell ) {
				if ( null !== $cell && '' !== trim( $cell ) ) {
					$has_data = true;
					break;
				}
			}
			if ( ! $has_data ) {
				continue;
			}
			$rows[] = array(
				'row'   => $number,
				'cells' => $record,
			);
			if ( count( $rows ) > 5000 ) {
				break;
			}
		}
		fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return $rows;
	}
}
