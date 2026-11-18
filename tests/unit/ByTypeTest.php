<?php
/**
 * Unit tests for FOI data by application type, per-category service standards and online/post answers.
 *
 * @package GCO_Police_Data
 */

use PHPUnit\Framework\TestCase;

/**
 * Class ByTypeTest
 */
final class ByTypeTest extends TestCase {

	private function force( int $id, string $slug, string $group = '' ): array {
		return array(
			'id'           => $id,
			'slug'         => $slug,
			'name'         => ucfirst( $slug ) . ' Police',
			'country'      => 'England',
			'aliases'      => '',
			'page_id'      => null,
			'is_active'    => 1,
			'shared_group' => $group,
		);
	}

	/**
	 * A response row built through the validator exactly as an import would.
	 */
	private function response( int $round_id, int $force_id, array $raw = array() ): array {
		$base  = array(
			'round_id'        => $round_id,
			'force_id'        => $force_id,
			'response_date'   => '2026-12-10',
			'sg_grant_days'   => 40,
			'sg_grant_status' => 'Actual',
		);
		$clean = GCO_PD_Validator::validate_response( array_merge( $base, $raw ) );
		return array_merge( $base, $clean['clean'] );
	}

	/**
	 * A type row built through the validator.
	 */
	private function type_row( int $round_id, int $force_id, string $type, array $raw ): array {
		$clean = GCO_PD_Validator::validate_type_row( $raw );
		return array_merge( array( 'round_id' => $round_id, 'force_id' => $force_id, 'app_type' => $type ), $clean['clean'] );
	}

	private function fig( $value ): array {
		return array( $value, null === $value ? 'Not provided' : 'Actual' );
	}

	/* -------------------------------------------------------------- Validator */

	public function test_application_types_are_the_five_agreed_ones(): void {
		$this->assertSame( array( 'sg_grant', 'fac_grant', 'sg_renew', 'fac_renew', 'variation' ), array_keys( GCO_PD_Validator::APP_TYPES ) );
		$this->assertSame( 'Firearm variation', GCO_PD_Validator::APP_TYPES['variation'], 'variations are firearm certificates only' );
	}

	public function test_application_type_names_from_a_spreadsheet_are_understood(): void {
		foreach ( array( 'New Shotgun Grant', 'new shotgun grants', 'sg_grant', 'Shotgun Grant' ) as $raw ) {
			$this->assertSame( 'sg_grant', GCO_PD_Validator::normalize_app_type( $raw ), $raw );
		}
		$this->assertSame( 'fac_grant', GCO_PD_Validator::normalize_app_type( 'New Firearm Grant' ) );
		$this->assertSame( 'sg_renew', GCO_PD_Validator::normalize_app_type( 'Shotgun Renewal' ) );
		$this->assertSame( 'fac_renew', GCO_PD_Validator::normalize_app_type( 'firearm renewals' ) );
		$this->assertSame( 'variation', GCO_PD_Validator::normalize_app_type( 'Firearm Variation' ) );
		$this->assertSame( 'variation', GCO_PD_Validator::normalize_app_type( 'Variations' ) );
		$this->assertFalse( GCO_PD_Validator::normalize_app_type( 'Shotgun Variation' ), 'shotgun certificates have no variations' );
		$this->assertFalse( GCO_PD_Validator::normalize_app_type( '' ) );
	}

	public function test_yes_no_answers(): void {
		foreach ( array( 'Yes', 'y', 'TRUE', '1' ) as $raw ) {
			$this->assertSame( 'yes', GCO_PD_Validator::normalize_yesno( $raw ), $raw );
		}
		foreach ( array( 'No', 'n', 'false', '0' ) as $raw ) {
			$this->assertSame( 'no', GCO_PD_Validator::normalize_yesno( $raw ), $raw );
		}
		$this->assertNull( GCO_PD_Validator::normalize_yesno( '' ) );
		$this->assertNull( GCO_PD_Validator::normalize_yesno( null ), 'blank is not answered, never "no"' );
		$this->assertFalse( GCO_PD_Validator::normalize_yesno( 'maybe' ) );
	}

	public function test_online_and_post_fields_validate_and_default_to_null(): void {
		$ok = GCO_PD_Validator::validate_response( array( 'online_grant' => 'Yes', 'online_renew' => 'No', 'post_required' => 'yes' ) );
		$this->assertSame( array(), $ok['errors'] );
		$this->assertSame( 'yes', $ok['clean']['online_grant'] );
		$this->assertSame( 'no', $ok['clean']['online_renew'] );
		$this->assertNull( $ok['clean']['online_variation'], 'unanswered stays null' );
		$this->assertSame( 'yes', $ok['clean']['post_required'] );

		$bad = GCO_PD_Validator::validate_response( array( 'online_grant' => 'sometimes' ) );
		$this->assertArrayHasKey( 'online_grant', $bad['errors'] );
	}

	public function test_service_standards_take_their_status_from_the_number(): void {
		$r = GCO_PD_Validator::validate_response( array( 'std_grant_days' => 56, 'std_grant_pct' => '82%', 'std_renew_days' => '', 'std_variation_pct' => 0.9 ) );
		$this->assertSame( array(), $r['errors'] );
		$this->assertSame( 56, $r['clean']['std_grant_days'] );
		$this->assertSame( 'actual', $r['clean']['std_grant_days_status'] );
		$this->assertSame( 82.0, $r['clean']['std_grant_pct'] );
		$this->assertNull( $r['clean']['std_renew_days'] );
		$this->assertSame( 'not_provided', $r['clean']['std_renew_days_status'] );
		$this->assertSame( 90.0, $r['clean']['std_variation_pct'], 'a fraction is read as a percentage' );
		$this->assertArrayHasKey( 'std_variation_pct', $r['warnings'] );

		$over = GCO_PD_Validator::validate_response( array( 'std_grant_pct' => 140 ) );
		$this->assertArrayHasKey( 'std_grant_pct', $over['errors'] );
		$zero = GCO_PD_Validator::validate_response( array( 'std_grant_pct' => '0' ) );
		$this->assertSame( 0.0, $zero['clean']['std_grant_pct'], '0% is a real figure' );
		$this->assertSame( 'actual', $zero['clean']['std_grant_pct_status'] );
	}

	public function test_old_single_standard_fields_are_gone_from_validation(): void {
		$r = GCO_PD_Validator::validate_response( array( 'standard_text' => 'x', 'standard_days' => 84 ) );
		$this->assertArrayNotHasKey( 'standard_days', $r['clean'] );
		$this->assertArrayNotHasKey( 'standard_text', $r['clean'] );
	}

	public function test_type_rows_follow_the_same_value_and_status_rule(): void {
		$ok = GCO_PD_Validator::validate_type_row( array( 'received' => 1000, 'received_status' => 'Actual', 'completed' => 900, 'completed_status' => 'Estimate', 'outstanding' => '', 'outstanding_status' => '' ) );
		$this->assertSame( array(), $ok['errors'] );
		$this->assertSame( 'estimate', $ok['clean']['completed_status'] );
		$this->assertNull( $ok['clean']['outstanding'] );
		$this->assertSame( 'not_provided', $ok['clean']['outstanding_status'] );

		$bad = GCO_PD_Validator::validate_type_row( array( 'received' => 10, 'received_status' => 'Not provided' ) );
		$this->assertArrayHasKey( 'received', $bad['errors'] );
		$missing = GCO_PD_Validator::validate_type_row( array( 'received' => '', 'received_status' => 'Actual' ) );
		$this->assertArrayHasKey( 'received', $missing['errors'] );
		$decimal = GCO_PD_Validator::validate_type_row( array( 'outstanding' => '12.5', 'outstanding_status' => 'Actual' ) );
		$this->assertArrayHasKey( 'outstanding', $decimal['errors'] );
	}

	/* ------------------------------------------------------------------ Parser */

	private function sheet( array $header, array $rows ): array {
		$out = array( array( 'row' => 1, 'cells' => $header ) );
		foreach ( $rows as $i => $cells ) {
			$out[] = array( 'row' => $i + 2, 'cells' => $cells );
		}
		return $out;
	}

	public function test_the_types_sheet_is_detected_and_parsed(): void {
		$parsed = GCO_PD_Import_Parser::parse_sheet(
			$this->sheet(
				array_values( GCO_PD_Import_Parser::TYPE_HEADERS ),
				array( array( '2026-12', 'Kent Police', 'New Shotgun Grant', 1000, 'Actual', 900, 'Actual', 120, 'Estimate' ) )
			)
		);
		$this->assertSame( 'foi_types', $parsed['type'] );
		$this->assertSame( array(), $parsed['errors'] );
		$this->assertCount( 1, $parsed['rows'] );
		$row = $parsed['rows'][0];
		$this->assertSame( '2026-12', $row['period'] );
		$this->assertSame( 'Kent Police', $row['force'] );
		$this->assertSame( 'New Shotgun Grant', $row['data']['app_type'] );
		$this->assertSame( 1000, $row['data']['received'] );
		$this->assertSame( 'Estimate', $row['data']['outstanding_status'] );
	}

	public function test_the_main_sheet_is_still_the_main_sheet_and_reads_the_new_columns(): void {
		$headers = array_values( GCO_PD_Import_Parser::FOI_HEADERS );
		$h       = GCO_PD_Import_Parser::FOI_HEADERS;
		$cells   = array();
		foreach ( $headers as $label ) {
			$cells[ $label ] = '';
		}
		$cells[ $h['round'] ]         = '2026-12';
		$cells[ $h['force'] ]         = 'Kent Police';
		$cells[ $h['std_grant_days'] ] = 56;
		$cells[ $h['std_grant_pct'] ]  = 0.82;
		$cells[ $h['online_grant'] ]   = 'Yes';
		$cells[ $h['post_required'] ]  = 'No';
		$parsed = GCO_PD_Import_Parser::parse_sheet( $this->sheet( $headers, array( array_values( $cells ) ) ) );
		$this->assertSame( 'foi', $parsed['type'] );
		$this->assertSame( array(), $parsed['errors'] );
		$this->assertSame( 56, $parsed['rows'][0]['data']['std_grant_days'] );
		$this->assertSame( 'Yes', $parsed['rows'][0]['data']['online_grant'] );
	}

	public function test_client_service_standard_headers_are_used_exactly(): void {
		$h = GCO_PD_Import_Parser::FOI_HEADERS;
		$this->assertSame( 'New Grants - Target Processing Time (Days)', $h['std_grant_days'] );
		$this->assertSame( 'New Grants - Completed Within Target (%)', $h['std_grant_pct'] );
		$this->assertSame( 'Renewals - Target Processing Time (Days)', $h['std_renew_days'] );
		$this->assertSame( 'Renewals - Completed Within Target (%)', $h['std_renew_pct'] );
		$this->assertSame( 'Variations - Target Processing Time (Days)', $h['std_variation_days'] );
		$this->assertSame( 'Variations - Completed Within Target (%)', $h['std_variation_pct'] );
		$this->assertArrayNotHasKey( 'standard_days', $h, 'the single service standard is replaced' );
	}

	public function test_an_older_sheet_without_the_new_columns_still_parses(): void {
		$old = array( 'Round', 'Police Force', 'Shotgun Grant (days)', 'Shotgun Grant Status' );
		$h   = GCO_PD_Import_Parser::FOI_HEADERS;
		// Required columns only: every other header is optional, so the missing ones are notices, not errors.
		$required = array();
		foreach ( $h as $field => $label ) {
			if ( ! in_array( $field, GCO_PD_Import_Parser::FOI_OPTIONAL, true ) ) {
				$required[] = $label;
			}
		}
		$cells = array_map(
			static function ( $label ) use ( $h ) {
				return $label === $h['round'] ? '2026-06' : ( $label === $h['force'] ? 'Derbyshire' : '' );
			},
			$required
		);
		$parsed = GCO_PD_Import_Parser::parse_sheet( $this->sheet( $required, array( $cells ) ) );
		$this->assertSame( 'foi', $parsed['type'] );
		$this->assertSame( array(), $parsed['errors'] );
		$this->assertNotEmpty( $parsed['notices'] );
		unset( $old );
	}

	/* -------------------------------------------------------------- Calculator */

	public function test_types_block_has_figures_and_change_since_the_previous_round(): void {
		$snapshot = GCO_PD_Calculator::build(
			array(
				'forces'    => array( $this->force( 1, 'alpha' ) ),
				'rounds'    => array(
					array( 'id' => 1, 'label' => '2026-06', 'published_at' => null ),
					array( 'id' => 2, 'label' => '2026-12', 'published_at' => null ),
				),
				'responses' => array( $this->response( 1, 1 ), $this->response( 2, 1 ) ),
				'types'     => array(
					$this->type_row( 1, 1, 'sg_grant', array( 'outstanding' => 300, 'outstanding_status' => 'Actual', 'received' => 1000, 'received_status' => 'Actual' ) ),
					$this->type_row( 2, 1, 'sg_grant', array( 'outstanding' => 380, 'outstanding_status' => 'Actual', 'received' => 1100, 'received_status' => 'Estimate' ) ),
					$this->type_row( 2, 1, 'variation', array( 'received' => 40, 'received_status' => 'Actual' ) ),
				),
			)
		);
		$types = $snapshot['forces']['alpha']['foi']['extra']['types'];
		$this->assertSame( 380, $types['sg_grant']['outstanding']['value'] );
		$this->assertSame( 80, $types['sg_grant']['outstanding']['change'] );
		$this->assertSame( 'up', $types['sg_grant']['outstanding']['direction'] );
		$this->assertSame( 'estimate', $types['sg_grant']['received']['status'] );
		$this->assertSame( 100, $types['sg_grant']['received']['change'] );
		$this->assertNull( $types['sg_grant']['completed']['value'], 'not given: never 0' );
		$this->assertSame( 40, $types['variation']['received']['value'] );
		$this->assertNull( $types['variation']['received']['change'], 'no previous figure to compare' );
		$this->assertArrayNotHasKey( 'fac_grant', $types, 'a type the force did not give has no entry' );
	}

	public function test_national_type_totals_count_a_shared_department_once(): void {
		$snapshot = GCO_PD_Calculator::build(
			array(
				'forces'    => array( $this->force( 1, 'alpha', 'g' ), $this->force( 2, 'bravo', 'g' ), $this->force( 3, 'charlie' ) ),
				'rounds'    => array( array( 'id' => 1, 'label' => '2026-12', 'published_at' => null ) ),
				'responses' => array( $this->response( 1, 1 ), $this->response( 1, 2 ), $this->response( 1, 3 ) ),
				'types'     => array(
					$this->type_row( 1, 1, 'sg_grant', array( 'received' => 500, 'received_status' => 'Actual' ) ),
					$this->type_row( 1, 2, 'sg_grant', array( 'received' => 500, 'received_status' => 'Actual' ) ), // combined figure repeated
					$this->type_row( 1, 3, 'sg_grant', array( 'received' => 200, 'received_status' => 'Actual' ) ),
				),
			)
		);
		$received = $snapshot['foi']['types']['sg_grant']['received'];
		$this->assertSame( 700, $received['sum'], '500 once + 200, not 1,200' );
		$this->assertSame( 2, $received['n'] );
		$this->assertSame( 3, $received['n_forces'] );
		$this->assertSame( 1, $received['merged'] );
		$this->assertNull( $snapshot['foi']['types']['fac_grant']['received']['sum'], 'nobody answered: null, not 0' );
	}

	public function test_online_and_post_counts_never_treat_blank_as_no(): void {
		$snapshot = GCO_PD_Calculator::build(
			array(
				'forces'    => array( $this->force( 1, 'alpha' ), $this->force( 2, 'bravo' ), $this->force( 3, 'charlie' ) ),
				'rounds'    => array( array( 'id' => 1, 'label' => '2026-12', 'published_at' => null ) ),
				'responses' => array(
					$this->response( 1, 1, array( 'online_grant' => 'Yes', 'post_required' => 'No' ) ),
					$this->response( 1, 2, array( 'online_grant' => 'No', 'post_required' => 'Yes' ) ),
					$this->response( 1, 3 ),
				),
			)
		);
		$online = $snapshot['foi']['methods']['online_grant'];
		$this->assertSame( 1, $online['yes'] );
		$this->assertSame( 1, $online['no'] );
		$this->assertSame( 1, $online['unknown'], 'a force that did not answer is "unknown", not "no"' );
		$this->assertSame( 1, $snapshot['foi']['methods']['post_required']['yes'] );
		$this->assertSame( 'yes', $snapshot['forces']['alpha']['foi']['extra']['methods']['online_grant'] );
		$this->assertNull( $snapshot['forces']['charlie']['foi']['extra'], 'nothing extra given: no block' );
	}

	public function test_service_standards_reach_the_snapshot_per_category(): void {
		$snapshot = GCO_PD_Calculator::build(
			array(
				'forces'    => array( $this->force( 1, 'alpha' ) ),
				'rounds'    => array( array( 'id' => 1, 'label' => '2026-12', 'published_at' => null ) ),
				'responses' => array( $this->response( 1, 1, array( 'std_grant_days' => 56, 'std_grant_pct' => 88, 'std_renew_days' => 28, 'std_variation_days' => 14 ) ) ),
			)
		);
		$m = $snapshot['forces']['alpha']['foi']['extra']['metrics'];
		$this->assertSame( 56, $m['std_grant_days']['value'] );
		$this->assertSame( 88.0, $m['std_grant_pct']['value'] );
		$this->assertSame( 28, $m['std_renew_days']['value'] );
		$this->assertNull( $m['std_renew_pct']['value'], 'not recorded' );
		$this->assertSame( 14, $m['std_variation_days']['value'] );
		$this->assertSame( 1, $snapshot['foi']['extra']['std_grant_days']['n'] );
	}
}
