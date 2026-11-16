<?php
/**
 * Unit tests for GCO_PD_Format.
 *
 * @package GCO_Police_Data
 */

use PHPUnit\Framework\TestCase;

/**
 * Class FormatTest
 */
final class FormatTest extends TestCase {

	public function test_ordinals(): void {
		$expected = array( 1 => '1st', 2 => '2nd', 3 => '3rd', 4 => '4th', 11 => '11th', 12 => '12th', 13 => '13th', 21 => '21st', 22 => '22nd', 23 => '23rd', 43 => '43rd', 101 => '101st', 111 => '111th' );
		foreach ( $expected as $number => $text ) {
			$this->assertSame( $text, GCO_PD_Format::ordinal( $number ), (string) $number );
		}
	}

	public function test_days_never_invents_a_zero(): void {
		$this->assertNull( GCO_PD_Format::days( null ) );
		$this->assertSame( '0 days', GCO_PD_Format::days( 0 ), 'A real zero is shown as zero.' );
		$this->assertSame( '1 day', GCO_PD_Format::days( 1 ) );
		$this->assertSame( '32 days', GCO_PD_Format::days( 32 ) );
		$this->assertSame( '1,000 days', GCO_PD_Format::days( 1000 ) );
		$this->assertSame( '32.5 days', GCO_PD_Format::days( 32.5 ) );
	}

	public function test_numbers_and_percentages(): void {
		$this->assertNull( GCO_PD_Format::number( null ) );
		$this->assertSame( '476,079', GCO_PD_Format::number( 476079 ) );
		$this->assertSame( '0', GCO_PD_Format::number( 0 ) );
		$this->assertNull( GCO_PD_Format::percent( null ) );
		$this->assertSame( '89.6%', GCO_PD_Format::percent( 0.896235 ) );
		$this->assertSame( '70.67%', GCO_PD_Format::percent( 0.7067, 2 ) );
		$this->assertSame( '0.0%', GCO_PD_Format::percent( 0.0 ) );
	}

	public function test_dates(): void {
		$this->assertSame( 'June 2026', GCO_PD_Format::round_label( '2026-06' ) );
		$this->assertSame( 'December 2026', GCO_PD_Format::round_label( '2026-12' ) );
		$this->assertSame( 'junk', GCO_PD_Format::round_label( 'junk' ) );
		$this->assertSame( '23 March 2026', GCO_PD_Format::date( '2026-03-23' ) );
		$this->assertSame( '3 March 2026', GCO_PD_Format::date( '2026-03-03 10:15:00' ) );
		$this->assertSame( '', GCO_PD_Format::date( '' ) );
		$this->assertSame( '', GCO_PD_Format::date( null ) );
		$this->assertSame( '', GCO_PD_Format::date( '2026-13-01' ) );
	}

	public function test_wait_changes_treat_shorter_as_better(): void {
		$better = GCO_PD_Format::wait_change( -8, 'down' );
		$this->assertSame( '8 days faster', $better['text'] );
		$this->assertSame( 'better', $better['class'] );

		$worse = GCO_PD_Format::wait_change( 1, 'up' );
		$this->assertSame( '1 day slower', $worse['text'] );
		$this->assertSame( 'worse', $worse['class'] );

		$same = GCO_PD_Format::wait_change( 0, 'same' );
		$this->assertSame( 'No change', $same['text'] );

		$this->assertNull( GCO_PD_Format::wait_change( null, null ), 'No comparison means no arrow.' );
	}

	public function test_sla_changes(): void {
		$up = GCO_PD_Format::sla_change( 30.0 );
		$this->assertSame( '30.0 pts', $up['text'] );
		$this->assertSame( 'better', $up['class'] );

		$down = GCO_PD_Format::sla_change( -2.5 );
		$this->assertSame( '2.5 pts', $down['text'] );
		$this->assertSame( 'worse', $down['class'] );

		$this->assertSame( 'same', GCO_PD_Format::sla_change( 0.02 )['class'] );
		$this->assertNull( GCO_PD_Format::sla_change( null ) );
	}

	public function test_sla_class_bands(): void {
		$this->assertSame( 'none', GCO_PD_Format::sla_class( null, null ) );
		$this->assertSame( 'ok', GCO_PD_Format::sla_class( 0.8, true ) );
		$this->assertSame( 'mid', GCO_PD_Format::sla_class( 0.79, false ) );
		$this->assertSame( 'mid', GCO_PD_Format::sla_class( 0.6, false ) );
		$this->assertSame( 'low', GCO_PD_Format::sla_class( 0.336, false ) );
	}

	public function test_basis_wording(): void {
		$this->assertSame( 'Actual average', GCO_PD_Format::basis( 'actual' ) );
		$this->assertSame( 'Actual', GCO_PD_Format::basis( 'actual', false ) );
		$this->assertSame( 'Estimate', GCO_PD_Format::basis( 'estimate' ) );
		$this->assertSame( '', GCO_PD_Format::basis( 'not_provided' ) );
	}

	public function test_versus_average(): void {
		$this->assertSame( '8 days faster than the national average', GCO_PD_Format::versus_average( 32, 40.4 ) );
		$this->assertSame( '1 day slower than the national average', GCO_PD_Format::versus_average( 41, 40.4 ) );
		$this->assertSame( 'In line with the national average', GCO_PD_Format::versus_average( 40, 40.2 ) );
		$this->assertSame( '', GCO_PD_Format::versus_average( null, 40 ) );
		$this->assertSame( '', GCO_PD_Format::versus_average( 40, null ) );
	}
}
