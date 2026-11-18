<?php
/**
 * Unit tests for shared departments (no double counting) and the extra FOI figures
 * (applications, outstanding, backlog age, service standards, percentages).
 *
 * @package GCO_Police_Data
 */

use PHPUnit\Framework\TestCase;

/**
 * Class SharedAndExtrasTest
 */
final class SharedAndExtrasTest extends TestCase {

	/**
	 * Force fixture with an optional shared department group.
	 *
	 * @param int    $id    Id.
	 * @param string $slug  Slug.
	 * @param string $group Shared group ('' for none).
	 * @return array
	 */
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
	 * Response row with the seven main metrics and the extras taken from $extra.
	 *
	 * @param int   $round_id Round id.
	 * @param int   $force_id Force id.
	 * @param array $metrics  key => array( value, status ).
	 * @param array $extra    Raw extra columns.
	 * @return array
	 */
	private function response( int $round_id, int $force_id, array $metrics, array $extra = array() ): array {
		$row = array(
			'round_id'      => $round_id,
			'force_id'      => $force_id,
			'response_date' => '2026-12-10',
			'pdf_id'        => null,
		);
		foreach ( GCO_PD_Validator::FOI_METRICS as $key => $meta ) {
			$row[ $meta['column'] ] = $metrics[ $key ][0] ?? null;
			$row[ $meta['status'] ] = $metrics[ $key ][1] ?? 'not_provided';
		}
		// Extras go through the validator exactly as an import would, so the stored shape is realistic.
		$clean = GCO_PD_Validator::validate_response( array_merge( $row, $extra ) );
		return array_merge( $row, $clean['clean'] );
	}

	/**
	 * NPCC row.
	 *
	 * @param int $quarter_id Quarter id.
	 * @param int $force_id   Force id.
	 * @param int $holders    Holders.
	 * @param int $received   Received.
	 * @param int $completed  Completed.
	 * @param int $in_sla     Completed within SLA.
	 * @return array
	 */
	private function npcc( int $quarter_id, int $force_id, int $holders, int $received, int $completed, int $in_sla ): array {
		return array(
			'quarter_id'         => $quarter_id,
			'force_id'           => $force_id,
			'total_holders'      => $holders,
			'total_certificates' => $holders + 10,
			'apps_received_12m'  => $received,
			'apps_completed_12m' => $completed,
			'completed_in_sla'   => $in_sla,
			'temporary_permits'  => null,
		);
	}

	/* ----------------------------------------------------- Shared departments (NPCC) */

	public function test_shared_department_counted_once_in_national_npcc_totals(): void {
		$snapshot = GCO_PD_Calculator::build(
			array(
				'forces'   => array(
					$this->force( 1, 'alpha', 'alpha-bravo' ),
					$this->force( 2, 'bravo', 'alpha-bravo' ),
					$this->force( 3, 'charlie' ),
				),
				'quarters' => array( array( 'id' => 1, 'label' => '2025/26 Q4', 'data_date' => '2026-03-23' ) ),
				'npcc'     => array(
					// One combined figure entered against both forces of the shared department.
					$this->npcc( 1, 1, 1000, 400, 300, 240 ),
					$this->npcc( 1, 2, 1000, 400, 300, 240 ),
					$this->npcc( 1, 3, 500, 100, 100, 50 ),
				),
			)
		);
		$national = $snapshot['npcc']['national'];
		$this->assertSame( 1500, $national['holders'], 'holders counted once for the shared department' );
		$this->assertSame( 500, $national['received'] );
		$this->assertSame( 400, $national['completed'] );
		$this->assertSame( 290, $national['in_sla'] );
		$this->assertEqualsWithDelta( 290 / 400, $national['pct'], 0.000001, 'the national % uses each department once' );
		$this->assertSame( 1, $national['merged'] );
		$this->assertSame( 3, $national['forces_in_report'], 'forces are still all reported' );
	}

	public function test_both_forces_of_a_shared_department_still_appear_on_the_leaderboard(): void {
		$snapshot = GCO_PD_Calculator::build(
			array(
				'forces'   => array( $this->force( 1, 'alpha', 'g' ), $this->force( 2, 'bravo', 'g' ) ),
				'quarters' => array( array( 'id' => 1, 'label' => '2025/26 Q4', 'data_date' => null ) ),
				'npcc'     => array( $this->npcc( 1, 1, 10, 10, 10, 9 ), $this->npcc( 1, 2, 10, 10, 10, 9 ) ),
			)
		);
		$this->assertCount( 2, $snapshot['npcc']['leaderboard'] );
		$this->assertSame( 1, $snapshot['forces']['alpha']['npcc']['rank'] );
		$this->assertSame( 1, $snapshot['forces']['bravo']['npcc']['rank'], 'identical figures share a rank' );
	}

	public function test_different_figures_in_a_shared_group_are_treated_as_separate(): void {
		$snapshot = GCO_PD_Calculator::build(
			array(
				'forces'   => array( $this->force( 1, 'alpha', 'g' ), $this->force( 2, 'bravo', 'g' ) ),
				'quarters' => array( array( 'id' => 1, 'label' => '2025/26 Q4', 'data_date' => null ) ),
				'npcc'     => array( $this->npcc( 1, 1, 1000, 10, 10, 9 ), $this->npcc( 1, 2, 600, 10, 10, 9 ) ),
			)
		);
		$national = $snapshot['npcc']['national'];
		$this->assertSame( 1600, $national['holders'], 'different figures are separate, so both are counted' );
		$this->assertSame( 10, $national['received'], 'the received figure is identical across the group, so it counts once' );
	}

	public function test_no_groups_changes_nothing(): void {
		$snapshot = GCO_PD_Calculator::build(
			array(
				'forces'   => array( $this->force( 1, 'alpha' ), $this->force( 2, 'bravo' ) ),
				'quarters' => array( array( 'id' => 1, 'label' => '2025/26 Q4', 'data_date' => null ) ),
				'npcc'     => array( $this->npcc( 1, 1, 1000, 10, 10, 9 ), $this->npcc( 1, 2, 1000, 10, 10, 9 ) ),
			)
		);
		$this->assertSame( 2000, $snapshot['npcc']['national']['holders'] );
		$this->assertSame( 0, $snapshot['npcc']['national']['merged'] );
	}

	/* ------------------------------------------------------- Shared departments (FOI) */

	public function test_shared_department_counted_once_in_foi_averages(): void {
		$snapshot = GCO_PD_Calculator::build(
			array(
				'forces'    => array(
					$this->force( 1, 'alpha', 'ab' ),
					$this->force( 2, 'bravo', 'ab' ),
					$this->force( 3, 'charlie' ),
				),
				'rounds'    => array( array( 'id' => 1, 'label' => '2026-12', 'published_at' => null ) ),
				'responses' => array(
					$this->response( 1, 1, array( 'sg_grant' => array( 60, 'actual' ) ) ),
					$this->response( 1, 2, array( 'sg_grant' => array( 60, 'actual' ) ) ),
					$this->response( 1, 3, array( 'sg_grant' => array( 30, 'actual' ) ) ),
				),
			)
		);
		$metric = $snapshot['foi']['metrics']['sg_grant'];
		$this->assertEqualsWithDelta( 45.0, $metric['average'], 0.01, '(60 + 30) / 2 departments, not (60 + 60 + 30) / 3' );
		$this->assertSame( 2, $metric['n'] );
		$this->assertSame( 3, $metric['n_forces'] );
		$this->assertSame( 1, $metric['merged'] );
		// Each force of the shared department still has its own rank entry.
		$this->assertSame( 2, $snapshot['forces']['alpha']['foi']['metrics']['sg_grant']['rank'] );
		$this->assertSame( 2, $snapshot['forces']['bravo']['foi']['metrics']['sg_grant']['rank'] );
	}

	public function test_forces_know_who_they_share_a_department_with(): void {
		$snapshot = GCO_PD_Calculator::build(
			array( 'forces' => array( $this->force( 1, 'alpha', 'ab' ), $this->force( 2, 'bravo', 'ab' ), $this->force( 3, 'charlie', 'solo' ) ) )
		);
		$this->assertSame( 'ab', $snapshot['forces']['alpha']['shared']['group'] );
		$this->assertSame( 'bravo', $snapshot['forces']['alpha']['shared']['with'][0]['slug'] );
		$this->assertNull( $snapshot['forces']['charlie']['shared'], 'a group of one is not a shared department' );
	}

	/* ------------------------------------------------------------ Extra figures */

	public function test_extra_figures_reach_the_snapshot_with_change_since_the_previous_round(): void {
		$snapshot = GCO_PD_Calculator::build(
			array(
				'forces'    => array( $this->force( 1, 'alpha' ) ),
				'rounds'    => array(
					array( 'id' => 1, 'label' => '2026-06', 'published_at' => null ),
					array( 'id' => 2, 'label' => '2026-12', 'published_at' => null ),
				),
				'responses' => array(
					$this->response( 1, 1, array( 'sg_grant' => array( 40, 'actual' ) ), array( 'outstanding' => 900, 'outstanding_status' => 'Actual' ) ),
					$this->response(
						2,
						1,
						array( 'sg_grant' => array( 35, 'actual' ) ),
						array(
							'outstanding'            => 1000,
							'outstanding_status'     => 'Actual',
							'apps_received'          => 2500,
							'apps_received_status'   => 'Estimate',
							'std_grant_days'         => 56,
							'std_grant_pct'          => '82%',
							'oldest_days'            => 410,
							'oldest_days_status'     => 'Actual',
						)
					),
				),
			)
		);
		$extra = $snapshot['forces']['alpha']['foi']['extra'];
		$this->assertSame( 1000, $extra['metrics']['outstanding']['value'] );
		$this->assertSame( 100, $extra['metrics']['outstanding']['change'] );
		$this->assertSame( 'up', $extra['metrics']['outstanding']['direction'] );
		$this->assertSame( 'estimate', $extra['metrics']['apps_received']['status'] );
		$this->assertNull( $extra['metrics']['apps_received']['change'], 'no change without the previous figure' );
		$this->assertSame( 82.0, $extra['metrics']['std_grant_pct']['value'] );
		$this->assertSame( 56, $extra['metrics']['std_grant_days']['value'] );
		$this->assertSame( 'actual', $extra['metrics']['std_grant_days']['status'], 'a service-standard number is Actual by itself' );
		$this->assertNull( $extra['backlog'] );
	}

	public function test_a_force_that_gave_no_extra_figures_has_no_extra_block_and_older_rounds_are_unaffected(): void {
		$snapshot = GCO_PD_Calculator::build(
			array(
				'forces'    => array( $this->force( 1, 'alpha' ) ),
				'rounds'    => array( array( 'id' => 1, 'label' => '2026-06', 'published_at' => null ) ),
				'responses' => array( $this->response( 1, 1, array( 'sg_grant' => array( 40, 'actual' ) ) ) ),
			)
		);
		$this->assertNull( $snapshot['forces']['alpha']['foi']['extra'] );
		$this->assertSame( 0, $snapshot['foi']['extra']['outstanding']['n'] );
		$this->assertNull( $snapshot['foi']['extra']['outstanding']['sum'], 'no figures means null, never 0' );
		// The coverage counts still cover only the seven original figures.
		$this->assertSame( 7, array_sum( $snapshot['forces']['alpha']['foi']['coverage'] ) );
	}

	public function test_backlog_counts_can_be_totalled_but_percentages_never_are(): void {
		$counts = GCO_PD_Calculator::build(
			array(
				'forces'    => array( $this->force( 1, 'alpha' ) ),
				'rounds'    => array( array( 'id' => 1, 'label' => '2026-12', 'published_at' => null ) ),
				'responses' => array(
					$this->response(
						1,
						1,
						array(),
						array(
							'backlog_m0_3' => 700, 'backlog_m3_6' => 300, 'backlog_m6_12' => 150, 'backlog_m12_plus' => 50,
							'backlog_status' => 'Actual',
						)
					),
				),
			)
		);
		$backlog = $counts['forces']['alpha']['foi']['extra']['backlog'];
		$this->assertSame( 'count', $backlog['unit'] );
		$this->assertSame( 1200, $backlog['total'] );
		$this->assertSame( 700, $backlog['bands'][0]['value'] );

		$percent = GCO_PD_Calculator::build(
			array(
				'forces'    => array( $this->force( 1, 'alpha' ) ),
				'rounds'    => array( array( 'id' => 1, 'label' => '2026-12', 'published_at' => null ) ),
				'responses' => array(
					$this->response(
						1,
						1,
						array(),
						array(
							'backlog_unit' => 'Percentage', 'backlog_m0_3' => 60, 'backlog_m3_6' => 25.5, 'backlog_m6_12' => 10, 'backlog_m12_plus' => 4.5,
							'backlog_status' => 'Estimate',
						)
					),
				),
			)
		);
		$backlog = $percent['forces']['alpha']['foi']['extra']['backlog'];
		$this->assertSame( 'percent', $backlog['unit'] );
		$this->assertNull( $backlog['total'], 'percentages are shown as given, never summed into counts' );
		$this->assertSame( 25.5, $backlog['bands'][1]['value'] );
	}

	public function test_national_outstanding_counts_a_shared_department_once(): void {
		$snapshot = GCO_PD_Calculator::build(
			array(
				'forces'    => array( $this->force( 1, 'alpha', 'ab' ), $this->force( 2, 'bravo', 'ab' ), $this->force( 3, 'charlie' ) ),
				'rounds'    => array( array( 'id' => 1, 'label' => '2026-12', 'published_at' => null ) ),
				'responses' => array(
					$this->response( 1, 1, array(), array( 'outstanding' => 800, 'outstanding_status' => 'Actual' ) ),
					$this->response( 1, 2, array(), array( 'outstanding' => 800, 'outstanding_status' => 'Actual' ) ),
					$this->response( 1, 3, array(), array( 'outstanding' => 200, 'outstanding_status' => 'Estimate' ) ),
				),
			)
		);
		$outstanding = $snapshot['foi']['extra']['outstanding'];
		$this->assertSame( 1000, $outstanding['sum'], '800 once + 200, not 1,800' );
		$this->assertSame( 2, $outstanding['n'] );
		$this->assertSame( 3, $outstanding['n_forces'] );
		$this->assertSame( 1, $outstanding['merged'] );
		$this->assertSame( 1, $outstanding['estimates'] );
		$this->assertSame( 800, $outstanding['highest']['value'] );
	}

	/* ------------------------------------------------------- Validator, parser, helpers */

	public function test_percent_parsing(): void {
		$this->assertSame( 85.0, GCO_PD_Validator::parse_percent( '85%' )['value'] );
		$this->assertSame( 85.0, GCO_PD_Validator::parse_percent( 0.85 )['value'] );
		$this->assertTrue( GCO_PD_Validator::parse_percent( 0.85 )['converted'] );
		$this->assertSame( 0.5, GCO_PD_Validator::parse_percent( '0.5%' )['value'], 'a typed % sign is never rescaled' );
		$this->assertSame( 0.0, GCO_PD_Validator::parse_percent( '0' )['value'] );
		$this->assertNull( GCO_PD_Validator::parse_percent( '' )['value'] );
		$this->assertFalse( GCO_PD_Validator::parse_percent( 120 )['value'] );
		$this->assertFalse( GCO_PD_Validator::parse_percent( 'about half' )['value'] );
	}

	public function test_unit_normalisation(): void {
		$this->assertSame( 'count', GCO_PD_Validator::normalize_unit( '' ) );
		$this->assertSame( 'count', GCO_PD_Validator::normalize_unit( 'Count' ) );
		$this->assertSame( 'percent', GCO_PD_Validator::normalize_unit( 'Percentage' ) );
		$this->assertSame( 'percent', GCO_PD_Validator::normalize_unit( '%' ) );
		$this->assertFalse( GCO_PD_Validator::normalize_unit( 'weeks' ) );
	}

	public function test_validator_backlog_rules(): void {
		$base = array( 'sg_grant_days' => 40, 'sg_grant_status' => 'Actual' );

		$ok = GCO_PD_Validator::validate_response( $base + array( 'backlog_unit' => 'Percentage', 'backlog_m0_3' => '55', 'backlog_m3_6' => '25.5', 'backlog_m6_12' => '15', 'backlog_m12_plus' => '4.5', 'backlog_status' => 'Estimate' ) );
		$this->assertSame( array(), $ok['errors'] );
		$this->assertSame( 25.5, $ok['clean']['backlog_m3_6'] );
		$this->assertSame( 'percent', $ok['clean']['backlog_unit'] );

		$fractions = GCO_PD_Validator::validate_response( $base + array( 'backlog_unit' => '%', 'backlog_m0_3' => 0.55, 'backlog_m3_6' => 0.255, 'backlog_m6_12' => 0.15, 'backlog_m12_plus' => 0.045, 'backlog_status' => 'Actual' ) );
		$this->assertSame( array(), $fractions['errors'] );
		$this->assertEqualsWithDelta( 55.0, $fractions['clean']['backlog_m0_3'], 0.001 );
		$this->assertArrayHasKey( 'backlog_unit', $fractions['warnings'] );

		$off = GCO_PD_Validator::validate_response( $base + array( 'backlog_unit' => 'Percentage', 'backlog_m0_3' => 60, 'backlog_m3_6' => 20, 'backlog_status' => 'Actual' ) );
		$this->assertArrayHasKey( 'backlog_status', $off['warnings'] );

		$decimal = GCO_PD_Validator::validate_response( $base + array( 'backlog_m0_3' => '12.5', 'backlog_status' => 'Actual' ) );
		$this->assertArrayHasKey( 'backlog_m0_3', $decimal['errors'] );
		$this->assertStringContainsString( 'Percentage', $decimal['errors']['backlog_m0_3'] );

		$mismatch = GCO_PD_Validator::validate_response( $base + array( 'backlog_m0_3' => 5 ) );
		$this->assertArrayHasKey( 'backlog_status', $mismatch['errors'] );
		$none = GCO_PD_Validator::validate_response( $base + array( 'backlog_status' => 'Actual' ) );
		$this->assertArrayHasKey( 'backlog_status', $none['errors'] );

		$sum = GCO_PD_Validator::validate_response( $base + array( 'outstanding' => 1000, 'outstanding_status' => 'Actual', 'backlog_m0_3' => 700, 'backlog_m3_6' => 300, 'backlog_m6_12' => 150, 'backlog_m12_plus' => 50, 'backlog_status' => 'Actual' ) );
		$this->assertSame( array(), $sum['errors'] );
		$this->assertArrayHasKey( 'backlog_status', $sum['warnings'], 'bands that do not add up to outstanding warn but do not block' );
	}

	public function test_old_style_rows_stay_valid_and_extras_default_to_not_provided(): void {
		$result = GCO_PD_Validator::validate_response( array( 'sg_grant_days' => 40, 'sg_grant_status' => 'Actual' ) );
		$this->assertSame( array(), $result['errors'] );
		$this->assertNull( $result['clean']['outstanding'] );
		$this->assertSame( 'not_provided', $result['clean']['outstanding_status'] );
		$this->assertSame( 'count', $result['clean']['backlog_unit'] );
		$this->assertSame( 'not_provided', $result['clean']['backlog_status'] );
		$this->assertNull( $result['clean']['std_grant_pct'] );
	}

	public function test_new_headers_are_optional_so_old_spreadsheets_still_import(): void {
		foreach ( array( 'apps_received', 'outstanding', 'backlog_unit', 'backlog_m12_plus', 'std_grant_pct', 'online_grant', 'post_required' ) as $field ) {
			$this->assertContains( $field, GCO_PD_Import_Parser::FOI_OPTIONAL );
			$this->assertArrayHasKey( $field, GCO_PD_Import_Parser::FOI_HEADERS );
		}
		// Required columns are unchanged.
		$this->assertNotContains( 'sg_grant_days', GCO_PD_Import_Parser::FOI_OPTIONAL );
	}

	public function test_plain_number_export_tidies_decimals_only(): void {
		$this->assertSame( '55', GCO_PD_Exporter::plain_number( '55.00' ) );
		$this->assertSame( '25.5', GCO_PD_Exporter::plain_number( '25.50' ) );
		$this->assertSame( '1200', GCO_PD_Exporter::plain_number( '1200' ) );
		$this->assertNull( GCO_PD_Exporter::plain_number( null ) );
	}

	public function test_group_names_are_normalised(): void {
		$this->assertSame( 'warwickshire-west-mercia', GCO_PD_Repository::normalize_group( 'Warwickshire & West Mercia' ) );
		$this->assertSame( '', GCO_PD_Repository::normalize_group( '  ' ) );
	}
	public function test_bar_chart_can_skip_the_latest_highlight(): void {
		$items = array( 'Up to 3 months' => 700, 'Over 12 months' => 50 );
		$plain = GCO_PD_SVG_Chart::bars( $items, array( 'title' => 'Age', 'highlight_last' => false ) );
		$this->assertStringNotContainsString( '#8fd0a4', $plain, 'every bar uses the same colour' );
		$default = GCO_PD_SVG_Chart::bars( $items, array( 'title' => 'Age' ) );
		$this->assertStringContainsString( '#8fd0a4', $default, 'the default still marks the latest bar' );
		$percent = GCO_PD_SVG_Chart::bars( array( 'A' => 55 ), array( 'unit' => '%' ) );
		$this->assertStringContainsString( '55%', $percent );
	}
}
