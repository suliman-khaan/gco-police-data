<?php
/**
 * Setting up the pages: put the portal layout on a page, move force pages to the template, create missing
 * force pages. One implementation used by the Police Data > Setup screen and by WP-CLI.
 *
 * Nothing here is destructive: before a page's content is replaced the original (and its WPBakery styles) is
 * kept in post meta, WordPress keeps a revision, and every change can be restored.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class GCO_PD_Setup
 */
final class GCO_PD_Setup {

	const FORCE_BACKUP_META  = '_gco_pd_pre_m6_content';
	const FORCE_BACKUP_CSS   = '_gco_pd_pre_m6_css';
	const PORTAL_BACKUP_META = '_gco_pd_pre_portal_content';
	const PORTAL_BACKUP_CSS  = '_gco_pd_pre_portal_css';

	/**
	 * Page content written by the migration: one row, the hero card sits inside the page layout.
	 */
	const TEMPLATE_CONTENT = '[vc_row][vc_column][gco_force_page][/vc_column][/vc_row]';

	/**
	 * Content written by migrations before the blog layout (full-width hero row + body row). Pages that still
	 * have exactly this are moved to TEMPLATE_CONTENT on the next migration.
	 */
	const OLD_TEMPLATE_CONTENT = '[vc_row full_width="stretch_row_content_no_spaces"][vc_column][gco_force_page part="hero"][/vc_column][/vc_row][vc_row][vc_column][gco_force_page part="body"][/vc_column][/vc_row]';

	/* ------------------------------------------------------------------------- Portal */

	/**
	 * The bundled portal layout: WPBakery rows holding the plugin's shortcodes.
	 *
	 * @return string Empty when the file is missing.
	 */
	public static function portal_layout() {
		$file = GCO_PD_DIR . 'includes/data/portal-page.txt';
		return is_readable( $file ) ? trim( (string) file_get_contents( $file ) ) : '';
	}

	/**
	 * Pages the portal layout can go on: published or draft pages, newest first.
	 *
	 * @return WP_Post[]
	 */
	public static function pages() {
		return get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => 500,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
	}

	/**
	 * Replaces a page's content with the portal layout (old content kept) and records it as the portal page.
	 *
	 * @param int $page_id Page.
	 * @return true|WP_Error
	 */
	public static function portal_apply( $page_id ) {
		$page   = get_post( (int) $page_id );
		$layout = self::portal_layout();
		if ( ! $page || 'page' !== $page->post_type ) {
			return new WP_Error( 'gco_pd_no_page', 'Choose a page.' );
		}
		if ( '' === $layout ) {
			return new WP_Error( 'gco_pd_no_layout', 'The bundled portal layout file is missing from the plugin.' );
		}
		if ( ! metadata_exists( 'post', $page->ID, self::PORTAL_BACKUP_META ) ) {
			update_post_meta( $page->ID, self::PORTAL_BACKUP_META, wp_slash( $page->post_content ) );
			update_post_meta( $page->ID, self::PORTAL_BACKUP_CSS, wp_slash( (string) get_post_meta( $page->ID, '_wpb_shortcodes_custom_css', true ) ) );
		}
		$updated = wp_update_post(
			array(
				'ID'           => $page->ID,
				'post_content' => wp_slash( $layout ),
			),
			true
		);
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}
		// The old row styling no longer applies; WPBakery rebuilds it the next time the page is saved in its editor.
		delete_post_meta( $page->ID, '_wpb_shortcodes_custom_css' );

		$settings                   = GCO_PD_Settings::all();
		$settings['portal_page_id'] = (int) $page->ID;
		GCO_PD_Settings::save_from_input( $settings );
		do_action( 'gco_pd_data_changed' );
		return true;
	}

	/**
	 * Puts a page's pre-portal content (and WPBakery styles) back.
	 *
	 * @param int $page_id Page.
	 * @return true|WP_Error
	 */
	public static function portal_restore( $page_id ) {
		$page_id = (int) $page_id;
		if ( ! get_post( $page_id ) ) {
			return new WP_Error( 'gco_pd_no_page', 'Choose a page.' );
		}
		return self::restore_from_meta( $page_id, self::PORTAL_BACKUP_META, self::PORTAL_BACKUP_CSS );
	}

	/**
	 * A new draft page holding the portal layout; nothing existing is touched.
	 *
	 * @param string $title Title.
	 * @return int|WP_Error Page id.
	 */
	public static function portal_create( $title = '' ) {
		$layout = self::portal_layout();
		if ( '' === $layout ) {
			return new WP_Error( 'gco_pd_no_layout', 'The bundled portal layout file is missing from the plugin.' );
		}
		return wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'draft',
				'post_title'   => '' !== trim( (string) $title ) ? sanitize_text_field( $title ) : 'Police Firearms Licensing (new)',
				'post_content' => wp_slash( $layout ),
			),
			true
		);
	}

	/**
	 * Whether a page has a stored pre-portal copy.
	 *
	 * @param int $page_id Page.
	 * @return bool
	 */
	public static function portal_has_backup( $page_id ) {
		return (bool) $page_id && metadata_exists( 'post', (int) $page_id, self::PORTAL_BACKUP_META );
	}

	/* ------------------------------------------------------------------------- Force pages */

	/**
	 * One row per force: its page and whether the page uses the template.
	 *
	 * @return array[] force, page_id, status, state (none|template|old), backup, filled.
	 */
	public static function force_rows() {
		$profiles = GCO_PD_Profile::all();
		$rows     = array();
		foreach ( GCO_PD_Repository::get_forces() as $force ) {
			$page_id = (int) $force->page_id;
			$post    = $page_id ? get_post( $page_id ) : null;
			$content = $post ? (string) $post->post_content : '';
			$state   = 'none';
			if ( $post ) {
				$state = has_shortcode( $content, 'gco_force_page' ) ? 'template' : 'old';
			}
			$rows[] = array(
				'force'   => $force,
				'page_id' => $post ? $page_id : 0,
				'status'  => $post ? $post->post_status : '',
				'state'   => $state,
				'blank'      => $post && '' === trim( $content ),
				'old_layout' => $post && self::OLD_TEMPLATE_CONTENT === trim( $content ),
				'needs'      => $post && ( ( 'old' === $state && '' !== trim( $content ) ) || self::OLD_TEMPLATE_CONTENT === trim( $content ) ),
				'backup'  => $post && metadata_exists( 'post', $page_id, self::FORCE_BACKUP_META ),
				'filled'  => GCO_PD_Profile::completeness( $profiles[ (int) $force->id ] ?? GCO_PD_Profile::empty_profile() ),
			);
		}
		return $rows;
	}

	/**
	 * Moves one force's page to the template. With $apply false nothing is changed and the report says what
	 * would happen. The old content is read into the force profile (only empty fields, unless $overwrite),
	 * kept in post meta, and the page content becomes the template shortcode.
	 *
	 * @param object $force        Force row.
	 * @param bool   $apply        Write changes.
	 * @param bool   $overwrite    Replace profile fields that already have a value.
	 * @param bool   $keep_content Fill the profile but leave the page content alone.
	 * @param bool   $shared_saved Whether the shared defaults are already filled; set true once this call fills them.
	 * @return array{status:string,force:object,page_id:int,found:string[],filling:string[],replaced:string[],unknown:string[],messages:string[]}
	 *         status: skipped | preview | migrated | error.
	 */
	public static function migrate_force( $force, $apply, $overwrite, $keep_content, &$shared_saved ) {
		$report  = array(
			'status'   => 'skipped',
			'force'    => $force,
			'page_id'  => (int) $force->page_id,
			'found'    => array(),
			'filling'  => array(),
			'replaced' => array(),
			'unknown'  => array(),
			'messages' => array(),
		);
		$page_id = (int) $force->page_id;
		$post    = $page_id ? get_post( $page_id ) : null;
		if ( ! $post ) {
			$report['messages'][] = 'No linked page.';
			return $report;
		}

		$content  = (string) $post->post_content;
		$template = has_shortcode( $content, 'gco_force_page' );
		$source   = $template ? (string) get_post_meta( $page_id, self::FORCE_BACKUP_META, true ) : $content;
		if ( '' === trim( $source ) ) {
			if ( $template && self::OLD_TEMPLATE_CONTENT === trim( $content ) && ! $keep_content ) {
				// Nothing to read, but the page uses the old two-row layout: move it to the single row.
				$report['status']     = $apply ? 'migrated' : 'preview';
				$report['messages'][] = 'Page layout moves to the single-row template.';
				if ( $apply ) {
					self::write_template( $page_id );
				}
				return $report;
			}
			$report['messages'][] = $template ? 'Already uses the template and has no stored original to read from.' : 'The page is empty, so there is nothing to read. Use "Create / fill empty pages" to put the template on it.';
			return $report;
		}

		$result  = GCO_PD_Page_Extractor::extract( $source );
		$current = GCO_PD_Profile::get( (int) $force->id );
		$merged  = $current;
		foreach ( $result['profile'] as $key => $value ) {
			$empty = is_array( $current[ $key ] ?? null ) ? ! $current[ $key ] : '' === trim( (string) ( $current[ $key ] ?? '' ) );
			if ( $overwrite || $empty ) {
				$merged[ $key ]     = $value;
				$report['filling'][] = $key;
			}
		}
		$report['found']    = $result['report']['found'];
		$report['replaced'] = $result['report']['skipped'];
		$report['unknown']  = $result['report']['unknown'];

		if ( ! $apply ) {
			$report['status'] = 'preview';
			return $report;
		}

		// 1. Backup (first run only) before anything changes.
		if ( ! $template && ! metadata_exists( 'post', $page_id, self::FORCE_BACKUP_META ) ) {
			update_post_meta( $page_id, self::FORCE_BACKUP_META, wp_slash( $content ) );
			update_post_meta( $page_id, self::FORCE_BACKUP_CSS, wp_slash( (string) get_post_meta( $page_id, '_wpb_shortcodes_custom_css', true ) ) );
		}

		// 2. Profile.
		$saved = GCO_PD_Profile::save( (int) $force->id, $merged );
		if ( is_wp_error( $saved ) ) {
			$report['status']     = 'error';
			$report['messages'][] = 'Profile not saved: ' . $saved->get_error_message();
			return $report;
		}

		// 3. Shared blocks, from the first page only and only if nobody has saved them yet.
		if ( ! $shared_saved && $result['shared'] ) {
			$shared = GCO_PD_Profile::shared();
			foreach ( array( 'inspection_text', 'inspection_products', 'disclaimer' ) as $key ) {
				if ( isset( $result['shared'][ $key ] ) ) {
					$shared[ $key ] = self::generalise( $result['shared'][ $key ], $force->name, get_the_title( $page_id ) );
				}
			}
			GCO_PD_Profile::save_shared( $shared );
			$shared_saved         = true;
			$report['messages'][] = 'Shared defaults filled from this page (inspection text, products, disclaimer).';
		}

		// 4. Page content (WordPress stores a revision of the old content).
		if ( ! $keep_content && ( ! $template || self::OLD_TEMPLATE_CONTENT === trim( $content ) ) ) {
			$updated = self::write_template( $page_id );
			if ( is_wp_error( $updated ) ) {
				$report['status']     = 'error';
				$report['messages'][] = 'Page not updated: ' . $updated->get_error_message();
				return $report;
			}
			$report['messages'][] = 'Page content switched to the template.';
		}
		$report['status'] = 'migrated';
		return $report;
	}

	/**
	 * Puts a force page's pre-migration content back (the profile is kept).
	 *
	 * @param object $force Force row.
	 * @return true|WP_Error
	 */
	public static function restore_force( $force ) {
		if ( ! $force || ! $force->page_id ) {
			return new WP_Error( 'gco_pd_no_page', 'That force has no linked page.' );
		}
		return self::restore_from_meta( (int) $force->page_id, self::FORCE_BACKUP_META, self::FORCE_BACKUP_CSS );
	}

	/**
	 * Gives every force without a page a draft page holding the template, and links it. Pages that exist but
	 * are completely empty get the template as well (nothing is replaced: there is nothing in them).
	 *
	 * @param bool $apply Write changes; false only counts.
	 * @return array{created:string[],filled:string[]} Force names.
	 */
	public static function create_missing_pages( $apply ) {
		$out = array(
			'created' => array(),
			'filled'  => array(),
		);
		foreach ( self::force_rows() as $row ) {
			$force = $row['force'];
			if ( 'none' === $row['state'] ) {
				$out['created'][] = $force->name;
				if ( $apply ) {
					$id = wp_insert_post(
						array(
							'post_type'    => 'page',
							'post_status'  => 'draft',
							'post_title'   => GCO_PD_Format::short_name( $force->name ) . ' Police Firearms Licensing',
							'post_name'    => sanitize_title( $force->slug . '-police-firearms-licensing' ),
							'post_content' => wp_slash( self::TEMPLATE_CONTENT ),
						),
						true
					);
					if ( ! is_wp_error( $id ) ) {
						GCO_PD_Repository::update_force( (int) $force->id, array( 'page_id' => (int) $id ) );
					}
				}
			} elseif ( $row['blank'] ) {
				$out['filled'][] = $force->name;
				if ( $apply ) {
					self::write_template( (int) $row['page_id'] );
				}
			}
		}
		if ( $apply ) {
			do_action( 'gco_pd_data_changed' );
		}
		return $out;
	}

	/**
	 * Turns page-specific text into shared text: every way the force is named becomes {force}.
	 *
	 * @param string $text       Text from one force page.
	 * @param string $name       Force name, e.g. "Derbyshire Constabulary".
	 * @param string $page_title Page title, e.g. "Derbyshire Police Firearms Licensing".
	 * @return string
	 */
	public static function generalise( $text, $name, $page_title = '' ) {
		$short    = GCO_PD_Format::short_name( $name );
		$variants = array_unique(
			array_filter(
				array(
					$name,
					trim( preg_replace( '/\s*Firearms Licensing.*$/i', '', html_entity_decode( (string) $page_title, ENT_QUOTES, 'UTF-8' ) ) ),
					$short . ' Police Service',
					$short . ' Constabulary',
					$short . ' Police',
				)
			)
		);
		usort(
			$variants,
			static function ( $a, $b ) {
				return strlen( $b ) <=> strlen( $a );
			}
		);
		return str_ireplace( $variants, '{force}', (string) $text );
	}

	/* ------------------------------------------------------------------------- Internals */

	/**
	 * Writes the template shortcode as the page content and drops stale WPBakery row styles.
	 *
	 * @param int $page_id Page.
	 * @return int|WP_Error
	 */
	private static function write_template( $page_id ) {
		$updated = wp_update_post(
			array(
				'ID'           => (int) $page_id,
				'post_content' => wp_slash( self::TEMPLATE_CONTENT ),
			),
			true
		);
		if ( ! is_wp_error( $updated ) ) {
			delete_post_meta( (int) $page_id, '_wpb_shortcodes_custom_css' );
		}
		return $updated;
	}

	/**
	 * Restores post content and WPBakery styles from two meta keys.
	 *
	 * @param int    $page_id  Page.
	 * @param string $meta     Content meta key.
	 * @param string $css_meta CSS meta key.
	 * @return true|WP_Error
	 */
	private static function restore_from_meta( $page_id, $meta, $css_meta ) {
		if ( ! metadata_exists( 'post', $page_id, $meta ) ) {
			return new WP_Error( 'gco_pd_no_backup', 'No stored original for this page.' );
		}
		$updated = wp_update_post(
			array(
				'ID'           => $page_id,
				'post_content' => wp_slash( (string) get_post_meta( $page_id, $meta, true ) ),
			),
			true
		);
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}
		$css = (string) get_post_meta( $page_id, $css_meta, true );
		if ( '' !== $css ) {
			update_post_meta( $page_id, '_wpb_shortcodes_custom_css', wp_slash( $css ) );
		}
		return true;
	}
}
