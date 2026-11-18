<?php
/**
 * Plugin settings: one small autoloaded option.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class GCO_PD_Settings
 */
final class GCO_PD_Settings {

	const OPTION = 'gco_pd_settings';

	/**
	 * Default values. Both options are off unless the site owner turns them on.
	 *
	 * @return array<string,bool|int>
	 */
	public static function defaults() {
		return array(
			'show_pdf_public' => false, // Link the FOI PDF publicly on force pages.
			'delete_data'     => false, // Remove tables and options when the plugin is deleted.
			// Extra FOI figures: collected always, public only when switched on.
			'show_volumes'    => false, // Applications received / completed on force pages.
			'show_outstanding' => false, // Outstanding applications and the oldest outstanding.
			'show_backlog'    => false, // Backlog age bands.
			'show_standards'  => false, // Service standards per category and how often they were met.
			'show_type_volumes' => false, // Received / completed / outstanding by application type.
			'show_application_methods' => false, // Online and postal applications.
			'portal_page_id'  => 0,     // The police licensing portal page (its page cache is cleared on publish).
		);
	}

	/**
	 * Keys stored as whole numbers rather than on/off flags.
	 *
	 * @var string[]
	 */
	const INT_KEYS = array( 'portal_page_id' );

	/**
	 * One whole-number setting.
	 *
	 * @param string $key Setting key.
	 * @return int
	 */
	public static function get_int( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? absint( $all[ $key ] ) : 0;
	}

	/**
	 * All settings merged over the defaults.
	 *
	 * @return array<string,bool|int>
	 */
	public static function all() {
		$stored = get_option( self::OPTION, array() );
		return array_merge( self::defaults(), is_array( $stored ) ? array_intersect_key( $stored, self::defaults() ) : array() );
	}

	/**
	 * One setting.
	 *
	 * @param string $key Setting key.
	 * @return bool On/off flags only; use get_int() for page ids.
	 */
	public static function get( $key ) {
		$all = self::all();
		return ! empty( $all[ $key ] );
	}

	/**
	 * Saves settings from a submitted form (checkboxes: present = on).
	 *
	 * @param array $input Raw input, e.g. $_POST.
	 */
	public static function save_from_input( array $input ) {
		$clean = array();
		foreach ( array_keys( self::defaults() ) as $key ) {
			if ( in_array( $key, self::INT_KEYS, true ) ) {
				$clean[ $key ] = isset( $input[ $key ] ) ? absint( wp_unslash( $input[ $key ] ) ) : 0;
			} else {
				$clean[ $key ] = ! empty( $input[ $key ] );
			}
		}
		update_option( self::OPTION, $clean, true );
	}
}
