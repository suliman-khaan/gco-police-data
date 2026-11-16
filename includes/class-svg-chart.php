<?php
/**
 * Inline SVG line chart: no JavaScript, no external requests, nothing loaded on other pages.
 * Pure PHP (no WordPress). Every text value is escaped here; callers pass plain text.
 *
 * A missing figure (null) breaks the line instead of being drawn as 0. Series differ by colour AND
 * dash pattern, and each point has a <title> tooltip. Always show the data table next to the chart.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class GCO_PD_SVG_Chart
 */
final class GCO_PD_SVG_Chart {

	const WIDTH  = 640;
	const HEIGHT = 300;

	/**
	 * Series colours and dash patterns (colour-blind friendly: shape differs, not only hue).
	 *
	 * @var array<int,array{0:string,1:string}>
	 */
	const STYLES = array(
		array( '#064d49', '' ),
		array( '#b45309', '8 5' ),
		array( '#1d4ed8', '2 4' ),
		array( '#7c2d12', '12 4 2 4' ),
	);

	/**
	 * Escapes text for SVG.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function e( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_XML1, 'UTF-8' );
	}

	/**
	 * Rounds the top of the y axis up to a tidy number and returns the tick step.
	 *
	 * @param int|float $max Largest value.
	 * @return array{0:int,1:int} Axis maximum and step.
	 */
	public static function nice_axis( $max ) {
		if ( $max <= 0 ) {
			return array( 10, 5 );
		}
		$step = 10;
		foreach ( array( 5, 10, 20, 25, 50, 100, 200, 250, 500, 1000, 2000, 5000, 10000 ) as $candidate ) {
			$step = $candidate;
			if ( $max / $candidate <= 5 ) {
				break;
			}
		}
		return array( (int) ( ceil( $max / $step ) * $step ), $step );
	}

	/**
	 * Builds the chart.
	 *
	 * @param array<string,array<int,int|null>> $series Series name => one value per x position (null = missing).
	 * @param string[]                          $labels X labels, one per position.
	 * @param array                             $args   title, description, unit (e.g. "days").
	 * @return string SVG markup, or an empty string when there is nothing to draw.
	 */
	public static function line( array $series, array $labels, array $args = array() ) {
		$count = count( $labels );
		$has   = false;
		$max   = 0;
		foreach ( $series as $values ) {
			foreach ( $values as $value ) {
				if ( null !== $value ) {
					$has = true;
					$max = max( $max, $value );
				}
			}
		}
		if ( ! $has || $count < 1 ) {
			return '';
		}

		$title = $args['title'] ?? 'Trend';
		$desc  = $args['description'] ?? '';
		$unit  = $args['unit'] ?? '';
		$uid   = 'gco-chart-' . substr( md5( $title . implode( '|', $labels ) . implode( '|', array_keys( $series ) ) ), 0, 8 );

		list( $axis_max, $step ) = self::nice_axis( $max );

		$left   = 48;
		$right  = 16;
		$top    = 40;
		$bottom = 44;
		$plot_w = self::WIDTH - $left - $right;
		$plot_h = self::HEIGHT - $top - $bottom;

		$x = static function ( $i ) use ( $count, $left, $plot_w ) {
			return $count > 1 ? $left + ( $plot_w * $i / ( $count - 1 ) ) : $left + $plot_w / 2;
		};
		$y = static function ( $value ) use ( $top, $plot_h, $axis_max ) {
			return $top + $plot_h - ( $plot_h * $value / $axis_max );
		};

		$svg  = sprintf(
			'<svg class="gco-pd-chart" viewBox="0 0 %1$d %2$d" role="img" aria-labelledby="%3$s-t %3$s-d" xmlns="http://www.w3.org/2000/svg">',
			self::WIDTH,
			self::HEIGHT,
			self::e( $uid )
		);
		$svg .= '<title id="' . self::e( $uid ) . '-t">' . self::e( $title ) . '</title>';
		$svg .= '<desc id="' . self::e( $uid ) . '-d">' . self::e( $desc ) . '</desc>';

		// Gridlines and y labels.
		for ( $tick = 0; $tick <= $axis_max; $tick += $step ) {
			$ty   = round( $y( $tick ), 1 );
			$svg .= sprintf( '<line x1="%d" y1="%s" x2="%d" y2="%s" stroke="#d9dedd" stroke-width="1"/>', $left, $ty, self::WIDTH - $right, $ty );
			$svg .= sprintf( '<text x="%d" y="%s" text-anchor="end" font-size="11" fill="#4b5755">%s</text>', $left - 8, round( $y( $tick ) + 4, 1 ), self::e( (string) $tick ) );
		}
		if ( '' !== $unit ) {
			$svg .= sprintf( '<text x="%d" y="%d" font-size="11" fill="#4b5755">%s</text>', $left, $top - 24, self::e( ucfirst( $unit ) ) );
		}

		// X labels.
		foreach ( $labels as $i => $label ) {
			$svg .= sprintf( '<text x="%s" y="%d" text-anchor="middle" font-size="11" fill="#4b5755">%s</text>', round( $x( $i ), 1 ), self::HEIGHT - $bottom + 18, self::e( $label ) );
		}

		// Series.
		$index  = 0;
		$legend = '';
		foreach ( $series as $name => $values ) {
			list( $colour, $dash ) = self::STYLES[ $index % count( self::STYLES ) ];
			$dash_attr             = '' === $dash ? '' : ' stroke-dasharray="' . self::e( $dash ) . '"';

			// Split into runs so a missing figure leaves a gap rather than a drop to zero.
			$runs = array();
			$run  = array();
			foreach ( $labels as $i => $label ) {
				$value = $values[ $i ] ?? null;
				if ( null === $value ) {
					if ( $run ) {
						$runs[] = $run;
						$run    = array();
					}
					continue;
				}
				$run[] = array( $i, $value );
			}
			if ( $run ) {
				$runs[] = $run;
			}

			foreach ( $runs as $points ) {
				if ( count( $points ) > 1 ) {
					$coords = array();
					foreach ( $points as $point ) {
						$coords[] = round( $x( $point[0] ), 1 ) . ',' . round( $y( $point[1] ), 1 );
					}
					$svg .= sprintf( '<polyline fill="none" stroke="%s" stroke-width="2.5" stroke-linejoin="round"%s points="%s"/>', $colour, $dash_attr, implode( ' ', $coords ) );
				}
				foreach ( $points as $point ) {
					$svg .= sprintf(
						'<circle cx="%s" cy="%s" r="4" fill="#fff" stroke="%s" stroke-width="2"><title>%s</title></circle>',
						round( $x( $point[0] ), 1 ),
						round( $y( $point[1] ), 1 ),
						$colour,
						self::e( sprintf( '%s, %s: %s%s', $name, $labels[ $point[0] ], $point[1], '' !== $unit ? ' ' . $unit : '' ) )
					);
				}
			}

			// Legend entry.
			$lx      = $left + $index * 190;
			$legend .= sprintf(
				'<line x1="%1$d" y1="14" x2="%2$d" y2="14" stroke="%3$s" stroke-width="2.5"%4$s/><text x="%5$d" y="18" font-size="12" fill="#1c2b29">%6$s</text>',
				$lx,
				$lx + 26,
				$colour,
				$dash_attr,
				$lx + 32,
				self::e( $name )
			);
			++$index;
		}

		return $svg . $legend . '</svg>';
	}
}
