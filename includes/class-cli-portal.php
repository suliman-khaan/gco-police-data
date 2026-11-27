<?php
/**
 * WP-CLI: put the new police licensing portal on a page. The work is done by GCO_PD_Setup, which the
 * Police Data > Setup screen uses too.
 *
 *   wp gco-pd portal create                 new DRAFT page from the bundled layout (live page untouched)
 *   wp gco-pd portal apply --page=4180      replace that page's content (old content kept for restore)
 *   wp gco-pd portal restore --page=4180    put the old content back
 *
 * The layout is includes/data/portal-page.txt: WPBakery rows holding the plugin's shortcodes, so
 * editors can still change copy, order and images in WPBakery afterwards.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class GCO_PD_CLI_Portal
 */
final class GCO_PD_CLI_Portal {

	/**
	 * Registers the command.
	 */
	public static function register() {
		WP_CLI::add_command( 'gco-pd portal', __CLASS__ );
	}

	/**
	 * Creates a new draft page with the portal layout. The live portal page is not touched.
	 *
	 * ## OPTIONS
	 *
	 * [--title=<title>]
	 * : Page title. Default "Police Firearms Licensing (new)".
	 *
	 * ## EXAMPLES
	 *
	 *     wp gco-pd portal create
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Flags.
	 */
	public function create( $args, $assoc_args ) {
		unset( $args );
		$id = GCO_PD_Setup::portal_create( $assoc_args['title'] ?? '' );
		if ( is_wp_error( $id ) ) {
			WP_CLI::error( $id->get_error_message() );
		}
		WP_CLI::success( sprintf( 'Draft page %d created: %s', $id, get_edit_post_link( $id, 'raw' ) ) );
		WP_CLI::log( 'Check the guide card IDs (Police guide cards element) point at pages/posts that exist on this site.' );
	}

	/**
	 * Replaces a page's content with the portal layout, keeping the old content so it can be restored.
	 * Also records the page as the portal page (breadcrumbs on force pages, cache clearing).
	 *
	 * ## OPTIONS
	 *
	 * --page=<id>
	 * : The page to replace, e.g. the live /police-firearms-licensing page.
	 *
	 * [--dry-run]
	 * : Show what would happen without changing anything.
	 *
	 * ## EXAMPLES
	 *
	 *     wp gco-pd portal apply --page=4180 --dry-run
	 *     wp gco-pd portal apply --page=4180
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Flags.
	 */
	public function apply( $args, $assoc_args ) {
		unset( $args );
		$page = $this->page( $assoc_args );
		if ( ! empty( $assoc_args['dry-run'] ) ) {
			WP_CLI::success( sprintf( 'Dry run: would replace page %d (%s, %s) and keep its old content for restore.', $page->ID, $page->post_title, $page->post_status ) );
			return;
		}
		$applied = GCO_PD_Setup::portal_apply( $page->ID );
		if ( is_wp_error( $applied ) ) {
			WP_CLI::error( $applied->get_error_message() );
		}
		WP_CLI::success( sprintf( 'Page %d now uses the new portal. Undo with: wp gco-pd portal restore --page=%d', $page->ID, $page->ID ) );
	}

	/**
	 * Puts a page's pre-portal content (and WPBakery styles) back.
	 *
	 * ## OPTIONS
	 *
	 * --page=<id>
	 * : The page to restore.
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Flags.
	 */
	public function restore( $args, $assoc_args ) {
		unset( $args );
		$page     = $this->page( $assoc_args );
		$restored = GCO_PD_Setup::portal_restore( $page->ID );
		if ( is_wp_error( $restored ) ) {
			WP_CLI::error( $restored->get_error_message() );
		}
		WP_CLI::success( sprintf( 'Page %d restored to its previous content.', $page->ID ) );
	}

	/**
	 * Loads the --page post.
	 *
	 * @param array $assoc_args Flags.
	 * @return WP_Post
	 */
	private function page( array $assoc_args ) {
		$page = get_post( absint( $assoc_args['page'] ?? 0 ) );
		if ( ! $page || 'page' !== $page->post_type ) {
			WP_CLI::error( 'Give a valid --page=<id>.' );
		}
		return $page;
	}
}
