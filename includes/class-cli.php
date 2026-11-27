<?php
/**
 * WP-CLI: force page migration. The work is done by GCO_PD_Setup, which the Police Data > Setup screen uses too.
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
		$rows = array();
		foreach ( GCO_PD_Setup::force_rows() as $row ) {
			$rows[] = array(
				'force'    => $row['force']->slug,
				'page'     => $row['page_id'] ? $row['page_id'] . ' (' . $row['status'] . ')' : '-',
				'template' => 'none' === $row['state'] ? '-' : ( 'template' === $row['state'] ? 'yes' : 'old' ),
				'backup'   => $row['backup'] ? 'yes' : '-',
				'contact'  => $row['filled']['contact'] ? 'yes' : '-',
				'area'     => $row['filled']['area'] ? 'yes' : '-',
				'gp'       => $row['filled']['gp'] ? 'yes' : '-',
				'quotes'   => $row['filled']['quotes'],
				'faqs'     => $row['filled']['faqs'],
			);
		}
		\WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', $rows, array_keys( $rows[0] ?? array( 'force' => '' ) ) );
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
			$report = GCO_PD_Setup::migrate_force( $force, $apply, $overwrite, $keep, $shared_saved );
			if ( 'skipped' === $report['status'] ) {
				WP_CLI::warning( $force->slug . ': ' . implode( ' ', $report['messages'] ) . ' Skipped.' );
				continue;
			}
			WP_CLI::log( '' );
			WP_CLI::log( WP_CLI::colorize( '%G' . $force->name . '%n (page ' . $report['page_id'] . ': ' . get_the_title( $report['page_id'] ) . ')' ) );
			WP_CLI::log( '  Found:   ' . ( $report['found'] ? implode( ', ', $report['found'] ) : 'nothing' ) );
			WP_CLI::log( '  Filling: ' . ( $report['filling'] ? implode( ', ', $report['filling'] ) : 'nothing new' ) );
			if ( $report['replaced'] ) {
				WP_CLI::log( '  Replaced by data: ' . implode( '; ', $report['replaced'] ) );
			}
			if ( $report['unknown'] ) {
				WP_CLI::warning( '  Not recognised, check by hand: ' . implode( '; ', $report['unknown'] ) );
			}
			foreach ( $report['messages'] as $message ) {
				WP_CLI::log( '  ' . $message );
			}
			if ( 'migrated' === $report['status'] ) {
				++$done;
			}
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
		$restored = GCO_PD_Setup::restore_force( $force );
		if ( is_wp_error( $restored ) ) {
			WP_CLI::error( $restored->get_error_message() );
		}
		WP_CLI::success( sprintf( 'Restored the original content and WPBakery styles of page %d (%s). The force profile is kept.', $force->page_id, get_the_title( $force->page_id ) ) );
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
		return GCO_PD_Setup::generalise( $text, $name, $page_title );
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
