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
	 * @return array{value:int|null,status:string|null,error:string|null}
	 */
	private static function validate_value_status( $raw_value, $raw_status, $max ) {
		$value  = self::parse_count( $raw_value );
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
