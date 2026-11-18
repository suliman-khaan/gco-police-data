<?php
/**
 * Installer: tables, capability, seed data and schema upgrades.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class GCO_PD_Install
 */
final class GCO_PD_Install {

	/**
	 * Current schema version. Bump when the table definitions below change.
	 */
	const DB_VERSION = '5';

	/**
	 * Option holding the installed schema version.
	 */
	const DB_VERSION_OPTION = 'gco_pd_db_version';

	/**
	 * Capability required for every admin screen, form handler and import.
	 */
	const CAPABILITY = 'gco_manage_police_data';

	/**
	 * Returns a full, prefixed table name.
	 *
	 * @param string $name One of forces, rounds, responses, npcc_quarters, npcc, force_profiles, response_types.
	 * @return string
	 */
	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'gco_' . $name;
	}

	/**
	 * Plugin activation.
	 */
	public static function activate() {
		self::create_tables();
		self::add_capability();
		self::seed_forces();
		self::link_force_pages();
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, true );
	}

	/**
	 * Runs the table definitions again when the stored schema version is older than the code.
	 * Hooked to plugins_loaded; costs one (autoloaded) option read on normal requests.
	 */
	public static function maybe_upgrade() {
		if ( get_option( self::DB_VERSION_OPTION ) === self::DB_VERSION ) {
			return;
		}
		self::create_tables();
		self::add_capability();
		self::link_force_pages();
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, true );
	}

	/**
	 * Creates or updates the six custom tables with dbDelta().
	 *
	 * dbDelta() is picky: one field per line, two spaces after PRIMARY KEY, KEY (not INDEX).
	 * It does not create foreign keys, so integrity is enforced in GCO_PD_Repository.
	 */
	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$forces  = self::table( 'forces' );
		$rounds  = self::table( 'rounds' );
		$resp    = self::table( 'responses' );
		$quarter = self::table( 'npcc_quarters' );
		$npcc    = self::table( 'npcc' );
		$profile = self::table( 'force_profiles' );
		$types   = self::table( 'response_types' );
		$status  = "ENUM('actual','estimate','not_provided') NOT NULL DEFAULT 'not_provided'";

		$sql = array();

		$sql[] = "CREATE TABLE {$forces} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug VARCHAR(80) NOT NULL,
  name VARCHAR(120) NOT NULL,
  aliases VARCHAR(255) NULL DEFAULT NULL,
  shared_group VARCHAR(60) NULL DEFAULT NULL,
  country ENUM('England','Wales') NOT NULL DEFAULT 'England',
  page_id BIGINT UNSIGNED NULL DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY  (id),
  UNIQUE KEY slug (slug)
) {$charset};";

		$sql[] = "CREATE TABLE {$rounds} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  label CHAR(7) NOT NULL,
  status ENUM('draft','published') NOT NULL DEFAULT 'draft',
  published_at DATETIME NULL DEFAULT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY label (label)
) {$charset};";

		$sql[] = "CREATE TABLE {$resp} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  round_id BIGINT UNSIGNED NOT NULL,
  force_id BIGINT UNSIGNED NOT NULL,
  foi_ref VARCHAR(80) NULL DEFAULT NULL,
  response_date DATE NULL DEFAULT NULL,
  sg_grant_days SMALLINT UNSIGNED NULL DEFAULT NULL,
  sg_grant_status {$status},
  fac_grant_days SMALLINT UNSIGNED NULL DEFAULT NULL,
  fac_grant_status {$status},
  sg_renew_days SMALLINT UNSIGNED NULL DEFAULT NULL,
  sg_renew_status {$status},
  fac_renew_days SMALLINT UNSIGNED NULL DEFAULT NULL,
  fac_renew_status {$status},
  variation_days SMALLINT UNSIGNED NULL DEFAULT NULL,
  variation_status {$status},
  sg_holders INT UNSIGNED NULL DEFAULT NULL,
  sg_holders_status {$status},
  fac_holders INT UNSIGNED NULL DEFAULT NULL,
  fac_holders_status {$status},
  apps_received INT UNSIGNED NULL DEFAULT NULL,
  apps_received_status {$status},
  apps_completed INT UNSIGNED NULL DEFAULT NULL,
  apps_completed_status {$status},
  volumes_period VARCHAR(120) NULL DEFAULT NULL,
  outstanding INT UNSIGNED NULL DEFAULT NULL,
  outstanding_status {$status},
  oldest_days SMALLINT UNSIGNED NULL DEFAULT NULL,
  oldest_days_status {$status},
  backlog_unit ENUM('count','percent') NOT NULL DEFAULT 'count',
  backlog_m0_3 DECIMAL(10,2) UNSIGNED NULL DEFAULT NULL,
  backlog_m3_6 DECIMAL(10,2) UNSIGNED NULL DEFAULT NULL,
  backlog_m6_12 DECIMAL(10,2) UNSIGNED NULL DEFAULT NULL,
  backlog_m12_plus DECIMAL(10,2) UNSIGNED NULL DEFAULT NULL,
  backlog_status {$status},
  standard_text VARCHAR(255) NULL DEFAULT NULL,
  standard_days SMALLINT UNSIGNED NULL DEFAULT NULL,
  standard_days_status {$status},
  standard_met_pct DECIMAL(5,2) UNSIGNED NULL DEFAULT NULL,
  standard_met_pct_status {$status},
  std_grant_days SMALLINT UNSIGNED NULL DEFAULT NULL,
  std_grant_days_status {$status},
  std_grant_pct DECIMAL(5,2) UNSIGNED NULL DEFAULT NULL,
  std_grant_pct_status {$status},
  std_renew_days SMALLINT UNSIGNED NULL DEFAULT NULL,
  std_renew_days_status {$status},
  std_renew_pct DECIMAL(5,2) UNSIGNED NULL DEFAULT NULL,
  std_renew_pct_status {$status},
  std_variation_days SMALLINT UNSIGNED NULL DEFAULT NULL,
  std_variation_days_status {$status},
  std_variation_pct DECIMAL(5,2) UNSIGNED NULL DEFAULT NULL,
  std_variation_pct_status {$status},
  online_grant ENUM('yes','no') NULL DEFAULT NULL,
  online_renew ENUM('yes','no') NULL DEFAULT NULL,
  online_variation ENUM('yes','no') NULL DEFAULT NULL,
  post_required ENUM('yes','no') NULL DEFAULT NULL,
  notes TEXT NULL,
  pdf_id BIGINT UNSIGNED NULL DEFAULT NULL,
  updated_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY round_force (round_id,force_id),
  KEY force_id (force_id)
) {$charset};";

		$sql[] = "CREATE TABLE {$quarter} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  label CHAR(10) NOT NULL,
  data_date DATE NULL DEFAULT NULL,
  status ENUM('draft','published') NOT NULL DEFAULT 'draft',
  published_at DATETIME NULL DEFAULT NULL,
  pdf_id BIGINT UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY label (label)
) {$charset};";

		$sql[] = "CREATE TABLE {$npcc} (
  quarter_id BIGINT UNSIGNED NOT NULL,
  force_id BIGINT UNSIGNED NOT NULL,
  total_holders INT UNSIGNED NULL DEFAULT NULL,
  total_certificates INT UNSIGNED NULL DEFAULT NULL,
  apps_received_12m INT UNSIGNED NULL DEFAULT NULL,
  apps_completed_12m INT UNSIGNED NULL DEFAULT NULL,
  completed_in_sla INT UNSIGNED NULL DEFAULT NULL,
  temporary_permits INT UNSIGNED NULL DEFAULT NULL,
  notes TEXT NULL,
  PRIMARY KEY  (quarter_id,force_id),
  KEY force_id (force_id)
) {$charset};";

		$sql[] = "CREATE TABLE {$profile} (
  force_id BIGINT UNSIGNED NOT NULL,
  data LONGTEXT NOT NULL,
  updated_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY  (force_id)
) {$charset};";

		$sql[] = "CREATE TABLE {$types} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  round_id BIGINT UNSIGNED NOT NULL,
  force_id BIGINT UNSIGNED NOT NULL,
  app_type ENUM('sg_grant','fac_grant','sg_renew','fac_renew','variation') NOT NULL,
  received INT UNSIGNED NULL DEFAULT NULL,
  received_status {$status},
  completed INT UNSIGNED NULL DEFAULT NULL,
  completed_status {$status},
  outstanding INT UNSIGNED NULL DEFAULT NULL,
  outstanding_status {$status},
  updated_at DATETIME NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY round_force_type (round_id,force_id,app_type),
  KEY force_id (force_id)
) {$charset};";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
	}

	/**
	 * Grants the management capability to administrators.
	 */
	public static function add_capability() {
		$role = get_role( 'administrator' );
		if ( $role && ! $role->has_cap( self::CAPABILITY ) ) {
			$role->add_cap( self::CAPABILITY );
		}
	}

	/**
	 * Inserts the 43 territorial forces. Idempotent: existing slugs are left untouched,
	 * so manual edits to names, aliases or page links survive re-activation.
	 */
	public static function seed_forces() {
		global $wpdb;
		$table = self::table( 'forces' );
		$forces = require GCO_PD_DIR . 'includes/data/forces.php';

		foreach ( $forces as $force ) {
			// INSERT IGNORE + the UNIQUE slug key makes this safe to repeat.
			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"INSERT IGNORE INTO {$table} (slug, name, aliases, country, is_active) VALUES (%s, %s, %s, %s, 1)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$force['slug'],
					$force['name'],
					$force['aliases'],
					$force['country']
				)
			);
		}
	}

	/**
	 * Links each force without a page to the WordPress page whose slug matches.
	 *
	 * Tries the force slug, then common suffixes. Filterable so the real live-site
	 * URL pattern can be added without code changes. Never overwrites an existing link.
	 */
	public static function link_force_pages() {
		global $wpdb;
		$table = self::table( 'forces' );
		$rows  = $wpdb->get_results( "SELECT id, slug FROM {$table} WHERE page_id IS NULL OR page_id = 0" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		foreach ( (array) $rows as $row ) {
			$candidates = apply_filters(
				'gco_pd_force_page_slug_candidates',
				array(
					$row->slug,
					$row->slug . '-police',
					$row->slug . '-firearms-licensing',
					$row->slug . '-police-firearms-licensing',
					str_replace( '-and-', '-', $row->slug ) . '-police-firearms-licensing',
				),
				$row->slug
			);

			foreach ( (array) $candidates as $candidate ) {
				$page = get_page_by_path( sanitize_title( $candidate ), OBJECT, 'page' );
				if ( $page && 'publish' === $page->post_status ) {
					$wpdb->update( $table, array( 'page_id' => (int) $page->ID ), array( 'id' => (int) $row->id ), array( '%d' ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					break;
				}
			}
		}
	}
}
