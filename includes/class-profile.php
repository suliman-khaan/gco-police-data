<?php
/**
 * Force page profiles: the written content of each force page (contact details, GP medical notes,
 * applicant reports, FAQs ...) plus the defaults shared by every force page.
 *
 * A profile is stored as one JSON document per force in gco_force_profiles, so new fields need no
 * schema change. The field list below drives sanitising, the admin form and the completeness report.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class GCO_PD_Profile
 */
final class GCO_PD_Profile {

	const SHARED_OPTION = 'gco_pd_force_defaults';

	/**
	 * Per-force fields: key => [type, label, help]. List types carry their sub-fields.
	 *
	 * Types: text, textarea (plain, line breaks kept), html (limited HTML), url, email, image (attachment id),
	 * date (Y-m-d), lines (one item per line), ids (comma separated post ids), list.
	 *
	 * @return array<string,array>
	 */
	public static function fields() {
		return array(
			// Hero.
			'tagline'          => array( 'text', 'Tagline', 'Short line under the page title. Leave empty for the default.' ),
			'intro'            => array( 'textarea', 'Intro', 'One or two sentences shown in the hero.' ),
			'hero_image'       => array( 'image', 'Hero photo', 'Wide landscape photo. Falls back to the default photo in Shared defaults.' ),
			'crest_image'      => array( 'image', 'Force crest', 'Optional. Only use a crest the client has permission to show.' ),
			// Contact.
			'contact_unit'     => array( 'text', 'Unit name', 'e.g. Firearms Licensing Unit' ),
			'contact_address'  => array( 'textarea', 'Address', 'One line per address line.' ),
			'contact_phone'    => array( 'text', 'Telephone', '' ),
			'contact_email'    => array( 'email', 'Email', '' ),
			'contact_hours'    => array( 'text', 'Office / licensing hours', '' ),
			'contact_url'      => array( 'url', 'Force licensing webpage', '' ),
			'contact_note'     => array( 'textarea', 'Contact note', 'Optional note shown under the contact details.' ),
			// Area.
			'area_name'        => array( 'text', 'Area name', 'Used in "Firearms Licensing in {area}". Defaults to the force name without "Police".' ),
			'area_text'        => array( 'html', 'About licensing in this area', 'The main description of the force and its area.' ),
			'applicant_text'   => array( 'html', 'General applicant information', 'How applications are made (online, postal, telephone ...).' ),
			'applicant_points' => array( 'lines', 'Applicant checklist', 'One point per line, shown with ticks.' ),
			// GP medical.
			'gp_text'          => array( 'html', 'GP medical text', 'Leave empty to use the shared text.' ),
			'gp_providers'     => array(
				'list',
				'Medical providers',
				'Private providers offering firearms medicals in this area.',
				array(
					'name'    => array( 'text', 'Name' ),
					'phone'   => array( 'text', 'Telephone' ),
					'website' => array( 'url', 'Website' ),
				),
			),
			'gp_note'          => array( 'textarea', 'GP note', 'Optional, e.g. "Know a provider we have missed?".' ),
			// Waiting times and national picture.
			'waits_text'       => array( 'html', 'Waiting times introduction', 'Shown above the waiting-times table, e.g. how long applicants usually wait and what the figures mean.' ),
			'national_text'    => array( 'html', 'National comparison text', 'Shown above the national figures: how this force compares with the national picture.' ),
			// Applicant reports.
			'reports_intro'    => array( 'textarea', 'Applicant reports intro', 'A short summary of what applicants report.' ),
			'quotes'           => array(
				'list',
				'Applicant quotes',
				'Shown three across. Only publish quotes you have permission to use.',
				array(
					'quote'  => array( 'textarea', 'Quote' ),
					'who'    => array( 'text', 'Who / application type', 'e.g. Shotgun renewal, May 2026' ),
					'source' => array( 'text', 'Source', 'e.g. The Stalking Directory (2024)' ),
				),
			),
			// FAQs.
			'faqs'             => array(
				'list',
				'Frequently asked questions',
				'Shown in two columns with FAQ structured data.',
				array(
					'question' => array( 'text', 'Question' ),
					'answer'   => array( 'html', 'Answer' ),
				),
			),
			// Review and guides.
			'reviewed'         => array( 'date', 'Page reviewed', '' ),
			'next_review'      => array( 'date', 'Next scheduled review', '' ),
			'guide_ids'        => array( 'ids', 'Related guides', 'Page or post IDs, comma separated. Leave empty for the shared list.' ),
		);
	}

	/**
	 * Admin form tabs: label => field keys.
	 *
	 * @return array<string,string[]>
	 */
	public static function tabs() {
		return array(
			'Hero'              => array( 'tagline', 'intro', 'hero_image', 'crest_image' ),
			'Contact'           => array( 'contact_unit', 'contact_address', 'contact_phone', 'contact_email', 'contact_hours', 'contact_url', 'contact_note' ),
			'Area & applicants' => array( 'area_name', 'area_text', 'applicant_text', 'applicant_points' ),
			'GP medical'        => array( 'gp_text', 'gp_providers', 'gp_note' ),
			'Waiting times & national' => array( 'waits_text', 'national_text' ),
			'Applicant reports' => array( 'reports_intro', 'quotes' ),
			'FAQs'              => array( 'faqs' ),
			'Review & guides'   => array( 'reviewed', 'next_review', 'guide_ids' ),
		);
	}

	/**
	 * Shared fields used by every force page.
	 *
	 * @return array<string,array>
	 */
	public static function shared_fields() {
		return array(
			'hero_image'          => array( 'image', 'Default hero photo', 'Used when a force has no photo of its own.' ),
			'tagline'             => array( 'text', 'Default tagline', 'Use {force} for the force name.' ),
			'gp_text'             => array( 'html', 'Default GP medical text', 'Use {force} for the force name.' ),
			'gp_points'           => array( 'lines', 'GP medical checklist', 'One point per line, shown on every force page.' ),
			'gp_url'              => array( 'url', '"More about medical requirements" link', '' ),
			'inspection_title'    => array( 'text', 'Security inspection title', '' ),
			'inspection_text'     => array( 'html', 'Security inspection text', 'Use {force} for the force name.' ),
			'inspection_image'    => array( 'image', 'Security inspection image', '' ),
			'inspection_products' => array( 'ids', 'Products to show', 'Optional WooCommerce product IDs, comma separated.' ),
			'inspection_btn_text' => array( 'text', 'Button text', '' ),
			'inspection_btn_url'  => array( 'url', 'Button link', '' ),
			'waits_note'           => array( 'html', 'Note under the waiting times', 'Shown under every waiting-times table. Leave empty to hide it.' ),
			'share_title'          => array( 'text', 'Share prompt: heading', 'Shown under the waiting times with share buttons. Use {force} for the force name. Leave empty to hide the share row.' ),
			'share_text'           => array( 'text', 'Share prompt: line under it', '' ),
			'feedback_text'        => array( 'html', 'Feedback invitation', 'Shown under the applicant reports. Use {force} for the force name. Leave empty to hide it.' ),
			'guide_ids'           => array( 'ids', 'Related guides', 'Page or post IDs, comma separated.' ),
			'guides_url'          => array( 'url', '"View all guides" link', '' ),
			'disclaimer'          => array( 'html', 'Disclaimer and sources', 'Shown at the foot of every force page.' ),
		);
	}

	/**
	 * Shared defaults before anything is saved.
	 *
	 * @return array
	 */
	public static function shared_defaults() {
		return array(
			'hero_image'          => 0,
			'tagline'             => 'Firearms licensing information, waiting times, inspection guidance and application resources',
			'gp_text'             => 'As part of your application, {force} will require medical information from your GP. This usually involves a medical report and confirmation of any relevant conditions or treatment.',
			'gp_points'           => "Medical information is required for all applicants\nYour GP may charge a fee for completing the report\nDelays can occur if medical information is outstanding\nA specialist medical reporting provider may be used where permitted",
			'gp_url'              => '',
			'inspection_title'    => 'Preparing for Your Firearms Security Inspection',
			'inspection_text'     => 'Before a certificate can be granted, a Firearms Enquiry Officer (FEO) will usually visit your home to assess your firearm security arrangements. Your gun cabinet should meet BS 7558:1992 and be securely installed in a suitable location.',
			'inspection_image'    => 0,
			'inspection_products' => '',
			'inspection_btn_text' => 'View Gun Cabinets',
			'inspection_btn_url'  => home_url( '/product-category/gun-cabinets/' ),
			'waits_note'           => 'Where a processing time range was provided by the force, the figures shown represent the midpoint of that range to allow consistent comparison with other police forces included within our national firearms licensing dataset.',
			'share_title'          => 'Know someone applying or renewing their certificate with {force}?',
			'share_text'           => 'Share these latest waiting times with them.',
			'feedback_text'        => '<strong>Have feedback you wish to share?</strong> If you have had a recent interaction with {force} Firearms Licensing and wish to share it, please let us know by email and we\'ll consider adding it to this page.',
			'guide_ids'           => '',
			'guides_url'          => '',
			'disclaimer'          => '',
		);
	}

	/* ------------------------------------------------------------------ Storage */

	/**
	 * Empty profile with every key present.
	 *
	 * @return array
	 */
	public static function empty_profile() {
		$profile = array();
		foreach ( self::fields() as $key => $field ) {
			$profile[ $key ] = self::empty_value( $field[0] );
		}
		return $profile;
	}

	/**
	 * Empty value for a field type.
	 *
	 * @param string $type Type.
	 * @return mixed
	 */
	private static function empty_value( $type ) {
		if ( 'list' === $type ) {
			return array();
		}
		return 'image' === $type ? 0 : '';
	}

	/**
	 * A force's profile (every key present).
	 *
	 * @param int $force_id Force id.
	 * @return array
	 */
	public static function get( $force_id ) {
		global $wpdb;
		$table = GCO_PD_Install::table( 'force_profiles' );
		$json  = $wpdb->get_var( $wpdb->prepare( "SELECT data FROM {$table} WHERE force_id = %d", (int) $force_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$data  = $json ? json_decode( (string) $json, true ) : array();
		return array_merge( self::empty_profile(), is_array( $data ) ? array_intersect_key( $data, self::fields() ) : array() );
	}

	/**
	 * Every stored profile, keyed by force id (one query, for list screens).
	 *
	 * @return array<int,array>
	 */
	public static function all() {
		global $wpdb;
		$table = GCO_PD_Install::table( 'force_profiles' );
		$rows  = $wpdb->get_results( "SELECT force_id, data, updated_at FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out   = array();
		foreach ( (array) $rows as $row ) {
			$data                         = json_decode( (string) $row->data, true );
			$out[ (int) $row->force_id ] = array_merge( self::empty_profile(), is_array( $data ) ? array_intersect_key( $data, self::fields() ) : array() );
			$out[ (int) $row->force_id ]['_updated_at'] = $row->updated_at;
		}
		return $out;
	}

	/**
	 * Saves a force's profile (input is sanitised here).
	 *
	 * @param int   $force_id Force id.
	 * @param array $input    Raw values (unslashed).
	 * @return true|WP_Error
	 */
	public static function save( $force_id, array $input ) {
		global $wpdb;
		if ( ! GCO_PD_Repository::get_force( $force_id ) ) {
			return new WP_Error( 'gco_pd_no_force', 'That force does not exist.' );
		}
		$data  = self::sanitize( $input, self::fields() );
		$table = GCO_PD_Install::table( 'force_profiles' );
		$ok    = $wpdb->replace( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'force_id'   => (int) $force_id,
				'data'       => wp_json_encode( $data ),
				'updated_by' => get_current_user_id(),
				'updated_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%d', '%s' )
		);
		if ( false === $ok ) {
			return new WP_Error( 'gco_pd_db', 'Could not save the profile.' );
		}
		self::purge_force_page( $force_id );
		return true;
	}

	/**
	 * Shared defaults (stored values merged over the built-in ones).
	 *
	 * @return array
	 */
	public static function shared() {
		$stored = get_option( self::SHARED_OPTION, array() );
		return array_merge( self::shared_defaults(), is_array( $stored ) ? array_intersect_key( $stored, self::shared_fields() ) : array() );
	}

	/**
	 * Saves the shared defaults and clears every force page's cache.
	 *
	 * @param array $input Raw values (unslashed).
	 */
	public static function save_shared( array $input ) {
		update_option( self::SHARED_OPTION, self::sanitize( $input, self::shared_fields() ), false );
		foreach ( GCO_PD_Repository::get_forces() as $force ) {
			if ( ! empty( $force->page_id ) ) {
				self::purge_page( (int) $force->page_id );
			}
		}
	}

	/**
	 * Clears the cache of the page linked to a force.
	 *
	 * @param int $force_id Force id.
	 */
	private static function purge_force_page( $force_id ) {
		$force = GCO_PD_Repository::get_force( $force_id );
		if ( $force && ! empty( $force->page_id ) ) {
			self::purge_page( (int) $force->page_id );
		}
	}

	/**
	 * Clears one page from the object and page caches.
	 *
	 * @param int $page_id Page id.
	 */
	private static function purge_page( $page_id ) {
		clean_post_cache( $page_id );
		if ( function_exists( 'rocket_clean_post' ) ) {
			rocket_clean_post( $page_id );
		}
		/** This action is documented in includes/class-cache.php */
		do_action( 'gco_pd_purge_pages', array( $page_id ) );
	}

	/* --------------------------------------------------------------- Sanitising */

	/**
	 * Sanitises input against a field list. Unknown keys are dropped; list rows that are entirely
	 * empty are removed; lists are capped at 50 rows.
	 *
	 * @param array $input  Raw values (unslashed).
	 * @param array $fields Field definitions.
	 * @return array
	 */
	public static function sanitize( array $input, array $fields ) {
		$clean = array();
		foreach ( $fields as $key => $field ) {
			$value = $input[ $key ] ?? null;
			if ( 'list' === $field[0] ) {
				$rows = array();
				foreach ( array_slice( is_array( $value ) ? array_values( $value ) : array(), 0, 50 ) as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$item = array();
					foreach ( $field[3] as $sub_key => $sub ) {
						$item[ $sub_key ] = self::sanitize_value( $sub[0], $row[ $sub_key ] ?? '' );
					}
					if ( array_filter( $item, 'strlen' ) ) {
						$rows[] = $item;
					}
				}
				$clean[ $key ] = $rows;
			} else {
				$clean[ $key ] = self::sanitize_value( $field[0], $value );
			}
		}
		return $clean;
	}

	/**
	 * Sanitises one scalar value.
	 *
	 * @param string $type  Field type.
	 * @param mixed  $value Raw value.
	 * @return string|int
	 */
	public static function sanitize_value( $type, $value ) {
		$value = is_scalar( $value ) ? (string) $value : '';
		switch ( $type ) {
			case 'textarea':
			case 'lines':
				$lines = array_map( 'sanitize_text_field', preg_split( '/\r\n|\r|\n/', $value ) );
				return trim( implode( "\n", 'lines' === $type ? array_filter( $lines, 'strlen' ) : $lines ) );
			case 'html':
				return trim( wp_kses( $value, self::allowed_html() ) );
			case 'url':
				return esc_url_raw( trim( $value ) );
			case 'email':
				return sanitize_email( $value );
			case 'image':
				return absint( $value );
			case 'date':
				$value = trim( $value );
				return preg_match( '/^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/D', $value ) ? $value : '';
			case 'ids':
				return implode( ',', array_filter( array_map( 'absint', preg_split( '/[\s,]+/', $value ) ) ) );
			default:
				return sanitize_text_field( $value );
		}
	}

	/**
	 * Tags allowed in rich-text fields: paragraphs, emphasis, links and simple lists.
	 *
	 * @return array
	 */
	public static function allowed_html() {
		return array(
			'p'      => array(),
			'br'     => array(),
			'strong' => array(),
			'b'      => array(),
			'em'     => array(),
			'i'      => array(),
			'u'      => array(),
			'ul'     => array(),
			'ol'     => array(),
			'li'     => array(),
			'a'      => array(
				'href'   => true,
				'title'  => true,
				'target' => true,
				'rel'    => true,
			),
		);
	}

	/* ----------------------------------------------------------------- Reports */

	/**
	 * Which parts of a profile are filled, for the Force Pages screen and the CLI.
	 *
	 * @param array $profile Profile.
	 * @return array<string,int|bool> contact, area, gp, quotes (count), faqs (count), hero.
	 */
	public static function completeness( array $profile ) {
		return array(
			'hero'    => '' !== $profile['intro'],
			'contact' => '' !== $profile['contact_address'] || '' !== $profile['contact_phone'] || '' !== $profile['contact_email'],
			'area'    => '' !== $profile['area_text'],
			'gp'      => '' !== $profile['gp_text'] || ! empty( $profile['gp_providers'] ),
			'quotes'  => count( $profile['quotes'] ),
			'faqs'    => count( $profile['faqs'] ),
		);
	}

	/**
	 * Replaces {force} in shared text.
	 *
	 * @param string $text Text.
	 * @param string $name Force name.
	 * @return string
	 */
	public static function fill( $text, $name ) {
		return str_replace( '{force}', $name, (string) $text );
	}
}
