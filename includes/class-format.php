<?php
/**
 * Display helpers: wording for ranks, days, percentages, dates and changes.
 * Pure PHP (no WordPress), returns plain text; templates escape on output.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class GCO_PD_Format
 */
final class GCO_PD_Format {

	/**
	 * Month names (no locale dependency, so output is the same on every server).
	 *
	 * @var string[]
	 */
	const MONTHS = array( 1 => 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December' );

	/**
	 * Labels for the seven FOI metrics.
	 *
	 * @var array<string,string>
	 */
	const METRIC_LABELS = array(
		'sg_grant'    => 'New shotgun certificate',
		'fac_grant'   => 'New firearm certificate',
		'sg_renew'    => 'Shotgun renewal',
		'fac_renew'   => 'Firearm renewal',
		'variation'   => 'Variation',
		'sg_holders'  => 'Shotgun certificate holders',
		'fac_holders' => 'Firearm certificate holders',
	);

	/**
	 * 1 => "1st", 2 => "2nd", 11 => "11th", 23 => "23rd".
	 *
	 * @param int $number Number.
	 * @return string
	 */
	public static function ordinal( $number ) {
		$number = (int) $number;
		$mod100 = $number % 100;
		if ( $mod100 >= 11 && $mod100 <= 13 ) {
			return $number . 'th';
		}
		switch ( $number % 10 ) {
			case 1:
				return $number . 'st';
			case 2:
				return $number . 'nd';
			case 3:
				return $number . 'rd';
			default:
				return $number . 'th';
		}
	}

	/**
	 * "32 days" / "1 day". NULL stays NULL: a missing figure is never "0 days".
	 *
	 * @param int|float|null $days Days.
	 * @return string|null
	 */
	public static function days( $days ) {
		if ( null === $days ) {
			return null;
		}
		$text = ( floor( $days ) === (float) $days ) ? number_format( (int) $days ) : number_format( (float) $days, 1 );
		return $text . ( 1 === (int) $days && floor( $days ) === (float) $days ? ' day' : ' days' );
	}

	/**
	 * Whole number with thousands separators; NULL stays NULL.
	 *
	 * @param int|null $number Number.
	 * @return string|null
	 */
	public static function number( $number ) {
		return null === $number ? null : number_format( (int) $number );
	}

	/**
	 * Fraction to percentage text: 0.8962 => "89.6%".
	 *
	 * @param float|null $fraction Fraction 0..1.
	 * @param int        $decimals Decimal places.
	 * @return string|null
	 */
	public static function percent( $fraction, $decimals = 1 ) {
		return null === $fraction ? null : number_format( $fraction * 100, $decimals ) . '%';
	}

	/**
	 * Change in a count since the previous round: "▲ 120 more". Arrow and wording only; the class says whether
	 * the direction is good ('better'), bad ('worse') or neither ('same').
	 *
	 * @param int|float|null $change    Signed change.
	 * @param string|null    $direction up | down | same.
	 * @param string|null    $bad       Which direction is bad: 'up', 'down', or null when neither is better.
	 * @param string         $noun      "applications" ...; used for the wording.
	 * @return array{arrow:string,text:string,class:string}|null
	 */
	public static function count_change( $change, $direction, $bad = null, $noun = '' ) {
		if ( null === $change || null === $direction ) {
			return null;
		}
		$size = number_format( abs( (float) $change ), floor( abs( (float) $change ) ) === abs( (float) $change ) ? 0 : 1 );
		$tail = '' !== $noun ? ' ' . $noun : '';
		if ( 'same' === $direction ) {
			return array(
				'arrow' => "\u{2013}",
				'text'  => 'No change',
				'class' => 'same',
			);
		}
		return array(
			'arrow' => 'up' === $direction ? "\u{25B2}" : "\u{25BC}",
			'text'  => $size . $tail . ( 'up' === $direction ? ' more' : ' fewer' ),
			'class' => null === $bad ? 'same' : ( $direction === $bad ? 'worse' : 'better' ),
		);
	}

	/**
	 * "38 forces" or, when forces share a licensing department and are counted once,
	 * "41 forces (39 licensing departments)".
	 *
	 * @param int      $forces Forces that gave a figure.
	 * @param int|null $unique Independent departments after shared ones are merged (null = same as forces).
	 * @return string
	 */
	public static function forces_phrase( $forces, $unique = null ) {
		$forces = (int) $forces;
		$text   = $forces . ( 1 === $forces ? ' force' : ' forces' );
		if ( null !== $unique && (int) $unique !== $forces ) {
			$unique = (int) $unique;
			$text  .= sprintf( ' (%d licensing %s)', $unique, 1 === $unique ? 'department' : 'departments' );
		}
		return $text;
	}

	/**
	 * Force name without its service suffix, for tight lists: "Dorset Police" => "Dorset",
	 * "Metropolitan Police Service" => "Metropolitan", "Cumbria Constabulary" => "Cumbria".
	 *
	 * @param string $name Full force name.
	 * @return string
	 */
	public static function short_name( $name ) {
		$short = preg_replace( '/\s+(Police Service|Police|Constabulary)$/i', '', (string) $name );
		return '' === trim( (string) $short ) ? (string) $name : $short;
	}

	/**
	 * "2026-06" => "June 2026".
	 *
	 * @param string|null $label Round label.
	 * @return string
	 */
	public static function round_label( $label ) {
		if ( is_string( $label ) && 1 === preg_match( '/^(\d{4})-(0[1-9]|1[0-2])$/D', $label, $m ) ) {
			return self::MONTHS[ (int) $m[2] ] . ' ' . $m[1];
		}
		return (string) $label;
	}

	/**
	 * "2026-03-23" => "23 March 2026".
	 *
	 * @param string|null $date Y-m-d (a time part is ignored).
	 * @return string
	 */
	public static function date( $date ) {
		if ( is_string( $date ) && 1 === preg_match( '/^(\d{4})-(\d{2})-(\d{2})/', $date, $m ) && (int) $m[2] >= 1 && (int) $m[2] <= 12 ) {
			return (int) $m[3] . ' ' . self::MONTHS[ (int) $m[2] ] . ' ' . $m[1];
		}
		return '';
	}

	/**
	 * Wording and style for a change in waiting time. A shorter wait is good news.
	 *
	 * @param int|null    $change    Days difference (new minus old).
	 * @param string|null $direction down|up|same.
	 * @return array{arrow:string,text:string,class:string}|null Null when there is nothing to compare.
	 */
	public static function wait_change( $change, $direction ) {
		if ( null === $change || null === $direction ) {
			return null;
		}
		$size = abs( (int) $change );
		if ( 'down' === $direction ) {
			return array(
				'arrow' => "\u{25BC}",
				'text'  => $size . ( 1 === $size ? ' day' : ' days' ) . ' faster',
				'class' => 'better',
			);
		}
		if ( 'up' === $direction ) {
			return array(
				'arrow' => "\u{25B2}",
				'text'  => $size . ( 1 === $size ? ' day' : ' days' ) . ' slower',
				'class' => 'worse',
			);
		}
		return array(
			'arrow' => "\u{2013}",
			'text'  => 'No change',
			'class' => 'same',
		);
	}

	/**
	 * Wording for a change in % within SLA, in percentage points.
	 *
	 * @param float|null $change_pp Points difference.
	 * @return array{arrow:string,text:string,class:string}|null
	 */
	public static function sla_change( $change_pp ) {
		if ( null === $change_pp ) {
			return null;
		}
		if ( abs( $change_pp ) < 0.05 ) {
			return array(
				'arrow' => "\u{2013}",
				'text'  => 'No change',
				'class' => 'same',
			);
		}
		$up = $change_pp > 0;
		return array(
			'arrow' => $up ? "\u{25B2}" : "\u{25BC}",
			'text'  => number_format( abs( $change_pp ), 1 ) . ' pts',
			'class' => $up ? 'better' : 'worse',
		);
	}

	/**
	 * Colour band for % within SLA. Green means the 80% aspiration is met.
	 *
	 * @param float|null $fraction  Fraction.
	 * @param bool|null  $meets_sla Whether the aspiration is met (from the calculator, exact).
	 * @return string ok|mid|low|none
	 */
	public static function sla_class( $fraction, $meets_sla ) {
		if ( null === $fraction ) {
			return 'none';
		}
		if ( $meets_sla ) {
			return 'ok';
		}
		return $fraction >= 0.6 ? 'mid' : 'low';
	}

	/**
	 * Basis wording for a figure's status.
	 *
	 * @param string $status      actual|estimate|not_provided.
	 * @param bool   $is_average  Use "Actual average" (waiting times) rather than "Actual" (holders).
	 * @return string Empty for not provided.
	 */
	public static function basis( $status, $is_average = true ) {
		if ( GCO_PD_Validator::STATUS_ACTUAL === $status ) {
			return $is_average ? 'Actual average' : 'Actual';
		}
		if ( GCO_PD_Validator::STATUS_ESTIMATE === $status ) {
			return 'Estimate';
		}
		return '';
	}

	/**
	 * "12 days faster than the national average".
	 *
	 * @param int|null       $value   Force value.
	 * @param int|float|null $average National average.
	 * @return string Empty when either side is missing.
	 */
	public static function versus_average( $value, $average ) {
		if ( null === $value || null === $average ) {
			return '';
		}
		$diff = round( $value - $average );
		if ( 0.0 === (float) $diff ) {
			return 'In line with the national average';
		}
		$size = abs( (int) $diff );
		return $size . ( 1 === $size ? ' day' : ' days' ) . ( $diff < 0 ? ' faster' : ' slower' ) . ' than the national average';
	}
}
