<?php
/**
 * Snapshot storage and cache busting.
 *
 * The calculator's result is stored as ONE non-autoloaded option, rebuilt only when published data
 * changes (publish / unpublish of a round or quarter, force or settings edits). Front-end code reads
 * it with a single get_option (object-cache friendly) and does no per-force queries.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class GCO_PD_Cache
 */
final class GCO_PD_Cache {

	const OPTION         = 'gco_pd_snapshot';
	const VERSION_OPTION = 'gco_pd_cache_version';

	/**
	 * Request-level copy of the snapshot.
	 *
	 * @var array|null
	 */
	private static $memo = null;

	/**
	 * Hooks the rebuild to the "published data changed" action.
	 */
	public static function init() {
		add_action( 'gco_pd_data_changed', array( __CLASS__, 'rebuild' ) );
	}

	/**
	 * The current snapshot. Never null: before anything is published it is an empty skeleton, so
	 * callers can render "no data yet" without special cases. A snapshot built by older code is rebuilt.
	 *
	 * @return array
	 */
	public static function get_snapshot() {
		if ( null !== self::$memo ) {
			return self::$memo;
		}
		$stored = get_option( self::OPTION, null );
		if ( is_array( $stored ) && ( $stored['schema'] ?? 0 ) === GCO_PD_Calculator::SCHEMA ) {
			self::$memo = $stored;
			return $stored;
		}
		if ( null !== $stored ) {
			return self::rebuild(); // Stale layout after an update.
		}
		self::$memo = GCO_PD_Calculator::empty_snapshot();
		return self::$memo;
	}

	/**
	 * Cache version for versioned keys (bumped on every rebuild).
	 *
	 * @return int
	 */
	public static function version() {
		return (int) get_option( self::VERSION_OPTION, 1 );
	}

	/**
	 * Recalculates everything from the published data, stores it, bumps the version and clears page caches.
	 *
	 * @return array The new snapshot.
	 */
	public static function rebuild() {
		$snapshot             = GCO_PD_Calculator::build( self::gather_input() );
		$snapshot['built_at'] = gmdate( 'Y-m-d H:i:s' );

		update_option( self::OPTION, $snapshot, false ); // autoload = no.
		update_option( self::VERSION_OPTION, self::version() + 1, false );
		self::$memo = $snapshot;

		self::purge_page_caches( $snapshot );

		/**
		 * Fires after the snapshot has been rebuilt.
		 *
		 * @param array $snapshot New snapshot.
		 */
		do_action( 'gco_pd_snapshot_rebuilt', $snapshot );

		return $snapshot;
	}

	/**
	 * Reads the published rows in a handful of queries and returns plain arrays for the calculator.
	 *
	 * @return array
	 */
	private static function gather_input() {
		$to_arrays = static function ( array $rows ) {
			return array_map(
				static function ( $row ) {
					return (array) $row;
				},
				$rows
			);
		};

		return array(
			'forces'    => $to_arrays( GCO_PD_Repository::get_forces() ),
			'rounds'    => $to_arrays( GCO_PD_Repository::get_published_rounds() ),
			'responses' => $to_arrays( GCO_PD_Repository::get_published_responses() ),
			'quarters'  => $to_arrays( GCO_PD_Repository::get_published_quarters() ),
			'npcc'      => $to_arrays( GCO_PD_Repository::get_published_npcc_rows() ),
		);
	}

	/**
	 * Page ids whose cached HTML shows plugin data: the portal and every linked force page.
	 *
	 * @param array $snapshot Snapshot.
	 * @return int[]
	 */
	public static function affected_page_ids( array $snapshot ) {
		$ids    = array();
		$portal = GCO_PD_Settings::get_int( 'portal_page_id' );
		if ( $portal ) {
			$ids[] = $portal;
		}
		foreach ( $snapshot['forces'] as $force ) {
			if ( ! empty( $force['page_id'] ) ) {
				$ids[] = (int) $force['page_id'];
			}
		}
		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/**
	 * Clears page caches for the pages that show this data. Uses WP Rocket when present; other cache
	 * layers can hook gco_pd_purge_pages.
	 *
	 * @param array $snapshot Snapshot.
	 */
	private static function purge_page_caches( array $snapshot ) {
		$page_ids = self::affected_page_ids( $snapshot );

		foreach ( $page_ids as $page_id ) {
			clean_post_cache( $page_id );
			if ( function_exists( 'rocket_clean_post' ) ) {
				rocket_clean_post( $page_id );
			}
		}

		/**
		 * Lets other page-cache layers (IONOS, Cloudflare, ...) purge the affected pages.
		 *
		 * @param int[] $page_ids Page ids.
		 */
		do_action( 'gco_pd_purge_pages', $page_ids );
	}
}
