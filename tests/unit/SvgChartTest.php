<?php
/**
 * Unit tests for GCO_PD_SVG_Chart.
 *
 * @package GCO_Police_Data
 */

use PHPUnit\Framework\TestCase;

/**
 * Class SvgChartTest
 */
final class SvgChartTest extends TestCase {

	public function test_axis_rounding(): void {
		$this->assertSame( array( 10, 5 ), GCO_PD_SVG_Chart::nice_axis( 0 ) );
		$this->assertSame( array( 10, 5 ), GCO_PD_SVG_Chart::nice_axis( 7 ) );
		$this->assertSame( array( 60, 20 ), GCO_PD_SVG_Chart::nice_axis( 51 ) );
		$this->assertSame( array( 100, 20 ), GCO_PD_SVG_Chart::nice_axis( 100 ) );
		$this->assertSame( array( 400, 100 ), GCO_PD_SVG_Chart::nice_axis( 380 ) );
	}

	public function test_nothing_to_draw_gives_an_empty_string(): void {
		$this->assertSame( '', GCO_PD_SVG_Chart::line( array(), array( 'A', 'B' ) ) );
		$this->assertSame( '', GCO_PD_SVG_Chart::line( array( 'Wait' => array( null, null ) ), array( 'A', 'B' ) ) );
		$this->assertSame( '', GCO_PD_SVG_Chart::line( array( 'Wait' => array( 5 ) ), array() ) );
	}

	public function test_output_is_well_formed_xml_with_an_accessible_name(): void {
		if ( ! function_exists( 'simplexml_load_string' ) ) {
			$this->markTestSkipped( 'simplexml missing.' );
		}
		$svg = GCO_PD_SVG_Chart::line(
			array(
				'Shotgun' => array( 40, 32 ),
				'Firearm' => array( 50, 38 ),
			),
			array( 'June 2026', 'December 2026' ),
			array( 'title' => 'Derbyshire waits', 'description' => 'Days per round', 'unit' => 'days' )
		);
		$xml = simplexml_load_string( $svg );
		$this->assertNotFalse( $xml, 'SVG must be valid XML.' );
		$this->assertSame( 'img', (string) $xml['role'] );
		$this->assertStringContainsString( 'Derbyshire waits', $svg );
		$this->assertSame( 2, substr_count( $svg, '<polyline' ) );
		$this->assertSame( 4, substr_count( $svg, '<circle' ) );
	}

	public function test_a_missing_figure_leaves_a_gap_and_is_never_drawn_as_zero(): void {
		$svg = GCO_PD_SVG_Chart::line( array( 'Wait' => array( 10, null, 30, 40 ) ), array( 'A', 'B', 'C', 'D' ) );
		// Runs: [A] alone (a dot) and [C, D] (a line).
		$this->assertSame( 1, substr_count( $svg, '<polyline' ) );
		$this->assertSame( 3, substr_count( $svg, '<circle' ) );
	}

	public function test_a_single_point_is_a_dot_not_a_line(): void {
		$svg = GCO_PD_SVG_Chart::line( array( 'Wait' => array( 32 ) ), array( 'June 2026' ) );
		$this->assertSame( 0, substr_count( $svg, '<polyline' ) );
		$this->assertSame( 1, substr_count( $svg, '<circle' ) );
	}

	public function test_text_is_escaped(): void {
		$svg = GCO_PD_SVG_Chart::line(
			array( '<script>alert(1)</script>' => array( 1, 2 ) ),
			array( '"A"', "B' & C" ),
			array( 'title' => '<b>x</b>' )
		);
		$this->assertStringNotContainsString( '<script>', $svg );
		$this->assertStringNotContainsString( '<b>', $svg );
		$this->assertStringContainsString( '&lt;script&gt;', $svg );
	}

	public function test_series_are_told_apart_by_dash_pattern_as_well_as_colour(): void {
		$svg = GCO_PD_SVG_Chart::line(
			array(
				'One' => array( 1, 2 ),
				'Two' => array( 2, 3 ),
			),
			array( 'A', 'B' )
		);
		$this->assertStringContainsString( 'stroke-dasharray="8 5"', $svg );
	}

	public function test_output_is_deterministic(): void {
		$args = array( array( 'Wait' => array( 1, 2 ) ), array( 'A', 'B' ) );
		$this->assertSame( GCO_PD_SVG_Chart::line( ...$args ), GCO_PD_SVG_Chart::line( ...$args ) );
	}
	public function test_bars_skip_when_nothing_to_draw(): void {
		$this->assertSame( '', GCO_PD_SVG_Chart::bars( array() ) );
		$this->assertSame( '', GCO_PD_SVG_Chart::bars( array( 'June 2026' => null ) ) );
	}

	public function test_bars_are_well_formed_and_mark_missing_values(): void {
		$svg = GCO_PD_SVG_Chart::bars(
			array(
				'June 2025' => null,
				'Dec 2025 & co' => 70,
				'June 2026' => 53,
			),
			array( 'title' => 'Waits', 'unit' => 'days', 'axis_label' => 'Days' )
		);
		$this->assertNotFalse( simplexml_load_string( $svg ) );
		$this->assertSame( 2, substr_count( $svg, '<rect ' ) );
		$this->assertStringContainsString( 'Not provided', $svg );
		$this->assertStringContainsString( 'Dec 2025 &amp; co', $svg );
		$this->assertStringContainsString( '53 days', $svg );
		$this->assertStringContainsString( '#0f6b3f', $svg ); // Latest bar highlighted.
	}
}
