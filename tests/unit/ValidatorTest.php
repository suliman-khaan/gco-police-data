<?php
/**
 * Unit tests for GCO_PD_Validator.
 *
 * @package GCO_Police_Data
 */

use PHPUnit\Framework\TestCase;

/**
 * Class ValidatorTest
 */
final class ValidatorTest extends TestCase {

	/**
	 * The Derbyshire FOI row from the agreed template (June 2026).
	 *
	 * @return array
	 */
	private function derbyshire(): array {
		return array(
			'foi_ref'            => '01/FOI/26/008471/V',
			'response_date'      => '2026-06-11',
			'sg_grant_days'      => 32,
			'sg_grant_status'    => 'Actual',
			'fac_grant_days'     => 38,
			'fac_grant_status'   => 'Actual',
			'sg_renew_days'      => 51,
			'sg_renew_status'    => 'Actual',
			'fac_renew_days'     => 51,
			'fac_renew_status'   => 'Actual',
			'variation_days'     => 7,
			'variation_status'   => 'Actual',
			'sg_holders'         => 10541,
			'sg_holders_status'  => 'Actual',
			'fac_holders'        => 3224,
			'fac_holders_status' => 'Actual',
		);
	}

	public function test_round_labels(): void {
		$this->assertTrue( GCO_PD_Validator::is_valid_round_label( '2026-11' ) );
		$this->assertTrue( GCO_PD_Validator::is_valid_round_label( '2026-01' ) );
		foreach ( array( '2026-13', '2026-00', '2026-1', '26-11', '2026/11', '2026-11 ', '', null, 202611 ) as $bad ) {
			$this->assertFalse( GCO_PD_Validator::is_valid_round_label( $bad ), var_export( $bad, true ) );
		}
	}

	public function test_quarter_labels(): void {
		$this->assertTrue( GCO_PD_Validator::is_valid_quarter_label( '2025/26 Q4' ) );
		$this->assertTrue( GCO_PD_Validator::is_valid_quarter_label( '2026/27 Q1' ) );
		$this->assertTrue( GCO_PD_Validator::is_valid_quarter_label( '2099/00 Q2' ) );
		foreach ( array( '2025/27 Q4', '2025/26 Q5', '2025/26 Q0', '2025-26 Q4', '2025/26', 'Q4 2025/26', '' ) as $bad ) {
			$this->assertFalse( GCO_PD_Validator::is_valid_quarter_label( $bad ), $bad );
		}
	}

	public function test_dates(): void {
		$this->assertTrue( GCO_PD_Validator::is_valid_date( '2026-03-23' ) );
		$this->assertFalse( GCO_PD_Validator::is_valid_date( '2026-02-30' ) );
		$this->assertFalse( GCO_PD_Validator::is_valid_date( '23/03/2026' ) );
	}

	public function test_normalize_status(): void {
		$this->assertSame( 'actual', GCO_PD_Validator::normalize_status( ' Actual ' ) );
		$this->assertSame( 'estimate', GCO_PD_Validator::normalize_status( 'ESTIMATE' ) );
		$this->assertSame( 'not_provided', GCO_PD_Validator::normalize_status( 'Not provided' ) );
		$this->assertSame( 'not_provided', GCO_PD_Validator::normalize_status( 'not-provided' ) );
		$this->assertNull( GCO_PD_Validator::normalize_status( '' ) );
		$this->assertNull( GCO_PD_Validator::normalize_status( null ) );
		$this->assertFalse( GCO_PD_Validator::normalize_status( 'approx' ) );
	}

	public function test_parse_count_never_turns_blank_into_zero(): void {
		$this->assertNull( GCO_PD_Validator::parse_count( '' ) );
		$this->assertNull( GCO_PD_Validator::parse_count( '  ' ) );
		$this->assertNull( GCO_PD_Validator::parse_count( null ) );
		$this->assertSame( 0, GCO_PD_Validator::parse_count( 0 ) );
		$this->assertSame( 0, GCO_PD_Validator::parse_count( '0' ) );
		$this->assertSame( 32, GCO_PD_Validator::parse_count( '32.0' ) );
		$this->assertSame( 32, GCO_PD_Validator::parse_count( 32.0 ) );
		foreach ( array( -1, '-3', 3.5, '3.5', '1,200', 'abc', array(), true ) as $bad ) {
			$this->assertFalse( GCO_PD_Validator::parse_count( $bad ), var_export( $bad, true ) );
		}
	}

	public function test_valid_derbyshire_row(): void {
		$result = GCO_PD_Validator::validate_response( $this->derbyshire() );
		$this->assertSame( array(), $result['errors'] );
		$this->assertSame( array(), $result['warnings'] );
		$this->assertSame( 32, $result['clean']['sg_grant_days'] );
		$this->assertSame( 'actual', $result['clean']['sg_grant_status'] );
		$this->assertSame( 10541, $result['clean']['sg_holders'] );
		$this->assertSame( '01/FOI/26/008471/V', $result['clean']['foi_ref'] );
		$this->assertSame( '2026-06-11', $result['clean']['response_date'] );
	}

	public function test_blank_value_and_blank_status_become_not_provided_not_zero(): void {
		$row = $this->derbyshire();
		$row['variation_days']   = '';
		$row['variation_status'] = '';
		$result = GCO_PD_Validator::validate_response( $row );
		$this->assertSame( array(), $result['errors'] );
		$this->assertNull( $result['clean']['variation_days'] );
		$this->assertSame( 'not_provided', $result['clean']['variation_status'] );
	}

	public function test_missing_keys_are_not_provided(): void {
		$result = GCO_PD_Validator::validate_response( array() );
		$this->assertSame( array(), $result['errors'] );
		foreach ( GCO_PD_Validator::FOI_METRICS as $meta ) {
			$this->assertNull( $result['clean'][ $meta['column'] ] );
			$this->assertSame( 'not_provided', $result['clean'][ $meta['status'] ] );
		}
	}

	public function test_value_with_not_provided_status_is_rejected(): void {
		$row = $this->derbyshire();
		$row['sg_grant_status'] = 'Not provided';
		$result = GCO_PD_Validator::validate_response( $row );
		$this->assertArrayHasKey( 'sg_grant_days', $result['errors'] );
	}

	public function test_value_with_blank_status_is_rejected(): void {
		$row = $this->derbyshire();
		$row['fac_grant_status'] = '';
		$result = GCO_PD_Validator::validate_response( $row );
		$this->assertArrayHasKey( 'fac_grant_days', $result['errors'] );
	}

	public function test_status_without_value_is_rejected(): void {
		foreach ( array( 'Actual', 'Estimate' ) as $status ) {
			$row = $this->derbyshire();
			$row['sg_renew_days']   = '';
			$row['sg_renew_status'] = $status;
			$result = GCO_PD_Validator::validate_response( $row );
			$this->assertArrayHasKey( 'sg_renew_days', $result['errors'], $status );
		}
	}

	public function test_zero_is_a_real_value(): void {
		$row = $this->derbyshire();
		$row['variation_days'] = 0;
		$result = GCO_PD_Validator::validate_response( $row );
		$this->assertSame( array(), $result['errors'] );
		$this->assertSame( 0, $result['clean']['variation_days'] );
		$this->assertSame( 'actual', $result['clean']['variation_status'] );
	}

	public function test_bad_numbers_and_statuses_are_rejected(): void {
		$row = $this->derbyshire();
		$row['sg_grant_days']   = '4 weeks';
		$row['fac_grant_days']  = -5;
		$row['sg_renew_status'] = 'roughly';
		$row['variation_days']  = 70000; // Above SMALLINT UNSIGNED.
		$errors = GCO_PD_Validator::validate_response( $row )['errors'];
		$this->assertArrayHasKey( 'sg_grant_days', $errors );
		$this->assertArrayHasKey( 'fac_grant_days', $errors );
		$this->assertArrayHasKey( 'sg_renew_days', $errors );
		$this->assertArrayHasKey( 'variation_days', $errors );
	}

	public function test_long_waits_warn_but_do_not_fail(): void {
		$row = $this->derbyshire();
		$row['sg_grant_days']   = 400;
		$row['sg_grant_status'] = 'Estimate';
		$result = GCO_PD_Validator::validate_response( $row );
		$this->assertSame( array(), $result['errors'] );
		$this->assertArrayHasKey( 'sg_grant_days', $result['warnings'] );
		$this->assertSame( 'estimate', $result['clean']['sg_grant_status'] );
	}

	public function test_holders_do_not_trigger_the_days_warning(): void {
		$result = GCO_PD_Validator::validate_response( $this->derbyshire() );
		$this->assertArrayNotHasKey( 'sg_holders', $result['warnings'] );
	}

	public function test_response_date_and_ref_checks(): void {
		$row = $this->derbyshire();
		$row['response_date'] = '2026-02-30';
		$row['foi_ref']       = str_repeat( 'x', 81 );
		$errors = GCO_PD_Validator::validate_response( $row )['errors'];
		$this->assertArrayHasKey( 'response_date', $errors );
		$this->assertArrayHasKey( 'foi_ref', $errors );

		$row['response_date'] = '';
		$row['foi_ref']       = '';
		$result = GCO_PD_Validator::validate_response( $row );
		$this->assertSame( array(), $result['errors'] );
		$this->assertNull( $result['clean']['response_date'] );
		$this->assertNull( $result['clean']['foi_ref'] );
	}

	/**
	 * Avon and Somerset, 2025/26 Q4 (from the NPCC report): 5,810 received, 5,657 completed, 5,070 in SLA.
	 *
	 * @return array
	 */
	private function avon(): array {
		return array(
			'total_holders'      => 19825,
			'total_certificates' => 25410,
			'apps_received_12m'  => 5810,
			'apps_completed_12m' => 5657,
			'completed_in_sla'   => 5070,
			'temporary_permits'  => 5,
		);
	}

	public function test_valid_npcc_row(): void {
		$result = GCO_PD_Validator::validate_npcc( $this->avon() );
		$this->assertSame( array(), $result['errors'] );
		$this->assertSame( 5070, $result['clean']['completed_in_sla'] );
		$this->assertArrayNotHasKey( 'pct_within_sla', $result['clean'] );
	}

	public function test_npcc_blank_stays_null(): void {
		$row = $this->avon();
		$row['temporary_permits'] = '';
		$result = GCO_PD_Validator::validate_npcc( $row );
		$this->assertSame( array(), $result['errors'] );
		$this->assertNull( $result['clean']['temporary_permits'] );
	}

	public function test_npcc_sla_cannot_exceed_completed(): void {
		$row = $this->avon();
		$row['completed_in_sla'] = 6000;
		$this->assertArrayHasKey( 'completed_in_sla', GCO_PD_Validator::validate_npcc( $row )['errors'] );
	}

	public function test_npcc_sla_check_skipped_when_either_side_is_blank(): void {
		$row = $this->avon();
		$row['apps_completed_12m'] = '';
		$this->assertSame( array(), GCO_PD_Validator::validate_npcc( $row )['errors'] );
	}

	public function test_npcc_bad_numbers(): void {
		$row = $this->avon();
		$row['total_holders'] = '19,825';
		$this->assertArrayHasKey( 'total_holders', GCO_PD_Validator::validate_npcc( $row )['errors'] );
	}

	public function test_npcc_certificates_below_holders_warns(): void {
		$row = $this->avon();
		$row['total_certificates'] = 100;
		$result = GCO_PD_Validator::validate_npcc( $row );
		$this->assertSame( array(), $result['errors'] );
		$this->assertArrayHasKey( 'total_certificates', $result['warnings'] );
	}

	public function test_quarter_validation(): void {
		$ok = GCO_PD_Validator::validate_quarter( array( 'label' => '2025/26 Q4', 'data_date' => '2026-03-23' ) );
		$this->assertSame( array(), $ok['errors'] );
		$this->assertSame( '2026-03-23', $ok['clean']['data_date'] );

		$bad = GCO_PD_Validator::validate_quarter( array( 'label' => '2025 Q4', 'data_date' => 'yesterday' ) );
		$this->assertArrayHasKey( 'label', $bad['errors'] );
		$this->assertArrayHasKey( 'data_date', $bad['errors'] );
	}
}
