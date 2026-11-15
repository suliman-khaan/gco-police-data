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
	 * @return array<string,bool>
	 */
	public static function defaults() {
		return array(
			'show_pdf_public' => false, // Link the FOI PDF publicly on force pages.
			'delete_data'     => false, // Remove tables and options when the plugin is deleted.
		);
	}

	/**
	 * All settings merged over the defaults.
	 *
	 * @return array<string,bool>
	 */
	public static function all() {
		$stored = get_option( self::OPTION, array() );
		return array_merge( self::defaults(), is_array( $stored ) ? array_intersect_key( $stored, self::defaults() ) : array() );
	}

	/**
	 * One setting.
	 *
	 * @param string $key Setting key.
	 * @return bool
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
			$clean[ $key ] = ! empty( $input[ $key ] );
		}
		update_option( self::OPTION, $clean, true );
	}
}
