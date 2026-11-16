<?php
/**
 * Exporter: writes rounds and NPCC quarters back out as CSV using the exact template headers,
 * so an export can be re-imported unchanged (and doubles as a backup).
 *
 * Cells that start with = + - @ (or a tab / carriage return) are prefixed with an apostrophe so a
 * spreadsheet never treats text as a formula (CSV injection).
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class GCO_PD_Exporter
 */
final class GCO_PD_Exporter {

	/**
	 * Neutralises spreadsheet formulas in a text cell. Numbers and null are returned untouched.
	 *
	 * @param mixed $value Cell value.
	 * @return mixed
	 */
	public static function safe_cell( $value ) {
		if ( ! is_string( $value ) || '' === $value ) {
			return $value;
		}
		return in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ? "'" . $value : $value;
	}

	/**
	 * Status code to template wording.
	 *
	 * @param string|null $status Stored status.
	 * @return string
	 */
	public static function status_label( $status ) {
		switch ( $status ) {
			case GCO_PD_Validator::STATUS_ACTUAL:
				return 'Actual';
			case GCO_PD_Validator::STATUS_ESTIMATE:
				return 'Estimate';
			default:
				return 'Not provided';
		}
	}

	/**
	 * FOI Data CSV for the given rounds (all rounds when empty).
	 *
	 * @param int[] $round_ids Round ids.
	 * @return string CSV with a UTF-8 BOM.
	 */
	public static function foi_csv( array $round_ids = array() ) {
		$forces = array();
		foreach ( GCO_PD_Repository::get_forces() as $force ) {
			$forces[ (int) $force->id ] = $force->name;
		}

		$rounds = array_reverse( GCO_PD_Repository::get_rounds() ); // Oldest first.
		$lines  = array( array_values( GCO_PD_Import_Parser::FOI_HEADERS ) );

		foreach ( $rounds as $round ) {
			if ( $round_ids && ! in_array( (int) $round->id, $round_ids, true ) ) {
				continue;
			}
			$responses = GCO_PD_Repository::get_responses( (int) $round->id );
			uasort(
				$responses,
				static function ( $a, $b ) use ( $forces ) {
					return strcmp( $forces[ (int) $a->force_id ] ?? '', $forces[ (int) $b->force_id ] ?? '' );
				}
			);
			foreach ( $responses as $row ) {
				$line = array(
					$round->label,
					$forces[ (int) $row->force_id ] ?? '',
					$row->foi_ref,
					$row->response_date,
				);
				foreach ( array( 'sg_grant_days', 'fac_grant_days', 'sg_renew_days', 'fac_renew_days', 'variation_days', 'sg_holders', 'fac_holders' ) as $column ) {
					$status_column = ( 'sg_holders' === $column || 'fac_holders' === $column ) ? $column . '_status' : str_replace( '_days', '_status', $column );
					$line[]        = $row->$column;
					$line[]        = self::status_label( $row->$status_column );
				}
				$line[] = $row->notes;
				$line[] = $row->pdf_id ? wp_basename( (string) get_attached_file( (int) $row->pdf_id ) ) : '';
				$lines[] = $line;
			}
		}
		return self::to_csv( $lines );
	}

	/**
	 * NPCC Data CSV for the given quarters (all when empty). % within SLA is calculated here, never stored.
	 *
	 * @param int[] $quarter_ids Quarter ids.
	 * @return string CSV with a UTF-8 BOM.
	 */
	public static function npcc_csv( array $quarter_ids = array() ) {
		$forces = array();
		foreach ( GCO_PD_Repository::get_forces() as $force ) {
			$forces[ (int) $force->id ] = $force->name;
		}

		$lines = array( array_values( GCO_PD_Import_Parser::NPCC_HEADERS ) );
		foreach ( array_reverse( GCO_PD_Repository::get_quarters() ) as $quarter ) {
			if ( $quarter_ids && ! in_array( (int) $quarter->id, $quarter_ids, true ) ) {
				continue;
			}
			$rows = GCO_PD_Repository::get_npcc_rows( (int) $quarter->id );
			uasort(
				$rows,
				static function ( $a, $b ) use ( $forces ) {
					return strcmp( $forces[ (int) $a->force_id ] ?? '', $forces[ (int) $b->force_id ] ?? '' );
				}
			);
			foreach ( $rows as $row ) {
				$pct = ( null !== $row->completed_in_sla && null !== $row->apps_completed_12m && (int) $row->apps_completed_12m > 0 )
					? round( (int) $row->completed_in_sla / (int) $row->apps_completed_12m, 6 )
					: '';
				$lines[] = array(
					$quarter->label,
					$quarter->data_date,
					$forces[ (int) $row->force_id ] ?? '',
					$row->total_holders,
					$row->total_certificates,
					$row->apps_received_12m,
					$row->apps_completed_12m,
					$row->completed_in_sla,
					$pct,
					$row->temporary_permits,
					$row->notes,
				);
			}
		}
		return self::to_csv( $lines );
	}

	/**
	 * Encodes rows as CSV. Null becomes an empty cell (never 0).
	 *
	 * @param array<int,array> $lines Rows; the first is the header.
	 * @return string
	 */
	public static function to_csv( array $lines ) {
		$stream = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		foreach ( $lines as $line ) {
			$cells = array();
			foreach ( $line as $cell ) {
				$cells[] = null === $cell ? '' : self::safe_cell( $cell );
			}
			fputcsv( $stream, $cells, ',', '"', '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv
		}
		rewind( $stream );
		$csv = stream_get_contents( $stream );
		fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return "\xEF\xBB\xBF" . $csv;
	}
}
