<?php
/**
 * Unit tests for GCO_PD_Calculator (pure: fixtures in, snapshot out).
 *
 * @package GCO_Police_Data
 */

use PHPUnit\Framework\TestCase;

/**
 * Class CalculatorTest
 */
final class CalculatorTest extends TestCase {

	/**
	 * Builds a force fixture.
	 *
	 * @param int    $id     Id.
	 * @param string $slug   Slug.
	 * @param string $name   Name.
	 * @param int    $active Active flag.
	 * @return array
	 */
	private function force( int $id, string $slug, string $name, int $active = 1 ): array {
		return array(
			'id'        => $id,
			'slug'      => $slug,
			'name'      => $name,
			'country'   => 'England',
			'aliases'   => 'x, y',
			'page_id'   => null,
			'is_active' => $active,
		);
	}

	/**
	 * Builds a response row. $metrics maps a metric key to array( value, status ); others are not provided.
	 *
	 * @param int   $round_id Round id.
	 * @param int   $force_id Force id.
	 * @param array $metrics  Metrics.
	 * @return array
	 */
	private function response( int $round_id, int $force_id, array $metrics ): array {
		$row = array(
			'round_id'      => $round_id,
			'force_id'      => $force_id,
			'response_date' => '2026-06-11',
			'pdf_id'        => null,
		);
		foreach ( GCO_PD_Validator::FOI_METRICS as $key => $meta ) {
			$row[ $meta['column'] ] = $metrics[ $key ][0] ?? null;
			$row[ $meta['status'] ] = $metrics[ $key ][1] ?? 'not_provided';
		}
		return $row;
	}

	/**
	 * Two rounds, four forces.
	 *
	 * @return array
	 */
	private function foi_input(): array {
		return array(
			'forces'    => array(
				$this->force( 1, 'alpha', 'Alpha Police' ),
				$this->force( 2, 'bravo', 'Bravo Police' ),
				$this->force( 3, 'charlie', 'Charlie Police' ),
				$this->force( 4, 'delta', 'Delta Police' ),
			),
			'rounds'    => array(
				array( 'id' => 10, 'label' => '2026-06', 'published_at' => '2026-07-01 10:00:00' ),
				array( 'id' => 11, 'label' => '2026-12', 'published_at' => '2027-01-05 10:00:00' ),
			),
			'responses' => array(
				// Round 1 (June): Alpha 40, Bravo 30, Charlie 50.
				$this->response( 10, 1, array( 'sg_grant' => array( 40, 'actual' ), 'sg_holders' => array( 1000, 'actual' ) ) ),
				$this->response( 10, 2, array( 'sg_grant' => array( 30, 'actual' ) ) ),
				$this->response( 10, 3, array( 'sg_grant' => array( 50, 'estimate' ) ) ),
				// Round 2 (December): Alpha 32, Bravo 32 (tie), Charlie not provided. Delta absent in both.
				$this->response( 11, 1, array( 'sg_grant' => array( 32, 'actual' ), 'sg_holders' => array( 1100, 'actual' ) ) ),
				$this->response( 11, 2, array( 'sg_grant' => array( 32, 'estimate' ), 'fac_grant' => array( 0, 'actual' ) ) ),
				$this->response( 11, 3, array() ),
			),
			'quarters'  => array(),
			'npcc'      => array(),
		);
	}

	public function test_empty_input_gives_an_empty_snapshot_with_no_zeros(): void {
		$snapshot = GCO_PD_Calculator::build( array() );
		$this->assertSame( 0, $snapshot['total_forces'] );
		$this->assertNull( $snapshot['latest_round'] );
		$this->assertNull( $snapshot['npcc']['national'] );
		$this->assertSame( array(), $snapshot['npcc']['leaderboard'] );
	}

	public function test_ties_share_a_rank_and_the_next_rank_skips(): void {
		$input = $this->foi_input();
		$input['responses'][] = $this->response( 11, 4, array( 'sg_grant' => array( 45, 'actual' ) ) );
		$snap  = GCO_PD_Calculator::build( $input );

		$this->assertSame( 1, $snap['forces']['alpha']['foi']['metrics']['sg_grant']['rank'] );
		$this->assertSame( 1, $snap['forces']['bravo']['foi']['metrics']['sg_grant']['rank'] );
		$this->assertSame( 3, $snap['forces']['delta']['foi']['metrics']['sg_grant']['rank'] );
		$this->assertSame( 3, $snap['forces']['delta']['foi']['metrics']['sg_grant']['ranked_of'] );
	}

	public function test_not_provided_is_never_ranked_averaged_or_counted_as_zero(): void {
		$snap = GCO_PD_Calculator::build( $this->foi_input() );

		$charlie = $snap['forces']['charlie']['foi']['metrics']['sg_grant'];
		$this->assertNull( $charlie['value'] );
		$this->assertNull( $charlie['rank'] );
		$this->assertSame( 'not_provided', $charlie['status'] );

		$national = $snap['foi']['metrics']['sg_grant'];
		$this->assertSame( 2, $national['n'], 'Only Alpha and Bravo have a December figure.' );
		$this->assertSame( 32.0, (float) $national['average'] );
		$this->assertSame( 32, $national['min'] );
		$this->assertSame( 1, $national['estimates'] );

		// Nobody gave a variation figure: no average, not 0.
		$none = $snap['foi']['metrics']['variation'];
		$this->assertSame( 0, $none['n'] );
		$this->assertNull( $none['average'] );
		$this->assertNull( $none['median'] );
	}

	public function test_zero_days_is_a_real_figure_and_ranks_first(): void {
		$snap = GCO_PD_Calculator::build( $this->foi_input() );
		$this->assertSame( 0, $snap['forces']['bravo']['foi']['metrics']['fac_grant']['value'] );
		$this->assertSame( 1, $snap['forces']['bravo']['foi']['metrics']['fac_grant']['rank'] );
	}

	public function test_a_force_with_no_row_in_the_latest_round_keeps_its_last_figures_flagged_stale(): void {
		$input = $this->foi_input();
		// Remove Charlie's December row.
		$input['responses'] = array_values(
			array_filter(
				$input['responses'],
				static fn( $r ) => ! ( 11 === $r['round_id'] && 3 === $r['force_id'] )
			)
		);
		$snap    = GCO_PD_Calculator::build( $input );
		$charlie = $snap['forces']['charlie']['foi'];

		$this->assertTrue( $charlie['stale'] );
		$this->assertSame( '2026-06', $charlie['round'] );
		$this->assertSame( 50, $charlie['metrics']['sg_grant']['value'] );
		$this->assertFalse( $snap['forces']['alpha']['foi']['stale'] );
		$this->assertNull( $snap['forces']['delta']['foi'], 'A force that never answered has no FOI data.' );
	}

	public function test_change_vs_previous_round(): void {
		$snap = GCO_PD_Calculator::build( $this->foi_input() );

		$alpha = $snap['forces']['alpha']['foi']['metrics']['sg_grant'];
		$this->assertSame( -8, $alpha['change'] );
		$this->assertSame( 'down', $alpha['direction'] );

		$bravo = $snap['forces']['bravo']['foi']['metrics']['sg_grant'];
		$this->assertSame( 2, $bravo['change'] );
		$this->assertSame( 'up', $bravo['direction'] );

		// Charlie is not provided now: no change shown. Bravo's fac_grant had no earlier figure.
		$this->assertNull( $snap['forces']['charlie']['foi']['metrics']['sg_grant']['change'] );
		$this->assertNull( $snap['forces']['bravo']['foi']['metrics']['fac_grant']['change'] );
	}

	public function test_no_change_is_shown_when_the_previous_round_is_missing_for_that_force(): void {
		$input = $this->foi_input();
		$input['responses'][] = $this->response( 11, 4, array( 'sg_grant' => array( 45, 'actual' ) ) ); // Delta new in December.
		$snap = GCO_PD_Calculator::build( $input );
		$this->assertNull( $snap['forces']['delta']['foi']['metrics']['sg_grant']['change'] );
	}

	public function test_same_value_gives_a_same_direction(): void {
		$input = $this->foi_input();
		$input['responses'][] = $this->response( 10, 4, array( 'sg_grant' => array( 45, 'actual' ) ) );
		$input['responses'][] = $this->response( 11, 4, array( 'sg_grant' => array( 45, 'actual' ) ) );
		$snap = GCO_PD_Calculator::build( $input );
		$this->assertSame( 0, $snap['forces']['delta']['foi']['metrics']['sg_grant']['change'] );
		$this->assertSame( 'same', $snap['forces']['delta']['foi']['metrics']['sg_grant']['direction'] );
	}

	public function test_trend_lists_every_round_oldest_first(): void {
		$snap  = GCO_PD_Calculator::build( $this->foi_input() );
		$trend = $snap['forces']['alpha']['trend'];
		$this->assertSame( array( '2026-06', '2026-12' ), array_column( $trend, 'round' ) );
		$this->assertSame( 40, $trend[0]['metrics']['sg_grant']['value'] );
		$this->assertSame( 32, $trend[1]['metrics']['sg_grant']['value'] );
		$this->assertCount( 2, $snap['forces']['bravo']['trend'], 'Bravo answered both rounds.' );
		$this->assertCount( 0, $snap['forces']['delta']['trend'], 'Delta never answered.' );
	}

	public function test_glance_lists_and_reporting_counts(): void {
		$snap = GCO_PD_Calculator::build( $this->foi_input() );
		$this->assertSame( 2, $snap['foi']['reporting'], 'Charlie answered but gave no usable figure.' );

		$glance = $snap['foi']['rankings']['sg_grant'];
		$this->assertSame( 2, $glance['ranked'] );
		$this->assertSame( array( 'alpha', 'bravo' ), array_column( $glance['shortest'], 'slug' ) );
		$this->assertSame( 32, $glance['shortest'][0]['days'] );
		$this->assertSame( array( 'alpha', 'bravo' ), array_column( $glance['longest'], 'slug' ) );
	}

	public function test_holders_are_summed_but_never_ranked(): void {
		$snap = GCO_PD_Calculator::build( $this->foi_input() );
		$this->assertSame( 1100, $snap['foi']['holders']['sg_holders']['sum'] );
		$this->assertArrayNotHasKey( 'sg_holders', $snap['foi']['rankings'] );
		$this->assertNull( $snap['forces']['alpha']['foi']['metrics']['sg_holders']['rank'] );
	}

	public function test_median_even_and_odd(): void {
		$input = $this->foi_input();
		$input['responses'][] = $this->response( 11, 4, array( 'sg_grant' => array( 100, 'actual' ) ) );
		$snap = GCO_PD_Calculator::build( $input ); // 32, 32, 100.
		$this->assertSame( 32, $snap['foi']['metrics']['sg_grant']['median'] );

		$input['responses'][] = $this->response( 11, 3, array( 'sg_grant' => array( 60, 'actual' ) ) );
		$snap = GCO_PD_Calculator::build( $input ); // Charlie's December row is duplicated; the later wins.
		$this->assertContains( $snap['foi']['metrics']['sg_grant']['median'], array( 46, 46.0 ) );
	}

	public function test_inactive_forces_are_left_out_of_everything(): void {
		$input = $this->foi_input();
		$input['forces'][1]['is_active'] = 0; // Bravo.
		$snap = GCO_PD_Calculator::build( $input );
		$this->assertArrayNotHasKey( 'bravo', $snap['forces'] );
		$this->assertSame( 3, $snap['total_forces'] );
		$this->assertSame( 1, $snap['foi']['metrics']['sg_grant']['n'] );
	}

	public function test_coverage_counts(): void {
		$snap = GCO_PD_Calculator::build( $this->foi_input() );
		// December rows: Alpha 2 figures, Bravo 2 figures (one estimate), Charlie none. 3 forces x 7 metrics = 21.
		$this->assertSame( 3, $snap['foi']['coverage']['actual'] );
		$this->assertSame( 1, $snap['foi']['coverage']['estimate'] );
		$this->assertSame( 17, $snap['foi']['coverage']['not_provided'] );
		$this->assertSame( 21, array_sum( $snap['foi']['coverage'] ) );
	}

	public function test_a_value_without_a_usable_status_is_not_a_figure(): void {
		$input = $this->foi_input();
		$input['responses'][] = array_merge( $this->response( 11, 4, array() ), array( 'sg_grant_days' => 20, 'sg_grant_status' => 'not_provided' ) );
		$snap = GCO_PD_Calculator::build( $input );
		$this->assertNull( $snap['forces']['delta']['foi']['metrics']['sg_grant']['value'] );
	}

	/* ------------------------------------------------------------------- NPCC */

	/**
	 * NPCC row fixture.
	 *
	 * @param int       $quarter  Quarter id.
	 * @param int       $force    Force id.
	 * @param int|null  $done     Completed in 12 months.
	 * @param int|null  $in_sla   Completed within SLA.
	 * @param int|null  $holders  Holders.
	 * @return array
	 */
	private function npcc( int $quarter, int $force, ?int $done, ?int $in_sla, ?int $holders = 100 ): array {
		return array(
			'quarter_id'         => $quarter,
			'force_id'           => $force,
			'total_holders'      => $holders,
			'total_certificates' => null === $holders ? null : $holders + 10,
			'apps_received_12m'  => null === $done ? null : $done + 5,
			'apps_completed_12m' => $done,
			'completed_in_sla'   => $in_sla,
			'temporary_permits'  => null,
		);
	}

	/**
	 * Two quarters, five forces.
	 *
	 * @return array
	 */
	private function npcc_input(): array {
		return array(
			'forces'   => array(
				$this->force( 1, 'alpha', 'Alpha Police' ),
				$this->force( 2, 'bravo', 'Bravo Police' ),
				$this->force( 3, 'charlie', 'Charlie Police' ),
				$this->force( 4, 'delta', 'Delta Police' ),
				$this->force( 5, 'echo', 'Echo Police' ),
			),
			'quarters' => array(
				array( 'id' => 1, 'label' => '2025/26 Q3', 'data_date' => '2025-12-22' ),
				array( 'id' => 2, 'label' => '2025/26 Q4', 'data_date' => '2026-03-23' ),
			),
			'npcc'     => array(
				// Q3.
				$this->npcc( 1, 1, 100, 60 ),
				$this->npcc( 1, 2, 100, 80 ),
				// Q4: Alpha 90/100 = 90%, Bravo 80/100 = exactly 80%, Charlie 160/200 = 80% (more completed than Bravo),
				// Delta has no SLA figure, Echo has 0 completed.
				$this->npcc( 2, 1, 100, 90 ),
				$this->npcc( 2, 2, 100, 80 ),
				$this->npcc( 2, 3, 200, 160 ),
				$this->npcc( 2, 4, 50, null ),
				$this->npcc( 2, 5, 0, 0, null ),
			),
		);
	}

	public function test_leaderboard_order_ties_and_unranked(): void {
		$snap = GCO_PD_Calculator::build( $this->npcc_input() );
		$board = $snap['npcc']['leaderboard'];

		$this->assertSame( array( 'alpha', 'charlie', 'bravo' ), array_column( $board, 'slug' ), 'Tie on 80% goes to the force that completed more.' );
		$this->assertSame( array( 1, 2, 3 ), array_column( $board, 'rank' ) );
		$this->assertSame( array( 'delta', 'echo' ), array_column( $snap['npcc']['unranked'], 'slug' ), 'No % means no rank, never last place.' );
		$this->assertNull( $snap['forces']['delta']['npcc']['rank'] );
		$this->assertNull( $snap['forces']['delta']['npcc']['pct'] );
		$this->assertNull( $snap['forces']['echo']['npcc']['pct'], '0 completed is no percentage, not 0%.' );
	}

	public function test_identical_percentage_and_volume_share_a_rank(): void {
		$input = $this->npcc_input();
		$input['npcc'][] = $this->npcc( 2, 4, 100, 80 ); // Delta now matches Bravo exactly.
		$input['npcc']   = array_values( array_filter( $input['npcc'], static fn( $r ) => ! ( 2 === $r['quarter_id'] && 4 === $r['force_id'] && null === $r['completed_in_sla'] ) ) );
		$snap = GCO_PD_Calculator::build( $input );
		// Alpha 90%, Charlie 80% on 200, then Bravo and Delta both 80% on 100: they share rank 3.
		$this->assertSame( 3, $snap['forces']['bravo']['npcc']['rank'] );
		$this->assertSame( 3, $snap['forces']['delta']['npcc']['rank'] );
		$this->assertSame( 2, $snap['forces']['charlie']['npcc']['rank'] );
	}

	public function test_exactly_eighty_percent_meets_the_aspiration(): void {
		$snap = GCO_PD_Calculator::build( $this->npcc_input() );
		$this->assertTrue( $snap['forces']['bravo']['npcc']['meets_sla'] );
		$this->assertTrue( $snap['forces']['alpha']['npcc']['meets_sla'] );
		$this->assertSame( 3, $snap['npcc']['national']['forces_meeting'] );

		$input = $this->npcc_input();
		$input['npcc'][] = $this->npcc( 2, 3, 200, 159 ); // 79.5%.
		$input['npcc']   = array_values( array_filter( $input['npcc'], static fn( $r ) => ! ( 2 === $r['quarter_id'] && 3 === $r['force_id'] && 160 === $r['completed_in_sla'] ) ) );
		$this->assertFalse( GCO_PD_Calculator::build( $input )['forces']['charlie']['npcc']['meets_sla'] );
	}

	public function test_change_since_previous_quarter_in_percentage_points(): void {
		$snap = GCO_PD_Calculator::build( $this->npcc_input() );
		$this->assertSame( 30.0, $snap['forces']['alpha']['npcc']['change_pp'] ); // 60% to 90%.
		$this->assertSame( 0.0, $snap['forces']['bravo']['npcc']['change_pp'] );
		$this->assertNull( $snap['forces']['charlie']['npcc']['change_pp'], 'No previous figure, so no arrow.' );
	}

	public function test_national_percentage_uses_only_forces_with_both_figures(): void {
		$snap     = GCO_PD_Calculator::build( $this->npcc_input() );
		$national = $snap['npcc']['national'];

		// Ratio uses only forces that have BOTH figures: Alpha, Bravo, Charlie and Echo (0 of 0). Delta has no SLA figure.
		// (90 + 80 + 160 + 0) / (100 + 100 + 200 + 0) = 330 / 400.
		$this->assertSame( round( 330 / 400, 6 ), $national['pct'] );
		// Plain totals include every figure that exists: Delta's 50 completed counts here.
		$this->assertSame( 450, $national['completed'] );
		$this->assertSame( 5, $national['forces_in_report'] );
		$this->assertSame( 3, $national['forces_ranked'] );
	}

	public function test_national_sums_skip_nulls_and_stay_null_when_all_missing(): void {
		$snap = GCO_PD_Calculator::build( $this->npcc_input() );
		$this->assertSame( 400, $snap['npcc']['national']['holders'], 'Echo has no holders figure and adds nothing.' );
		$this->assertSame( 440, $snap['npcc']['national']['certificates'] );

		$input = $this->npcc_input();
		foreach ( $input['npcc'] as $i => $row ) {
			$input['npcc'][ $i ]['total_holders'] = null;
		}
		$this->assertNull( GCO_PD_Calculator::build( $input )['npcc']['national']['holders'], 'All missing is NULL, never 0.' );
	}

	public function test_only_published_quarters_given_are_used_and_latest_is_by_label(): void {
		$input = $this->npcc_input();
		$input['quarters'] = array_reverse( $input['quarters'] ); // Order in must not matter.
		$snap = GCO_PD_Calculator::build( $input );
		$this->assertSame( '2025/26 Q4', $snap['latest_quarter']['label'] );
		$this->assertSame( '2025/26 Q4', $snap['npcc']['national']['quarter'] );
	}

	/* ----------------------------------------------------- The real NPCC Q4 data */

	public function test_real_npcc_q4_matches_the_published_national_figures(): void {
		$path = GCO_PD_DIR . 'dev/reference/GCO-Police-FOI-Data-Template.xlsx';
		if ( ! is_file( $path ) || ! class_exists( 'ZipArchive' ) ) {
			$this->markTestSkipped( 'Template or zip extension not available.' );
		}
		$sheets = GCO_PD_Xlsx_Reader::read( $path );
		$parsed = GCO_PD_Import_Parser::parse_sheet( $sheets['NPCC Data'] );

		$forces  = array();
		$by_name = array();
		foreach ( require GCO_PD_DIR . 'includes/data/forces.php' as $i => $force ) {
			$forces[]                   = array_merge( $force, array( 'id' => $i + 1, 'is_active' => 1, 'page_id' => null ) );
			$by_name[ $force['name'] ] = $i + 1;
		}

		$rows   = array();
		$floats = array();
		foreach ( $parsed['rows'] as $row ) {
			$clean = GCO_PD_Validator::validate_npcc( $row['data'] )['clean'];
			$this->assertArrayHasKey( $row['force'], $by_name, $row['force'] );
			$rows[]                     = array_merge( $clean, array( 'quarter_id' => 1, 'force_id' => $by_name[ $row['force'] ] ) );
			$floats[ $row['force'] ] = $clean['completed_in_sla'] / $clean['apps_completed_12m'];
		}

		$snap = GCO_PD_Calculator::build(
			array(
				'forces'   => $forces,
				'quarters' => array( array( 'id' => 1, 'label' => '2025/26 Q4', 'data_date' => '2026-03-23' ) ),
				'npcc'     => $rows,
			)
		);

		$national = $snap['npcc']['national'];
		$this->assertSame( 476079, $national['holders'] );
		$this->assertSame( 70.67, round( $national['pct'] * 100, 2 ) );
		$this->assertSame( 43, $national['forces_ranked'] );
		$this->assertCount( 43, $snap['npcc']['leaderboard'] );

		$board = $snap['npcc']['leaderboard'];
		$this->assertEqualsWithDelta( max( $floats ), $board[0]['pct'], 0.000001 );
		$this->assertEqualsWithDelta( min( $floats ), $board[42]['pct'], 0.000001 );
		for ( $i = 1; $i < 43; $i++ ) {
			$this->assertLessThanOrEqual( $board[ $i - 1 ]['pct'] + 0.0000001, $board[ $i ]['pct'], 'Leaderboard must be sorted by % within SLA, highest first.' );
			$this->assertGreaterThanOrEqual( $board[ $i - 1 ]['rank'], $board[ $i ]['rank'] );
		}
		$this->assertSame( count( array_filter( $floats, static fn( $p ) => $p >= 0.8 ) ), $national['forces_meeting'] );
	}
}
