<?php
/**
 * WP-CLI: force page migration.
 *
 *   wp gco-pd force-pages status
 *   wp gco-pd force-pages migrate --force=derbyshire            (dry run: report only)
 *   wp gco-pd force-pages migrate --force=derbyshire --apply    (write profile + page content)
 *   wp gco-pd force-pages migrate --all --apply
 *   wp gco-pd force-pages restore --force=derbyshire
 *
 * migrate never loses the old page: before the first change the original content is kept in the
 * post meta _gco_pd_pre_m6_content and WordPress also stores a revision. restore puts it back.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class GCO_PD_CLI
 */
final class GCO_PD_CLI {

	const BACKUP_META = '_gco_pd_pre_m6_content';
	const BACKUP_CSS  = '_gco_pd_pre_m6_css';

	/**
	 * Page content written by the migration: full-width hero row + body row.
	 */
	const TEMPLATE_CONTENT = '[vc_row full_width="stretch_row_content_no_spaces"][vc_column][gco_force_page part="hero"][/vc_column][/vc_row][vc_row][vc_column][gco_force_page part="body"][/vc_column][/vc_row]';

	/**
	 * Registers the command.
	 */
	public static function register() {
		WP_CLI::add_command( 'gco-pd force-pages', __CLASS__ );
	}

	/**
	 * Lists every force with its page, template status and profile completeness.
	 *
	 * ## EXAMPLES
	 *
	 *     wp gco-pd force-pages status
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Flags.
	 */
	public function status( $args, $assoc_args ) {
		unset( $args );
		$profiles = GCO_PD_Profile::all();
		$rows     = array();
		foreach ( GCO_PD_Repository::get_forces() as $force ) {
			$page_id = (int) $force->page_id;
			$content = $page_id ? (string) get_post_field( 'post_content', $page_id ) : '';
			$filled  = GCO_PD_Profile::completeness( $profiles[ (int) $force->id ] ?? GCO_PD_Profile::empty_profile() );
			$rows[]  = array(
				'force'    => $force->slug,
				'page'     => $page_id ? $page_id . ' (' . get_post_status( $page_id ) . ')' : '-',
				'template' => $page_id ? ( has_shortcode( $content, 'gco_force_page' ) ? 'yes' : 'old' ) : '-',
				'backup'   => $page_id && metadata_exists( 'post', $page_id, self::BACKUP_META ) ? 'yes' : '-',
				'contact'  => $filled['contact'] ? 'yes' : '-',
				'area'     => $filled['area'] ? 'yes' : '-',
				'gp'       => $filled['gp'] ? 'yes' : '-',
				'quotes'   => $filled['quotes'],
				'faqs'     => $filled['faqs'],
			);
		}
		WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', $rows, array_keys( $rows[0] ?? array( 'force' => '' ) ) );
	}

	/**
	 * Moves force pages to the template: reads their written content into the force profile, keeps a
	 * backup of the old content and swaps the page content to [gco_force_page]. Dry run unless --apply.
	 *
	 * ## OPTIONS
	 *
	 * [--force=<slug>]
	 * : One force, e.g. derbyshire.
	 *
	 * [--all]
	 * : Every force with a linked page that still uses the old layout.
	 *
	 * [--apply]
	 * : Write the profile and page content. Without it nothing is changed.
	 *
	 * [--overwrite]
	 * : Replace profile fields that already have a value (default: only fill empty fields).
	 *
	 * [--keep-content]
	 * : Fill the profile but leave the page content as it is.
	 *
	 * ## EXAMPLES
	 *
	 *     wp gco-pd force-pages migrate --force=derbyshire
	 *     wp gco-pd force-pages migrate --force=derbyshire --apply
	 *     wp gco-pd force-pages migrate --all --apply
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Flags.
	 */
	public function migrate( $args, $assoc_args ) {
		unset( $args );
		$apply     = ! empty( $assoc_args['apply'] );
		$overwrite = ! empty( $assoc_args['overwrite'] );
		$keep      = ! empty( $assoc_args['keep-content'] );
		$forces    = $this->select_forces( $assoc_args );

		if ( ! $apply ) {
			WP_CLI::log( 'Dry run: nothing will be changed. Add --apply to write.' );
		}

		$shared_saved = false !== get_option( GCO_PD_Profile::SHARED_OPTION, false );
		$done         = 0;
		foreach ( $forces as $force ) {
			$page_id = (int) $force->page_id;
			if ( ! $page_id || ! get_post( $page_id ) ) {
				WP_CLI::warning( $force->slug . ': no linked page, skipped.' );
				continue;
			}
			$content = (string) get_post_field( 'post_content', $page_id );
			$source  = has_shortcode( $content, 'gco_force_page' ) ? (string) get_post_meta( $page_id, self::BACKUP_META, true ) : $content;
			if ( '' === $source ) {
				WP_CLI::warning( $force->slug . ': page already uses the template and has no backup to read from, skipped.' );
				continue;
			}

			$result  = GCO_PD_Page_Extractor::extract( $source );
			$current = GCO_PD_Profile::get( (int) $force->id );
			$merged  = $current;
			$filled  = array();
			foreach ( $result['profile'] as $key => $value ) {
				$empty = is_array( $current[ $key ] ?? null ) ? ! $current[ $key ] : '' === trim( (string) ( $current[ $key ] ?? '' ) );
				if ( $overwrite || $empty ) {
					$merged[ $key ] = $value;
					$filled[]       = $key;
				}
			}

			WP_CLI::log( '' );
			WP_CLI::log( WP_CLI::colorize( '%G' . $force->name . '%n (page ' . $page_id . ': ' . get_the_title( $page_id ) . ')' ) );
			WP_CLI::log( '  Found:   ' . ( $result['report']['found'] ? implode( ', ', $result['report']['found'] ) : 'nothing' ) );
			WP_CLI::log( '  Filling: ' . ( $filled ? implode( ', ', $filled ) : 'nothing new' ) );
			if ( $result['report']['skipped'] ) {
				WP_CLI::log( '  Replaced by data: ' . implode( '; ', $result['report']['skipped'] ) );
			}
			if ( $result['report']['unknown'] ) {
				WP_CLI::warning( '  Not recognised, check by hand: ' . implode( '; ', $result['report']['unknown'] ) );
			}

			if ( ! $apply ) {
				continue;
			}

			// 1. Backup (first run only) before anything changes.
			if ( ! metadata_exists( 'post', $page_id, self::BACKUP_META ) && ! has_shortcode( $content, 'gco_force_page' ) ) {
				update_post_meta( $page_id, self::BACKUP_META, wp_slash( $content ) );
				update_post_meta( $page_id, self::BACKUP_CSS, wp_slash( (string) get_post_meta( $page_id, '_wpb_shortcodes_custom_css', true ) ) );
			}

			// 2. Profile.
			$saved = GCO_PD_Profile::save( (int) $force->id, $merged );
			if ( is_wp_error( $saved ) ) {
				WP_CLI::warning( '  Profile not saved: ' . $saved->get_error_message() );
				continue;
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
				$shared_saved = true;
				WP_CLI::log( '  Shared defaults filled from this page (inspection text, products, disclaimer).' );
			}

			// 4. Page content (a revision of the old content is stored by WordPress).
			if ( ! $keep && ! has_shortcode( $content, 'gco_force_page' ) ) {
				$updated = wp_update_post(
					array(
						'ID'           => $page_id,
						'post_content' => wp_slash( self::TEMPLATE_CONTENT ),
					),
					true
				);
				if ( is_wp_error( $updated ) ) {
					WP_CLI::warning( '  Page not updated: ' . $updated->get_error_message() );
					continue;
				}
				delete_post_meta( $page_id, '_wpb_shortcodes_custom_css' );
				WP_CLI::log( '  Page content switched to the template.' );
			}
			++$done;
		}

		WP_CLI::log( '' );
		if ( $apply ) {
			WP_CLI::success( sprintf( '%d force page(s) migrated. Undo one with: wp gco-pd force-pages restore --force=<slug>', $done ) );
		} else {
			WP_CLI::success( 'Dry run finished. Review the report, then run again with --apply.' );
		}
	}

	/**
	 * Puts a page's pre-migration content back (the profile is kept).
	 *
	 * ## OPTIONS
	 *
	 * --force=<slug>
	 * : The force whose page to restore.
	 *
	 * ## EXAMPLES
	 *
	 *     wp gco-pd force-pages restore --force=derbyshire
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Flags.
	 */
	public function restore( $args, $assoc_args ) {
		unset( $args );
		$force = GCO_PD_Repository::get_force_by_slug( sanitize_title( $assoc_args['force'] ?? '' ) );
		if ( ! $force || ! $force->page_id ) {
			WP_CLI::error( 'Unknown force or no linked page.' );
		}
		$page_id = (int) $force->page_id;
		$backup  = get_post_meta( $page_id, self::BACKUP_META, true );
		if ( '' === (string) $backup ) {
			WP_CLI::error( 'No backup stored for this page.' );
		}
		$updated = wp_update_post(
			array(
				'ID'           => $page_id,
				'post_content' => wp_slash( $backup ),
			),
			true
		);
		if ( is_wp_error( $updated ) ) {
			WP_CLI::error( $updated->get_error_message() );
		}
		$css = (string) get_post_meta( $page_id, self::BACKUP_CSS, true );
		if ( '' !== $css ) {
			update_post_meta( $page_id, '_wpb_shortcodes_custom_css', wp_slash( $css ) );
		}
		WP_CLI::success( sprintf( 'Restored the original content and WPBakery styles of page %d (%s). The force profile is kept.', $page_id, get_the_title( $page_id ) ) );
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

	/**
	 * Forces chosen by --force / --all.
	 *
	 * @param array $assoc_args Flags.
	 * @return object[]
	 */
	private function select_forces( array $assoc_args ) {
		if ( ! empty( $assoc_args['force'] ) ) {
			$force = GCO_PD_Repository::get_force_by_slug( sanitize_title( $assoc_args['force'] ) );
			if ( ! $force ) {
				WP_CLI::error( 'Unknown force slug: ' . $assoc_args['force'] );
			}
			return array( $force );
		}
		if ( empty( $assoc_args['all'] ) ) {
			WP_CLI::error( 'Choose --force=<slug> or --all.' );
		}
		return array_filter(
			GCO_PD_Repository::get_forces(),
			static function ( $force ) {
				return ! empty( $force->page_id );
			}
		);
	}
}
