<?php
/**
 * Inline SVG icons for the front end (24px stroke icons, currentColor, decorative).
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class GCO_PD_Icons
 */
final class GCO_PD_Icons {

	/**
	 * Path data per icon.
	 *
	 * @var array<string,string>
	 */
	const PATHS = array(
		'trophy'      => '<path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0V4z"/><path d="M17 5h3v2a3 3 0 0 1-3 3M7 5H4v2a3 3 0 0 0 3 3"/>',
		'clock'       => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		'calendar'    => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
		'book'        => '<path d="M2 5c3-1.3 6.7-1.3 10 1 3.3-2.3 7-2.3 10-1v14c-3-1.3-6.7-1.3-10 1-3.3-2.3-7-2.3-10-1V5z"/><path d="M12 6v14"/>',
		'shield'      => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6l8-3z"/><path d="M9 12l2 2 4-4"/>',
		'info'        => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
		'help'        => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.7v.5M12 17h.01"/>',
		'users'       => '<circle cx="9" cy="8" r="3"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><circle cx="17" cy="9" r="2.5"/><path d="M16 14.2c2.9.4 5 2.8 5 5.8"/>',
		'document'    => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5z"/><path d="M14 3v5h5M9 13h6M9 17h6"/>',
		'check'       => '<circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.7 2.7L16 9.8"/>',
		'chart'       => '<path d="M5 20v-5M10 20V10M15 20v-8M20 20V5"/>',
		'search'      => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>',
		'arrow-down'  => '<path d="M12 4v16M6 14l6 6 6-6"/>',
		'arrow-up'    => '<path d="M12 20V4M6 10l6-6 6 6"/>',
		'arrow-right' => '<path d="M4 12h16M14 6l6 6-6 6"/>',
		'chevron'     => '<path d="M6 9l6 6 6-6"/>',
	);

	/**
	 * An icon as inline SVG, or '' for an unknown name.
	 *
	 * @param string $name  Icon name.
	 * @param string $class Extra class.
	 * @return string Safe markup.
	 */
	public static function get( $name, $class = '' ) {
		if ( ! isset( self::PATHS[ $name ] ) ) {
			return '';
		}
		return '<svg class="gco-pd-icon' . ( $class ? ' ' . esc_attr( $class ) : '' ) . '" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . self::PATHS[ $name ] . '</svg>';
	}
}
