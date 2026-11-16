<?php
/**
 * Importer: upload check, preview (new / changed / unchanged / error per row) and commit to DRAFT data only.
 *
 * Nothing becomes public until a round or quarter is published. The preview is kept in a short-lived,
 * per-user transient so the commit step does not need the file again; the upload itself is read from
 * its temp path and never stored.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class GCO_PD_Importer
 */
final class GCO_PD_Importer {

	const MAX_BYTES = 2097152; // 2 MB.

	/**
	 * Fields compared to decide changed / unchanged for an FOI row.
	 *
	 * @var string[]
	 */
	const FOI_COMPARE = array(
		'foi_ref', 'response_date', 'notes', 'pdf_id',
		'sg_grant_days', 'sg_grant_status', 'fac_grant_days', 'fac_grant_status',
		'sg_renew_days', 'sg_renew_status', 'fac_renew_days', 'fac_renew_status',
		'variation_days', 'variation_status', 'sg_holders', 'sg_holders_status', 'fac_holders', 'fac_holders_status',
		'apps_received', 'apps_received_status', 'apps_completed', 'apps_completed_status', 'volumes_period',
		'outstanding', 'outstanding_status', 'oldest_days', 'oldest_days_status',
		'backlog_unit', 'backlog_m0_3', 'backlog_m3_6', 'backlog_m6_12', 'backlog_m12_plus', 'backlog_status',
		'standard_text', 'standard_days', 'standard_days_status', 'standard_met_pct', 'standard_met_pct_status',
	);

	/* ------------------------------------------------------------------ Upload */

	/**
	 * Validates an uploaded file entry from $_FILES.
	 *
	 * Only .xlsx and .csv are accepted; content is sniffed rather than trusting the name or the
	 * browser-supplied type (WordPress often misreports CSV as text/plain).
	 *
	 * @param array $file One $_FILES entry.
	 * @return string|WP_Error 'xlsx' or 'csv'.
	 */
	public static function check_upload( array $file ) {
		if ( empty( $file['tmp_name'] ) || ! isset( $file['error'] ) || UPLOAD_ERR_OK !== (int) $file['error'] ) {
			return new WP_Error( 'gco_pd_upload', 'The file did not upload. Please try again.' );
		}
		if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'gco_pd_upload', 'That is not a valid upload.' );
		}
		return self::check_file( $file['tmp_name'], (string) ( $file['name'] ?? '' ) );
	}

	/**
	 * Checks a file on disk (separated from check_upload() so it can be tested).
	 *
	 * @param string $path Path.
	 * @param string $name Original file name.
	 * @return string|WP_Error 'xlsx' or 'csv'.
	 */
	public static function check_file( $path, $name ) {
		$size = (int) filesize( $path );
		if ( $size <= 0 ) {
			return new WP_Error( 'gco_pd_upload', 'That file is empty.' );
		}
		if ( $size > self::MAX_BYTES ) {
			return new WP_Error( 'gco_pd_upload', 'That file is larger than 2 MB.' );
		}

		$ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'xlsx', 'csv' ), true ) ) {
			return new WP_Error( 'gco_pd_upload', 'Only .xlsx and .csv files can be imported.' );
		}

		$head = (string) file_get_contents( $path, false, null, 0, 4096 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( 'xlsx' === $ext ) {
			if ( 0 !== strpos( $head, "PK\x03\x04" ) ) {
				return new WP_Error( 'gco_pd_upload', 'That file is not a real .xlsx workbook.' );
			}
		} else {
			if ( false !== strpos( $head, "\0" ) || 0 === strpos( $head, 'PK' ) || 0 === strpos( $head, 'MZ' ) || false !== stripos( $head, '<?php' ) ) {
				return new WP_Error( 'gco_pd_upload', 'That file is not a plain-text .csv.' );
			}
		}
		return $ext;
	}

	/* ----------------------------------------------------------------- Preview */

	/**
	 * Parses a file and classifies every row against the current draft/published data.
	 * Writes nothing.
	 *
	 * @param string $path     File path.
	 * @param string $ext      'xlsx' or 'csv'.
	 * @param string $filename Original file name (shown in the preview).
	 * @return array|WP_Error Preview structure.
	 */
	public static function build_preview( $path, $ext, $filename = '' ) {
		if ( 'xlsx' === $ext ) {
			$sheets = GCO_PD_Xlsx_Reader::read( $path );
			if ( is_wp_error( $sheets ) ) {
				return $sheets;
			}
		} else {
			$sheets = array( 'CSV' => GCO_PD_Import_Parser::read_csv( $path ) );
		}

		$preview = array(
			'file'    => sanitize_text_field( $filename ),
			'notices' => array(),
			'errors'  => array(),
			'foi'     => array(),
			'npcc'    => array(),
		);
		$foi_raw  = array();
		$npcc_raw = array();

		foreach ( $sheets as $sheet_name => $rows ) {
			$parsed = GCO_PD_Import_Parser::parse_sheet( $rows );
			if ( null === $parsed['type'] ) {
				continue;
			}
			foreach ( $parsed['errors'] as $message ) {
				$preview['errors'][] = sprintf( '%s: %s', $sheet_name, $message );
			}
			foreach ( $parsed['notices'] as $message ) {
				$preview['notices'][] = sprintf( '%s: %s', $sheet_name, $message );
			}
			if ( 'foi' === $parsed['type'] ) {
				$foi_raw = array_merge( $foi_raw, $parsed['rows'] );
			} else {
				$npcc_raw = array_merge( $npcc_raw, $parsed['rows'] );
			}
		}

		if ( empty( $preview['errors'] ) && empty( $foi_raw ) && empty( $npcc_raw ) ) {
			return new WP_Error( 'gco_pd_no_data', 'No "FOI Data" or "NPCC Data" rows were found in that file. Check you are using the template and the column headers are unchanged.' );
		}

		if ( empty( $preview['errors'] ) ) {
			$map             = GCO_PD_Repository::get_force_name_map();
			$preview['foi']  = self::prepare_foi( $foi_raw, $map );
			$preview['npcc'] = self::prepare_npcc( $npcc_raw, $map );
		}

		$preview['counts'] = array(
			'foi'  => self::count_states( $preview['foi'] ),
			'npcc' => self::count_states( $preview['npcc'] ),
		);
		return $preview;
	}

	/**
	 * Classifies FOI rows.
	 *
	 * @param array $rows Parser rows.
	 * @param array $map  Force name map.
	 * @return array
	 */
	private static function prepare_foi( array $rows, array $map ) {
		$out      = array();
		$rounds   = array();
		$existing = array();
		$seen     = array();

		foreach ( $rows as $row ) {
			$label    = $row['period'];
			$errors   = $row['errors'];
			$warnings = array();
			$force_id = GCO_PD_Repository::find_force_id( $row['force'], $map );

			if ( ! GCO_PD_Validator::is_valid_round_label( $label ) ) {
				$errors['round'] = 'Round must look like 2026-11 (year, dash, month).';
			}
			if ( null === $force_id ) {
				$errors['force'] = '' === $row['force']
					? 'Police force is blank.'
					: sprintf( '"%s" does not match any force. Use the exact name from the Forces sheet, or add an alias on the Forces screen.', $row['force'] );
			}

			if ( ! isset( $errors['round'] ) ) {
				if ( ! array_key_exists( $label, $rounds ) ) {
					$rounds[ $label ]   = GCO_PD_Repository::get_round_by_label( $label );
					$existing[ $label ] = $rounds[ $label ] ? GCO_PD_Repository::get_responses( (int) $rounds[ $label ]->id ) : array();
				}
				if ( $rounds[ $label ] && 'published' === $rounds[ $label ]->status ) {
					$errors['round'] = sprintf( 'Round %s is published. Unpublish it before importing changes, so nothing live changes by accident.', $label );
				}
			}

			if ( ! isset( $errors['round'] ) && null !== $force_id ) {
				$key = $label . '|' . $force_id;
				if ( isset( $seen[ $key ] ) ) {
					$errors['force'] = sprintf( 'This force already appears in round %s at row %d.', $label, $seen[ $key ] );
				} else {
					$seen[ $key ] = $row['row_num'];
				}
			}

			$result   = GCO_PD_Validator::validate_response( $row['data'] );
			$errors   = array_merge( $errors, $result['errors'] );
			$warnings = $result['warnings'];
			$clean    = $result['clean'];

			$current = ( null !== $force_id && isset( $existing[ $label ][ $force_id ] ) ) ? $existing[ $label ][ $force_id ] : null;

			// PDF: link by file name; a blank cell keeps whatever is already linked.
			$pdf_id = $current ? ( null !== $current->pdf_id ? (int) $current->pdf_id : null ) : null;
			if ( null !== $row['pdf_file'] ) {
				$found = GCO_PD_Repository::find_pdf_attachment( $row['pdf_file'] );
				if ( $found ) {
					$pdf_id = $found;
				} else {
					$warnings['pdf_file'] = sprintf( 'PDF "%s" is not in the media library yet. Upload it there and import again to link it.', $row['pdf_file'] );
				}
			}
			$clean['pdf_id'] = $pdf_id;

			$state   = 'new';
			$changed = array();
			if ( $errors ) {
				$state = 'error';
			} elseif ( $current ) {
				$changed = self::diff( $clean, $current, self::FOI_COMPARE );
				$state   = $changed ? 'changed' : 'unchanged';
			}

			$out[] = array(
				'row_num'    => $row['row_num'],
				'period'     => $label,
				'force_id'   => $force_id,
				'force_name' => null !== $force_id ? self::force_label( $force_id ) : $row['force'],
				'state'      => $state,
				'errors'     => $errors,
				'warnings'   => $warnings,
				'changed'    => $changed,
				'data'       => $clean,
			);
		}
		$out = self::warn_shared_mismatch( $out, array_diff( self::FOI_COMPARE, array( 'foi_ref', 'response_date', 'notes', 'pdf_id' ) ) );
		return self::warn_unmarked_twins( $out, array( 'apps_received', 'apps_completed', 'outstanding', 'sg_holders', 'fac_holders' ) );
	}

	/**
	 * Safety net for a forgotten tick: forces that carry IDENTICAL large figures in the same round or quarter
	 * are very probably one shared department entered twice. If they are not marked as sharing, say so on each
	 * row, because the national totals would otherwise count that department twice. A warning only: it never
	 * merges anything by itself (identical small numbers do happen between unrelated forces).
	 *
	 * @param array    $rows   Preview rows.
	 * @param string[] $fields Count columns that identify a department's figures (not waiting times).
	 * @return array Rows with warnings added.
	 */
	private static function warn_unmarked_twins( array $rows, array $fields ) {
		$seen = array();
		foreach ( $rows as $i => $row ) {
			if ( empty( $row['force_id'] ) || 'error' === $row['state'] ) {
				continue;
			}
			$values = array();
			$big    = false;
			foreach ( $fields as $field ) {
				$value = $row['data'][ $field ] ?? null;
				if ( null !== $value && '' !== $value ) {
					$values[ $field ] = (string) (int) $value;
					$big              = $big || (int) $value >= 100;
				}
			}
			// Need at least two matching numbers, one of them sizeable, before two forces look like twins.
			if ( count( $values ) < 2 || ! $big ) {
				continue;
			}
			ksort( $values );
			$force = GCO_PD_Repository::get_force( (int) $row['force_id'] );
			$seen[ $row['period'] . '|' . md5( json_encode( $values ) ) ][] = array(
				'index' => $i,
				'group' => $force && ! empty( $force->shared_group ) ? (string) $force->shared_group : '',
			);
		}
		foreach ( $seen as $twins ) {
			if ( count( $twins ) < 2 ) {
				continue;
			}
			// Count departments, not forces: forces marked as one group are one department.
			$units   = array();
			$in_group = array();
			foreach ( $twins as $twin ) {
				$units[] = '' !== $twin['group'] ? 'g:' . $twin['group'] : 'r:' . $twin['index'];
				if ( '' !== $twin['group'] ) {
					$in_group[ $twin['group'] ] = ( $in_group[ $twin['group'] ] ?? 0 ) + 1;
				}
			}
			if ( count( array_unique( $units ) ) < 2 ) {
				continue; // Everything here is already one marked department.
			}
			$names = array();
			foreach ( $twins as $twin ) {
				$names[] = $rows[ $twin['index'] ]['force_name'];
			}
			foreach ( $twins as $twin ) {
				if ( '' !== $twin['group'] && $in_group[ $twin['group'] ] > 1 ) {
					continue; // Properly marked with its partner: nothing to flag.
				}
				$rows[ $twin['index'] ]['warnings']['twins'] = sprintf(
					'These forces have identical figures: %s. If they share a licensing department, mark them under Police Data > Forces > Shared department so the national totals count them once. If they are separate, ignore this.',
					implode( ', ', $names )
				);
			}
		}
		return $rows;
	}

	/**
	 * Forces that run one licensing department together normally carry the same combined figures. When the
	 * figures typed for them in the same round or quarter differ, say so on each row (a warning, not an error:
	 * the forces may have given separate figures).
	 *
	 * @param array    $rows   Preview rows (force_id, period, state, data, warnings, force_name).
	 * @param string[] $fields Figure columns that must match across the group.
	 * @return array Rows with warnings added.
	 */
	private static function warn_shared_mismatch( array $rows, array $fields ) {
		$groups = array();
		foreach ( $rows as $i => $row ) {
			if ( empty( $row['force_id'] ) || 'error' === $row['state'] ) {
				continue;
			}
			$force = GCO_PD_Repository::get_force( (int) $row['force_id'] );
			if ( $force && ! empty( $force->shared_group ) ) {
				$groups[ $row['period'] . '|' . $force->shared_group ][] = $i;
			}
		}
		foreach ( $groups as $indexes ) {
			if ( count( $indexes ) < 2 ) {
				continue;
			}
			$signatures = array();
			foreach ( $indexes as $i ) {
				$parts = array();
				foreach ( $fields as $field ) {
					$value   = $rows[ $i ]['data'][ $field ] ?? null;
					$parts[] = is_numeric( $value ) ? (string) (float) $value : (string) $value;
				}
				$signatures[ $i ] = md5( implode( '|', $parts ) );
			}
			if ( count( array_unique( $signatures ) ) < 2 ) {
				continue;
			}
			foreach ( $indexes as $i ) {
				$others = array();
				foreach ( $indexes as $j ) {
					if ( $j !== $i ) {
						$others[] = $rows[ $j ]['force_name'];
					}
				}
				$rows[ $i ]['warnings']['shared'] = sprintf( 'Shares a licensing department with %s, but the figures differ. Forces in a shared department normally carry the same combined figures; if these are separate figures, ignore this. National totals count identical shared figures once and different figures separately.', implode( ', ', $others ) );
			}
		}
		return $rows;
	}

	/**
	 * Classifies NPCC rows.
	 *
	 * @param array $rows Parser rows.
	 * @param array $map  Force name map.
	 * @return array
	 */
	private static function prepare_npcc( array $rows, array $map ) {
		$out      = array();
		$quarters = array();
		$existing = array();
		$seen     = array();
		$dates    = array();

		foreach ( $rows as $row ) {
			$label    = $row['period'];
			$errors   = $row['errors'];
			$force_id = GCO_PD_Repository::find_force_id( $row['force'], $map );

			if ( ! GCO_PD_Validator::is_valid_quarter_label( $label ) ) {
				$errors['quarter'] = 'Quarter must look like "2025/26 Q4".';
			}
			if ( null === $force_id ) {
				$errors['force'] = '' === $row['force']
					? 'Police force is blank.'
					: sprintf( '"%s" does not match any force. NPCC short names like GMP, TVP and Met Police are understood; otherwise add an alias on the Forces screen.', $row['force'] );
			}

			if ( ! isset( $errors['quarter'] ) ) {
				if ( ! array_key_exists( $label, $quarters ) ) {
					$quarters[ $label ] = GCO_PD_Repository::get_quarter_by_label( $label );
					$existing[ $label ] = $quarters[ $label ] ? GCO_PD_Repository::get_npcc_rows( (int) $quarters[ $label ]->id ) : array();
				}
				if ( $quarters[ $label ] && 'published' === $quarters[ $label ]->status ) {
					$errors['quarter'] = sprintf( 'Quarter %s is published. Unpublish it before importing changes.', $label );
				}
			}

			if ( ! isset( $errors['quarter'] ) && null !== $force_id ) {
				$key = $label . '|' . $force_id;
				if ( isset( $seen[ $key ] ) ) {
					$errors['force'] = sprintf( 'This force already appears in quarter %s at row %d.', $label, $seen[ $key ] );
				} else {
					$seen[ $key ] = $row['row_num'];
				}
			}

			$result   = GCO_PD_Validator::validate_npcc( $row['data'] );
			$errors   = array_merge( $errors, $result['errors'] );
			$warnings = $result['warnings'];
			$clean    = $result['clean'];

			// The % column is never stored: warn if the sheet disagrees with the calculation.
			if ( isset( $clean['completed_in_sla'], $clean['apps_completed_12m'] ) && $clean['apps_completed_12m'] > 0 && null !== $row['pct'] ) {
				$calculated = $clean['completed_in_sla'] / $clean['apps_completed_12m'];
				if ( abs( $calculated - $row['pct'] ) > 0.005 ) {
					$warnings['pct'] = sprintf( 'The sheet says %.1f%% but the figures give %.1f%%. The calculated value is used.', $row['pct'] * 100, $calculated * 100 );
				}
			}

			$data_date = $row['data']['data_date'] ?? null;
			if ( $data_date && ! isset( $errors['quarter'] ) ) {
				$dates[ $label ] = $dates[ $label ] ?? $data_date;
				if ( $dates[ $label ] !== $data_date ) {
					$warnings['data_date'] = sprintf( 'Data date differs from earlier rows in %s; the first one (%s) is used.', $label, $dates[ $label ] );
				}
			}

			$current = ( null !== $force_id && isset( $existing[ $label ][ $force_id ] ) ) ? $existing[ $label ][ $force_id ] : null;
			$state   = 'new';
			$changed = array();
			if ( $errors ) {
				$state = 'error';
			} elseif ( $current ) {
				$changed = self::diff( $clean, $current, array_merge( GCO_PD_Validator::NPCC_COLUMNS, array( 'notes' ) ) );
				$state   = $changed ? 'changed' : 'unchanged';
			}

			$out[] = array(
				'row_num'    => $row['row_num'],
				'period'     => $label,
				'data_date'  => $data_date,
				'force_id'   => $force_id,
				'force_name' => null !== $force_id ? self::force_label( $force_id ) : $row['force'],
				'state'      => $state,
				'errors'     => $errors,
				'warnings'   => $warnings,
				'changed'    => $changed,
				'data'       => $clean,
			);
		}
		$out = self::warn_shared_mismatch( $out, GCO_PD_Validator::NPCC_COLUMNS );
		return self::warn_unmarked_twins( $out, array( 'total_holders', 'apps_received_12m', 'apps_completed_12m', 'completed_in_sla' ) );
	}

	/**
	 * Fields whose value differs. Compared as strings so NULL ("not provided") never equals 0.
	 *
	 * @param array  $clean   New values.
	 * @param object $current Existing row.
	 * @param array  $fields  Fields to compare.
	 * @return string[]
	 */
	private static function diff( array $clean, $current, array $fields ) {
		$changed = array();
		foreach ( $fields as $field ) {
			$new = $clean[ $field ] ?? null;
			$old = $current->$field ?? null;
			// DECIMAL columns come back as "12.50": compare numbers as numbers so an unchanged row stays unchanged.
			if ( is_numeric( $new ) && is_numeric( $old ) ) {
				if ( abs( (float) $new - (float) $old ) > 0.00001 ) {
					$changed[] = $field;
				}
				continue;
			}
			if ( (string) $new !== (string) $old ) {
				$changed[] = $field;
			}
		}
		return $changed;
	}

	/**
	 * Cached force display name.
	 *
	 * @param int $force_id Force id.
	 * @return string
	 */
	private static function force_label( $force_id ) {
		static $names = null;
		if ( null === $names ) {
			$names = array();
			foreach ( GCO_PD_Repository::get_forces() as $force ) {
				$names[ (int) $force->id ] = $force->name;
			}
		}
		return $names[ (int) $force_id ] ?? '';
	}

	/**
	 * Counts rows per state.
	 *
	 * @param array $rows Prepared rows.
	 * @return array<string,int>
	 */
	private static function count_states( array $rows ) {
		$counts = array(
			'new'       => 0,
			'changed'   => 0,
			'unchanged' => 0,
			'error'     => 0,
		);
		foreach ( $rows as $row ) {
			++$counts[ $row['state'] ];
		}
		return $counts;
	}

	/* ------------------------------------------------------------ Preview storage */

	/**
	 * Keeps a preview for one hour, private to the current user.
	 *
	 * @param array $preview Preview.
	 * @return string Token.
	 */
	public static function store_preview( array $preview ) {
		$token = wp_generate_password( 20, false, false );
		set_transient( self::transient_key( $token ), $preview, HOUR_IN_SECONDS );
		return $token;
	}

	/**
	 * Reads a stored preview.
	 *
	 * @param string $token Token.
	 * @return array|null
	 */
	public static function get_preview( $token ) {
		if ( 1 !== preg_match( '/^[A-Za-z0-9]{20}$/D', (string) $token ) ) {
			return null;
		}
		$preview = get_transient( self::transient_key( $token ) );
		return is_array( $preview ) ? $preview : null;
	}

	/**
	 * Discards a stored preview.
	 *
	 * @param string $token Token.
	 */
	public static function delete_preview( $token ) {
		if ( 1 === preg_match( '/^[A-Za-z0-9]{20}$/D', (string) $token ) ) {
			delete_transient( self::transient_key( $token ) );
		}
	}

	/**
	 * Per-user transient name.
	 *
	 * @param string $token Token.
	 * @return string
	 */
	private static function transient_key( $token ) {
		return 'gco_pd_import_' . get_current_user_id() . '_' . $token;
	}

	/* ------------------------------------------------------------------ Commit */

	/**
	 * Writes the new and changed rows of a preview to DRAFT rounds / quarters.
	 * Rows with errors, and unchanged rows, are not written.
	 *
	 * @param array $preview Stored preview.
	 * @return array Summary counts.
	 */
	public static function commit( array $preview ) {
		$summary = array(
			'foi_saved'       => 0,
			'npcc_saved'      => 0,
			'rounds_created'  => array(),
			'quarters_created' => array(),
			'skipped'         => 0,
			'failed'          => 0,
		);

		$round_ids = array();
		foreach ( $preview['foi'] ?? array() as $row ) {
			if ( ! in_array( $row['state'], array( 'new', 'changed' ), true ) ) {
				continue;
			}
			$label = $row['period'];
			if ( ! isset( $round_ids[ $label ] ) ) {
				$round = GCO_PD_Repository::get_round_by_label( $label );
				if ( ! $round ) {
					$id = GCO_PD_Repository::create_round( $label );
					if ( is_wp_error( $id ) ) {
						$round_ids[ $label ] = 0;
					} else {
						$round_ids[ $label ]        = (int) $id;
						$summary['rounds_created'][] = $label;
					}
				} else {
					$round_ids[ $label ] = 'published' === $round->status ? 0 : (int) $round->id; // Never touch a published round.
				}
			}
			if ( ! $round_ids[ $label ] ) {
				++$summary['skipped'];
				continue;
			}
			$saved = GCO_PD_Repository::save_response( $round_ids[ $label ], (int) $row['force_id'], $row['data'] );
			is_wp_error( $saved ) ? ++$summary['failed'] : ++$summary['foi_saved'];
		}

		$quarter_ids = array();
		foreach ( $preview['npcc'] ?? array() as $row ) {
			if ( ! in_array( $row['state'], array( 'new', 'changed' ), true ) ) {
				continue;
			}
			$label = $row['period'];
			if ( ! isset( $quarter_ids[ $label ] ) ) {
				$quarter = GCO_PD_Repository::get_quarter_by_label( $label );
				if ( ! $quarter ) {
					$id = GCO_PD_Repository::create_quarter( $label, $row['data_date'] );
					if ( is_wp_error( $id ) ) {
						$quarter_ids[ $label ] = 0;
					} else {
						$quarter_ids[ $label ]          = (int) $id;
						$summary['quarters_created'][] = $label;
					}
				} else {
					$quarter_ids[ $label ] = 'published' === $quarter->status ? 0 : (int) $quarter->id;
					if ( $quarter_ids[ $label ] && $row['data_date'] ) {
						GCO_PD_Repository::set_quarter_date_if_empty( $quarter_ids[ $label ], $row['data_date'] );
					}
				}
			}
			if ( ! $quarter_ids[ $label ] ) {
				++$summary['skipped'];
				continue;
			}
			$saved = GCO_PD_Repository::save_npcc_row( $quarter_ids[ $label ], (int) $row['force_id'], $row['data'] );
			is_wp_error( $saved ) ? ++$summary['failed'] : ++$summary['npcc_saved'];
		}

		return $summary;
	}
}
