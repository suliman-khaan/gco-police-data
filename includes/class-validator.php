<?php
/**
 * Validation rules for rounds, quarters, FOI responses and NPCC rows.
 *
 * Pure PHP (no WordPress calls) so it is unit-testable and shared by the admin grid,
 * the importer and the repository. Error messages are plain English strings; the admin
 * layer may wrap them for translation.
 *
 * Core rule: NULL means "not provided" and is never coerced to 0. A value requires the
 * status actual|estimate; no value requires not_provided.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class GCO_PD_Validator
 */
final class GCO_PD_Validator {

	const STATUS_ACTUAL       = 'actual';
	const STATUS_ESTIMATE     = 'estimate';
	const STATUS_NOT_PROVIDED = 'not_provided';

	/**
	 * Allowed statuses.
	 *
	 * @var string[]
	 */
	const STATUSES = array( self::STATUS_ACTUAL, self::STATUS_ESTIMATE, self::STATUS_NOT_PROVIDED );

	/**
	 * FOI metrics: key => type (days|count). Each has a `{key}_days|{key}` value column and `{key}_status`.
	 * Column names follow the schema: sg_grant_days / sg_grant_status, sg_holders / sg_holders_status, etc.
	 *
	 * @var array<string,array{column:string,status:string,type:string,max:int}>
	 */
	const FOI_METRICS = array(
		'sg_grant'   => array( 'column' => 'sg_grant_days', 'status' => 'sg_grant_status', 'type' => 'days', 'max' => 65535 ),
		'fac_grant'  => array( 'column' => 'fac_grant_days', 'status' => 'fac_grant_status', 'type' => 'days', 'max' => 65535 ),
		'sg_renew'   => array( 'column' => 'sg_renew_days', 'status' => 'sg_renew_status', 'type' => 'days', 'max' => 65535 ),
		'fac_renew'  => array( 'column' => 'fac_renew_days', 'status' => 'fac_renew_status', 'type' => 'days', 'max' => 65535 ),
		'variation'  => array( 'column' => 'variation_days', 'status' => 'variation_status', 'type' => 'days', 'max' => 65535 ),
		'sg_holders' => array( 'column' => 'sg_holders', 'status' => 'sg_holders_status', 'type' => 'count', 'max' => 4294967295 ),
		'fac_holders' => array( 'column' => 'fac_holders', 'status' => 'fac_holders_status', 'type' => 'count', 'max' => 4294967295 ),
	);

	/**
	 * Extra FOI figures asked from round 2026-11 onwards (applications, backlog, service standards).
	 * Same value/status pairing as FOI_METRICS, but kept apart so they never change the coverage counts
	 * or the "figures not provided" totals of older rounds, which were never asked these questions.
	 *
	 * type: count (whole number) | days | percent (0 to 100, up to 2 decimals).
	 *
	 * @var array<string,array{column:string,status:string,type:string,max:int}>
	 */
	const FOI_EXTRA_METRICS = array(
		'apps_received'  => array( 'column' => 'apps_received', 'status' => 'apps_received_status', 'type' => 'count', 'max' => 4294967295 ),
		'apps_completed' => array( 'column' => 'apps_completed', 'status' => 'apps_completed_status', 'type' => 'count', 'max' => 4294967295 ),
		'outstanding'    => array( 'column' => 'outstanding', 'status' => 'outstanding_status', 'type' => 'count', 'max' => 4294967295 ),
		'oldest'         => array( 'column' => 'oldest_days', 'status' => 'oldest_days_status', 'type' => 'days', 'max' => 65535 ),
		'standard_days'  => array( 'column' => 'standard_days', 'status' => 'standard_days_status', 'type' => 'days', 'max' => 65535 ),
		'standard_met'   => array( 'column' => 'standard_met_pct', 'status' => 'standard_met_pct_status', 'type' => 'percent', 'max' => 100 ),
	);

	/**
	 * Backlog age bands: column => label. One shared unit (count or percent) and one shared status.
	 *
	 * @var array<string,string>
	 */
	const BACKLOG_BANDS = array(
		'backlog_m0_3'     => 'Up to 3 months',
		'backlog_m3_6'     => '3 to 6 months',
		'backlog_m6_12'    => '6 to 12 months',
		'backlog_m12_plus' => 'Over 12 months',
	);

	const UNIT_COUNT   = 'count';
	const UNIT_PERCENT = 'percent';

	/**
	 * NPCC count columns.
	 *
	 * @var string[]
	 */
	const NPCC_COLUMNS = array(
		'total_holders',
		'total_certificates',
		'apps_received_12m',
		'apps_completed_12m',
		'completed_in_sla',
		'temporary_permits',
	);

	/**
	 * Days above this are accepted but flagged for a second look (likely weeks/months entered as days, or a typo).
	 */
	const DAYS_WARNING_THRESHOLD = 365;

	/**
	 * FOI round label, e.g. 2026-11.
	 *
	 * @param mixed $label Candidate label.
	 * @return bool
	 */
	public static function is_valid_round_label( $label ) {
		return is_string( $label ) && 1 === preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/D', $label );
	}

	/**
	 * NPCC quarter label, e.g. "2025/26 Q4". The second year must follow the first (2099/00 is valid).
	 *
	 * @param mixed $label Candidate label.
	 * @return bool
	 */
	public static function is_valid_quarter_label( $label ) {
		if ( ! is_string( $label ) || 1 !== preg_match( '/^(\d{4})\/(\d{2}) Q[1-4]$/D', $label, $m ) ) {
			return false;
		}
		return ( ( (int) $m[1] + 1 ) % 100 ) === (int) $m[2];
	}

	/**
	 * Strict Y-m-d date check (rejects 2026-02-30).
	 *
	 * @param mixed $date Candidate date.
	 * @return bool
	 */
	public static function is_valid_date( $date ) {
		if ( ! is_string( $date ) || 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/D', $date, $m ) ) {
			return false;
		}
		return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
	}

	/**
	 * Normalises a status typed by a person or read from a spreadsheet.
	 *
	 * "Actual", " estimate ", "Not provided", "not-provided" and "NOT_PROVIDED" are all accepted.
	 *
	 * @param mixed $raw Raw status.
	 * @return string|null|false Canonical status, null when blank, false when unrecognised.
	 */
	public static function normalize_status( $raw ) {
		if ( null === $raw ) {
			return null;
		}
		$value = strtolower( trim( (string) $raw ) );
		if ( '' === $value ) {
			return null;
		}
		$value = preg_replace( '/[\s\-]+/', '_', $value );
		return in_array( $value, self::STATUSES, true ) ? $value : false;
	}

	/**
	 * Parses a whole non-negative number without ever turning "no value" into 0.
	 *
	 * @param mixed $raw Raw value: int, integral float, digit string, or blank.
	 * @return int|null|false Integer, null when blank, false when not a non-negative whole number.
	 */
	public static function parse_count( $raw ) {
		if ( null === $raw ) {
			return null;
		}
		if ( is_int( $raw ) ) {
			return $raw >= 0 ? $raw : false;
		}
		if ( is_float( $raw ) ) {
			return ( $raw >= 0 && floor( $raw ) === $raw && $raw <= PHP_INT_MAX ) ? (int) $raw : false;
		}
		if ( ! is_string( $raw ) ) {
			return false;
		}
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return null;
		}
		// Spreadsheets sometimes hand us "32.0" for a whole number.
		if ( 1 === preg_match( '/^\d+(\.0+)?$/D', $raw ) ) {
			return (int) $raw;
		}
		return false;
	}

	/**
	 * Parses a decimal number (not negative) without ever turning "no value" into 0.
	 *
	 * @param mixed $raw Raw value: int, float, numeric string (a trailing % is ignored), or blank.
	 * @return float|null|false Number, null when blank, false when not a non-negative number.
	 */
	public static function parse_decimal( $raw ) {
		if ( null === $raw ) {
			return null;
		}
		if ( is_int( $raw ) || is_float( $raw ) ) {
			return $raw >= 0 ? (float) $raw : false;
		}
		if ( ! is_string( $raw ) ) {
			return false;
		}
		$raw = str_replace( array( '%', ',' ), '', trim( $raw ) );
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return null;
		}
		if ( 1 === preg_match( '/^\d+(\.\d+)?$/D', $raw ) ) {
			return (float) $raw;
		}
		return false;
	}

	/**
	 * Parses one percentage (0 to 100). A bare value of 1 or less is read as a fraction (0.85 = 85%) because
	 * Excel stores a cell typed as "85%" as 0.85; typing "0.5%" with the sign keeps it as 0.5 per cent.
	 *
	 * @param mixed $raw Raw value.
	 * @return array{value:float|null|false,converted:bool}
	 */
	public static function parse_percent( $raw ) {
		$has_sign  = is_string( $raw ) && false !== strpos( $raw, '%' );
		$value     = self::parse_decimal( $raw );
		$converted = false;
		if ( is_float( $value ) && ! $has_sign && $value > 0 && $value <= 1 ) {
			$value     = $value * 100;
			$converted = true;
		}
		if ( is_float( $value ) ) {
			if ( $value > 100 ) {
				return array(
					'value'     => false,
					'converted' => false,
				);
			}
			$value = round( $value, 2 );
		}
		return array(
			'value'     => $value,
			'converted' => $converted,
		);
	}

	/**
	 * Normalises the backlog unit typed by a person or read from a spreadsheet.
	 *
	 * @param mixed $raw Raw unit: blank (means count), Count, Number, Percentage, %, ...
	 * @return string|false 'count' or 'percent', false when unrecognised.
	 */
	public static function normalize_unit( $raw ) {
		$value = strtolower( trim( (string) $raw ) );
		if ( in_array( $value, array( '', 'count', 'counts', 'number', 'numbers', 'actual number', 'actual numbers', 'n', self::UNIT_COUNT ), true ) ) {
			return self::UNIT_COUNT;
		}
		if ( in_array( $value, array( 'percent', 'percentage', 'percentages', 'per cent', '%', 'pct', self::UNIT_PERCENT ), true ) ) {
			return self::UNIT_PERCENT;
		}
		return false;
	}

	/**
	 * Validates the extra FOI figures (applications, backlog, service standards) of one response row.
	 *
	 * @param array $row      Raw row.
	 * @param array $clean    Clean values so far (modified): adds the extra columns.
	 * @param array $errors   Errors by column (modified).
	 * @param array $warnings Warnings by column (modified).
	 */
	private static function validate_extras( array $row, array &$clean, array &$errors, array &$warnings ) {
		foreach ( self::FOI_EXTRA_METRICS as $meta ) {
			$column = $meta['column'];
			if ( 'percent' === $meta['type'] ) {
				$parsed = self::parse_percent( $row[ $column ] ?? null );
				$result = self::validate_value_status( $parsed['value'], $row[ $meta['status'] ] ?? null, $meta['max'], true );
				if ( $parsed['converted'] && ! $result['error'] ) {
					$warnings[ $column ] = sprintf( 'Read %s as %s%% (a fraction). If you meant a very small percentage, type it with a %% sign.', rtrim( rtrim( number_format( $parsed['value'] / 100, 4, '.', '' ), '0' ), '.' ), rtrim( rtrim( number_format( $parsed['value'], 2, '.', '' ), '0' ), '.' ) );
				}
			} else {
				$result = self::validate_value_status( $row[ $column ] ?? null, $row[ $meta['status'] ] ?? null, $meta['max'] );
			}
			if ( $result['error'] ) {
				$errors[ $column ] = ( 'percent' === $meta['type'] && false === $parsed['value'] ) ? 'Must be a percentage between 0 and 100, or left blank when not provided.' : $result['error'];
				continue;
			}
			$clean[ $column ]         = $result['value'];
			$clean[ $meta['status'] ] = $result['status'];
			if ( 'days' === $meta['type'] && null !== $result['value'] && $result['value'] > 3650 ) {
				$warnings[ $column ] = sprintf( '%d days is more than ten years; check it is not a typo.', $result['value'] );
			}
		}

		$period                = isset( $row['volumes_period'] ) ? trim( (string) $row['volumes_period'] ) : '';
		$clean['volumes_period'] = '' === $period ? null : $period;
		if ( strlen( $period ) > 120 ) {
			$errors['volumes_period'] = 'The applications period must be 120 characters or fewer.';
		}
		$standard              = isset( $row['standard_text'] ) ? trim( (string) $row['standard_text'] ) : '';
		$clean['standard_text'] = '' === $standard ? null : $standard;
		if ( strlen( $standard ) > 255 ) {
			$errors['standard_text'] = 'The service standard wording must be 255 characters or fewer.';
		}

		self::validate_backlog( $row, $clean, $errors, $warnings );
	}

	/**
	 * Backlog age bands: one unit (count or percentage), one status for the group.
	 *
	 * Count: whole numbers; when all four bands and the outstanding figure are given they should add up.
	 * Percentage: 0 to 100 each, adding to about 100. Bands typed as fractions that add to 1 (a spreadsheet
	 * percent-formatted cell) are read as percentages. Percentages are stored as given and never summed.
	 *
	 * @param array $row      Raw row.
	 * @param array $clean    Clean values (modified).
	 * @param array $errors   Errors by column (modified).
	 * @param array $warnings Warnings by column (modified).
	 */
	private static function validate_backlog( array $row, array &$clean, array &$errors, array &$warnings ) {
		$unit = self::normalize_unit( $row['backlog_unit'] ?? null );
		if ( false === $unit ) {
			$errors['backlog_unit'] = 'Backlog unit must be Count or Percentage.';
			$unit                   = self::UNIT_COUNT;
		}
		$clean['backlog_unit'] = $unit;

		$values = array();
		$failed = false;
		foreach ( array_keys( self::BACKLOG_BANDS ) as $column ) {
			$raw = $row[ $column ] ?? null;
			if ( self::UNIT_PERCENT === $unit ) {
				$value = self::parse_decimal( $raw );
				if ( false === $value || ( is_float( $value ) && $value > 100 ) ) {
					$errors[ $column ] = 'Must be a percentage between 0 and 100, or left blank when not provided.';
					$failed            = true;
					continue;
				}
			} else {
				$value = self::parse_count( $raw );
				if ( false === $value || ( is_int( $value ) && $value > 4294967295 ) ) {
					$errors[ $column ] = 'Backlog counts must be whole numbers, or left blank when not provided. If the force gave percentages, set the unit to Percentage.';
					$failed            = true;
					continue;
				}
			}
			$values[ $column ] = $value;
		}

		$given = array_filter(
			$values,
			static function ( $value ) {
				return null !== $value;
			}
		);

		if ( ! $failed && self::UNIT_PERCENT === $unit && $given ) {
			$sum = array_sum( $given );
			if ( max( $given ) <= 1 && $sum >= 0.98 && $sum <= 1.02 ) {
				foreach ( $given as $column => $value ) {
					$values[ $column ] = $value * 100;
				}
				$given    = array_filter(
					$values,
					static function ( $value ) {
						return null !== $value;
					}
				);
				$sum      = array_sum( $given );
				$warnings['backlog_unit'] = 'The backlog percentages were entered as fractions that add up to 1, so they were read as percentages.';
			}
			if ( count( $given ) > 1 && ( $sum < 99 || $sum > 101 ) ) {
				$warnings['backlog_status'] = sprintf( 'The backlog percentages add up to %s%%, not about 100%%. Check them.', rtrim( rtrim( number_format( $sum, 2, '.', '' ), '0' ), '.' ) );
			}
		}

		$status = self::normalize_status( $row['backlog_status'] ?? null );
		if ( false === $status ) {
			$errors['backlog_status'] = 'Status must be Actual, Estimate or Not provided.';
		} elseif ( ! $failed ) {
			if ( $given ) {
				if ( null === $status ) {
					$errors['backlog_status'] = 'Choose Actual or Estimate for the backlog figures.';
				} elseif ( self::STATUS_NOT_PROVIDED === $status ) {
					$errors['backlog_status'] = 'The backlog figures cannot be marked Not provided. Clear them or choose Actual or Estimate.';
				}
			} elseif ( null !== $status && self::STATUS_NOT_PROVIDED !== $status ) {
				$errors['backlog_status'] = 'A backlog status of Actual or Estimate needs at least one figure. Leave the status as Not provided if there are none.';
			}
		}

		foreach ( array_keys( self::BACKLOG_BANDS ) as $column ) {
			$value            = $values[ $column ] ?? null;
			$clean[ $column ] = null === $value ? null : ( self::UNIT_PERCENT === $unit ? round( (float) $value, 2 ) : (int) $value );
		}
		$clean['backlog_status'] = ( $given && ! isset( $errors['backlog_status'] ) ) ? $status : self::STATUS_NOT_PROVIDED;

		// Counts should add up to the outstanding total when everything is given.
		if (
			! $failed
			&& self::UNIT_COUNT === $unit
			&& count( $given ) === count( self::BACKLOG_BANDS )
			&& isset( $clean['outstanding'] )
			&& null !== $clean['outstanding']
			&& array_sum( $given ) !== $clean['outstanding']
		) {
			$warnings['backlog_status'] = sprintf( 'The backlog age bands add up to %s but outstanding applications is %s.', number_format( array_sum( $given ) ), number_format( $clean['outstanding'] ) );
		}
	}

	/**
	 * Validates one FOI response row (one force, one round).
	 *
	 * Input keys: foi_ref, response_date (Y-m-d), the seven value columns and seven status columns
	 * (see FOI_METRICS), notes, pdf_id. Unknown keys are ignored.
	 *
	 * @param array $row Raw row.
	 * @return array{clean:array,errors:array<string,string>,warnings:array<string,string>}
	 */
	public static function validate_response( array $row ) {
		$clean    = array();
		$errors   = array();
		$warnings = array();

		foreach ( self::FOI_METRICS as $key => $meta ) {
			$result = self::validate_value_status(
				$row[ $meta['column'] ] ?? null,
				$row[ $meta['status'] ] ?? null,
				$meta['max']
			);

			if ( $result['error'] ) {
				$errors[ $meta['column'] ] = $result['error'];
				continue;
			}

			$clean[ $meta['column'] ] = $result['value'];
			$clean[ $meta['status'] ] = $result['status'];

			if ( 'days' === $meta['type'] && null !== $result['value'] && $result['value'] > self::DAYS_WARNING_THRESHOLD ) {
				$warnings[ $meta['column'] ] = sprintf( '%d days is unusually long; check it is not weeks or months.', $result['value'] );
			}
		}

		self::validate_extras( $row, $clean, $errors, $warnings );

		$ref = isset( $row['foi_ref'] ) ? trim( (string) $row['foi_ref'] ) : '';
		if ( strlen( $ref ) > 80 ) {
			$errors['foi_ref'] = 'FOI reference must be 80 characters or fewer.';
		} else {
			$clean['foi_ref'] = '' === $ref ? null : $ref;
		}

		$date = isset( $row['response_date'] ) ? trim( (string) $row['response_date'] ) : '';
		if ( '' === $date ) {
			$clean['response_date'] = null;
		} elseif ( self::is_valid_date( $date ) ) {
			$clean['response_date'] = $date;
		} else {
			$errors['response_date'] = 'Response date must be a real date in YYYY-MM-DD format.';
		}

		$clean['notes'] = isset( $row['notes'] ) && '' !== trim( (string) $row['notes'] ) ? (string) $row['notes'] : null;

		$pdf = self::parse_count( $row['pdf_id'] ?? null );
		if ( false === $pdf ) {
			$errors['pdf_id'] = 'PDF reference is not valid.';
		} else {
			$clean['pdf_id'] = $pdf;
		}

		return array(
			'clean'    => $clean,
			'errors'   => $errors,
			'warnings' => $warnings,
		);
	}

	/**
	 * Validates one NPCC row (one force, one quarter).
	 *
	 * % within SLA is deliberately not an input: it is calculated, never typed or stored.
	 *
	 * @param array $row Raw row with the NPCC_COLUMNS keys, plus optional notes.
	 * @return array{clean:array,errors:array<string,string>,warnings:array<string,string>}
	 */
	public static function validate_npcc( array $row ) {
		$clean    = array();
		$errors   = array();
		$warnings = array();

		foreach ( self::NPCC_COLUMNS as $column ) {
			$value = self::parse_count( $row[ $column ] ?? null );
			if ( false === $value || ( is_int( $value ) && $value > 4294967295 ) ) {
				$errors[ $column ] = 'Must be a whole number of 0 or more, or left blank when not published.';
				continue;
			}
			$clean[ $column ] = $value;
		}

		if (
			! isset( $errors['completed_in_sla'] )
			&& ! isset( $errors['apps_completed_12m'] )
			&& isset( $clean['completed_in_sla'], $clean['apps_completed_12m'] )
			&& $clean['completed_in_sla'] > $clean['apps_completed_12m']
		) {
			$errors['completed_in_sla'] = 'Completed within SLA cannot be more than applications completed.';
		}

		if (
			isset( $clean['total_certificates'], $clean['total_holders'] )
			&& $clean['total_certificates'] < $clean['total_holders']
		) {
			$warnings['total_certificates'] = 'Total certificates is lower than total holders; check the figures.';
		}

		$clean['notes'] = isset( $row['notes'] ) && '' !== trim( (string) $row['notes'] ) ? (string) $row['notes'] : null;

		return array(
			'clean'    => $clean,
			'errors'   => $errors,
			'warnings' => $warnings,
		);
	}

	/**
	 * Validates a quarter's own fields (not its force rows).
	 *
	 * @param array $row Keys: label, data_date.
	 * @return array{clean:array,errors:array<string,string>}
	 */
	public static function validate_quarter( array $row ) {
		$clean  = array();
		$errors = array();

		$label = isset( $row['label'] ) ? trim( (string) $row['label'] ) : '';
		if ( self::is_valid_quarter_label( $label ) ) {
			$clean['label'] = $label;
		} else {
			$errors['label'] = 'Quarter must look like "2025/26 Q4".';
		}

		$date = isset( $row['data_date'] ) ? trim( (string) $row['data_date'] ) : '';
		if ( '' === $date ) {
			$clean['data_date'] = null;
		} elseif ( self::is_valid_date( $date ) ) {
			$clean['data_date'] = $date;
		} else {
			$errors['data_date'] = 'Data date must be a real date in YYYY-MM-DD format.';
		}

		return array(
			'clean'  => $clean,
			'errors' => $errors,
		);
	}

	/**
	 * Applies the value/status pairing rule to one metric.
	 *
	 * @param mixed $raw_value  Raw value.
	 * @param mixed $raw_status Raw status.
	 * @param int   $max        Largest value the column can hold.
	 * @param bool  $decimal    Whether $raw_value is an already parsed decimal (percentages).
	 * @return array{value:int|float|null,status:string|null,error:string|null}
	 */
	private static function validate_value_status( $raw_value, $raw_status, $max, $decimal = false ) {
		$value  = $decimal ? ( false === $raw_value ? false : ( null === $raw_value ? null : (float) $raw_value ) ) : self::parse_count( $raw_value );
		$status = self::normalize_status( $raw_status );
		$fail   = static function ( $message ) {
			return array(
				'value'  => null,
				'status' => null,
				'error'  => $message,
			);
		};

		if ( false === $value ) {
			return $fail( 'Must be a whole number of 0 or more, or left blank when not provided.' );
		}
		if ( false === $status ) {
			return $fail( 'Status must be Actual, Estimate or Not provided.' );
		}
		if ( null !== $value && $value > $max ) {
			return $fail( sprintf( 'Value is too large (maximum %s).', number_format( $max ) ) );
		}

		if ( null === $value ) {
			// No value: the only consistent status is not_provided (blank status is read as that).
			if ( null === $status || self::STATUS_NOT_PROVIDED === $status ) {
				return array(
					'value'  => null,
					'status' => self::STATUS_NOT_PROVIDED,
					'error'  => null,
				);
			}
			return $fail( 'A status of Actual or Estimate needs a value. Leave the status as Not provided if there is no figure.' );
		}

		// A value is present.
		if ( null === $status ) {
			return $fail( 'Choose Actual or Estimate for this figure.' );
		}
		if ( self::STATUS_NOT_PROVIDED === $status ) {
			return $fail( 'A figure cannot be marked Not provided. Clear the value or choose Actual or Estimate.' );
		}

		return array(
			'value'  => $value,
			'status' => $status,
			'error'  => null,
		);
	}
}
