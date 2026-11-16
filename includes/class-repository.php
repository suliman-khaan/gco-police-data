<?php
/**
 * Repository: every database read and write for the plugin, all through $wpdb->prepare() or the
 * $wpdb helper methods. Table names come only from GCO_PD_Install::table() ($wpdb->prefix + a fixed name).
 *
 * Rules enforced here because dbDelta() cannot create foreign keys:
 *  - a response/NPCC row needs an existing round/quarter and force;
 *  - only draft rounds/quarters can be deleted (their rows go with them);
 *  - a force that has data cannot be deleted (deactivate it instead).
 *
 * Failures return WP_Error; reads return objects/arrays/null. NULL is never coerced to 0.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

/**
 * Class GCO_PD_Repository
 */
final class GCO_PD_Repository {

	/* ------------------------------------------------------------------ Forces */

	/**
	 * All forces, alphabetically.
	 *
	 * @param bool $active_only Only active forces.
	 * @return object[]
	 */
	public static function get_forces( $active_only = false ) {
		global $wpdb;
		$table = GCO_PD_Install::table( 'forces' );
		$where = $active_only ? 'WHERE is_active = 1' : '';
		return (array) $wpdb->get_results( "SELECT * FROM {$table} {$where} ORDER BY name ASC" );
	}

	/**
	 * One force by id.
	 *
	 * @param int $id Force id.
	 * @return object|null
	 */
	public static function get_force( $id ) {
		global $wpdb;
		$table = GCO_PD_Install::table( 'forces' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
	}

	/**
	 * One force by slug.
	 *
	 * @param string $slug Force slug.
	 * @return object|null
	 */
	public static function get_force_by_slug( $slug ) {
		global $wpdb;
		$table = GCO_PD_Install::table( 'forces' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE slug = %s", $slug ) );
	}

	/**
	 * The force linked to a WordPress page (used by force="auto").
	 *
	 * @param int $page_id Page id.
	 * @return object|null
	 */
	public static function get_force_by_page( $page_id ) {
		global $wpdb;
		$table = GCO_PD_Install::table( 'forces' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE page_id = %d LIMIT 1", $page_id ) );
	}

	/**
	 * Normalises a force name for matching: lower case, "&" = "and", letters and digits only.
	 *
	 * @param string $name Name as typed or imported.
	 * @return string
	 */
	public static function normalize_name( $name ) {
		$name = strtolower( str_replace( '&', ' and ', (string) $name ) );
		return preg_replace( '/[^a-z0-9]+/', '', $name );
	}

	/**
	 * Builds a lookup of normalised name => force id from names, slugs, aliases and the name
	 * with its "Police" / "Constabulary" / "Police Service" suffix removed.
	 *
	 * @return array<string,int>
	 */
	public static function get_force_name_map() {
		$map = array();
		foreach ( self::get_forces() as $force ) {
			$names = array( $force->name, $force->slug, preg_replace( '/\s+(Police Service|Police|Constabulary)$/i', '', $force->name ) );
			if ( ! empty( $force->aliases ) ) {
				$names = array_merge( $names, array_map( 'trim', explode( ',', $force->aliases ) ) );
			}
			foreach ( $names as $name ) {
				$key = self::normalize_name( $name );
				if ( '' !== $key && ! isset( $map[ $key ] ) ) {
					$map[ $key ] = (int) $force->id;
				}
			}
		}
		return $map;
	}

	/**
	 * Finds a force by any of its names. Used by the importer ("GMP", "Met Police", "Avon & Somerset").
	 *
	 * @param string $name Name from a spreadsheet.
	 * @param array  $map  Optional prebuilt map from get_force_name_map() (avoids a query per row).
	 * @return int|null Force id.
	 */
	public static function find_force_id( $name, array $map = array() ) {
		if ( empty( $map ) ) {
			$map = self::get_force_name_map();
		}
		$key = self::normalize_name( $name );
		return $map[ $key ] ?? null;
	}

	/**
	 * A shared-department group key: lower case, letters, numbers and dashes ("Warwickshire & West Mercia" => "warwickshire-west-mercia").
	 *
	 * @param mixed $group Raw name.
	 * @return string
	 */
	public static function normalize_group( $group ) {
		$group = strtolower( trim( (string) $group ) );
		$group = str_replace( '&', ' ', $group );
		return trim( preg_replace( '/[^a-z0-9]+/', '-', $group ), '-' );
	}

	/**
	 * Updates editable force fields.
	 *
	 * @param int   $id   Force id.
	 * @param array $data Any of name, aliases, shared_group ('' for none), page_id (0/null to unlink), is_active.
	 * @return true|WP_Error
	 */
	public static function update_force( $id, array $data ) {
		global $wpdb;
		if ( ! self::get_force( $id ) ) {
			return new WP_Error( 'gco_pd_no_force', 'That force does not exist.' );
		}

		$fields  = array();
		$formats = array();
		if ( isset( $data['name'] ) ) {
			$name = trim( (string) $data['name'] );
			if ( '' === $name || strlen( $name ) > 120 ) {
				return new WP_Error( 'gco_pd_bad_name', 'Force name must be 1 to 120 characters.' );
			}
			$fields['name'] = $name;
			$formats[]      = '%s';
		}
		if ( array_key_exists( 'aliases', $data ) ) {
			$aliases = trim( (string) $data['aliases'] );
			if ( strlen( $aliases ) > 255 ) {
				return new WP_Error( 'gco_pd_bad_aliases', 'Aliases must be 255 characters or fewer.' );
			}
			$fields['aliases'] = '' === $aliases ? null : $aliases;
			$formats[]         = '%s';
		}
		if ( array_key_exists( 'shared_group', $data ) ) {
			$group = self::normalize_group( $data['shared_group'] );
			if ( strlen( $group ) > 60 ) {
				return new WP_Error( 'gco_pd_bad_group', 'The shared department name must be 60 characters or fewer.' );
			}
			$fields['shared_group'] = '' === $group ? null : $group;
			$formats[]                 = '%s';
		}
		if ( array_key_exists( 'page_id', $data ) ) {
			$page_id           = (int) $data['page_id'];
			$fields['page_id'] = $page_id > 0 ? $page_id : null;
			$formats[]         = '%d';
		}
		if ( isset( $data['is_active'] ) ) {
			$fields['is_active'] = $data['is_active'] ? 1 : 0;
			$formats[]           = '%d';
		}
		if ( empty( $fields ) ) {
			return true;
		}

		$result = $wpdb->update( GCO_PD_Install::table( 'forces' ), $fields, array( 'id' => (int) $id ), $formats, array( '%d' ) );
		return false === $result ? new WP_Error( 'gco_pd_db', 'Could not save the force.' ) : true;
	}

	/**
	 * Deletes a force that has no data attached.
	 *
	 * @param int $id Force id.
	 * @return true|WP_Error
	 */
	public static function delete_force( $id ) {
		global $wpdb;
		$resp = GCO_PD_Install::table( 'responses' );
		$npcc = GCO_PD_Install::table( 'npcc' );
		$used = (int) $wpdb->get_var( $wpdb->prepare( "SELECT (SELECT COUNT(*) FROM {$resp} WHERE force_id = %d) + (SELECT COUNT(*) FROM {$npcc} WHERE force_id = %d)", $id, $id ) );
		if ( $used > 0 ) {
			return new WP_Error( 'gco_pd_force_in_use', 'This force has data. Mark it inactive instead of deleting it.' );
		}
		$wpdb->delete( GCO_PD_Install::table( 'forces' ), array( 'id' => (int) $id ), array( '%d' ) );
		return true;
	}

	/* ------------------------------------------------------- FOI rounds (draft/published) */

	/**
	 * Rounds, newest first.
	 *
	 * @param string $status '', 'draft' or 'published'.
	 * @return object[]
	 */
	public static function get_rounds( $status = '' ) {
		global $wpdb;
		$table = GCO_PD_Install::table( 'rounds' );
		if ( in_array( $status, array( 'draft', 'published' ), true ) ) {
			return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status = %s ORDER BY label DESC", $status ) );
		}
		return (array) $wpdb->get_results( "SELECT * FROM {$table} ORDER BY label DESC" );
	}

	/**
	 * Published rounds in date order (oldest first), for trends.
	 *
	 * @return object[]
	 */
	public static function get_published_rounds() {
		global $wpdb;
		$table = GCO_PD_Install::table( 'rounds' );
		return (array) $wpdb->get_results( "SELECT * FROM {$table} WHERE status = 'published' ORDER BY label ASC" );
	}

	/**
	 * Round by id.
	 *
	 * @param int $id Round id.
	 * @return object|null
	 */
	public static function get_round( $id ) {
		global $wpdb;
		$table = GCO_PD_Install::table( 'rounds' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
	}

	/**
	 * Round by label (YYYY-MM).
	 *
	 * @param string $label Round label.
	 * @return object|null
	 */
	public static function get_round_by_label( $label ) {
		global $wpdb;
		$table = GCO_PD_Install::table( 'rounds' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE label = %s", $label ) );
	}

	/**
	 * Creates a draft round.
	 *
	 * @param string $label YYYY-MM.
	 * @return int|WP_Error New round id.
	 */
	public static function create_round( $label ) {
		global $wpdb;
		$label = trim( (string) $label );
		if ( ! GCO_PD_Validator::is_valid_round_label( $label ) ) {
			return new WP_Error( 'gco_pd_bad_label', 'Round must look like 2026-11 (year, dash, month).' );
		}
		if ( self::get_round_by_label( $label ) ) {
			return new WP_Error( 'gco_pd_duplicate_round', 'A round with that label already exists.' );
		}
		$now = current_time( 'mysql', true );
		$wpdb->insert(
			GCO_PD_Install::table( 'rounds' ),
			array(
				'label'      => $label,
				'status'     => 'draft',
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%s', '%s', '%s', '%s' )
		);
		return $wpdb->insert_id ? (int) $wpdb->insert_id : new WP_Error( 'gco_pd_db', 'Could not create the round.' );
	}

	/**
	 * Publishes or unpublishes a round. The calculator and cache rebuild is triggered by the caller.
	 *
	 * @param int    $id     Round id.
	 * @param string $status 'draft' or 'published'.
	 * @return true|WP_Error
	 */
	public static function set_round_status( $id, $status ) {
		global $wpdb;
		if ( ! in_array( $status, array( 'draft', 'published' ), true ) ) {
			return new WP_Error( 'gco_pd_bad_status', 'Status must be draft or published.' );
		}
		if ( ! self::get_round( $id ) ) {
			return new WP_Error( 'gco_pd_no_round', 'That round does not exist.' );
		}
		$now  = current_time( 'mysql', true );
		$data = array(
			'status'       => $status,
			'published_at' => 'published' === $status ? $now : null,
			'updated_at'   => $now,
		);
		$wpdb->update( GCO_PD_Install::table( 'rounds' ), $data, array( 'id' => (int) $id ), array( '%s', '%s', '%s' ), array( '%d' ) );
		return true;
	}

	/**
	 * Deletes a draft round and its responses.
	 *
	 * @param int $id Round id.
	 * @return true|WP_Error
	 */
	public static function delete_round( $id ) {
		global $wpdb;
		$round = self::get_round( $id );
		if ( ! $round ) {
			return new WP_Error( 'gco_pd_no_round', 'That round does not exist.' );
		}
		if ( 'draft' !== $round->status ) {
			return new WP_Error( 'gco_pd_round_published', 'Only draft rounds can be deleted. Unpublish it first.' );
		}
		$wpdb->delete( GCO_PD_Install::table( 'responses' ), array( 'round_id' => (int) $id ), array( '%d' ) );
		$wpdb->delete( GCO_PD_Install::table( 'rounds' ), array( 'id' => (int) $id ), array( '%d' ) );
		return true;
	}

	/**
	 * Coverage summary for a round: how many forces have a response and how many figures are "not provided".
	 *
	 * @param int $round_id Round id.
	 * @return array{forces_with_data:int,total_forces:int,not_provided:int,figures:int}
	 */
	public static function get_round_summary( $round_id ) {
		global $wpdb;
		$resp   = GCO_PD_Install::table( 'responses' );
		$forces = GCO_PD_Install::table( 'forces' );
		$row    = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS forces_with_data,
					COALESCE(SUM((sg_grant_status = 'not_provided') + (fac_grant_status = 'not_provided') + (sg_renew_status = 'not_provided')
						+ (fac_renew_status = 'not_provided') + (variation_status = 'not_provided')
						+ (sg_holders_status = 'not_provided') + (fac_holders_status = 'not_provided')), 0) AS not_provided
				 FROM {$resp} WHERE round_id = %d",
				$round_id
			)
		);
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$forces} WHERE is_active = 1" );
		return array(
			'forces_with_data' => (int) ( $row->forces_with_data ?? 0 ),
			'total_forces'     => $total,
			'not_provided'     => (int) ( $row->not_provided ?? 0 ),
			'figures'          => (int) ( $row->forces_with_data ?? 0 ) * 7,
		);
	}

	/**
	 * Finds a PDF in the media library by file name (as typed in the template's "PDF File Name" column).
	 *
	 * @param string $filename File name such as "DONE-Y_Derby_Police_11.06.26.pdf".
	 * @return int Attachment id, or 0 when not found.
	 */
	public static function find_pdf_attachment( $filename ) {
		global $wpdb;
		$filename = wp_basename( (string) $filename );
		if ( '' === $filename ) {
			return 0;
		}
		$id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT pm.post_id FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = '_wp_attached_file'
				   AND (pm.meta_value = %s OR pm.meta_value LIKE %s)
				   AND p.post_type = 'attachment' AND p.post_mime_type = 'application/pdf'
				 ORDER BY pm.post_id DESC LIMIT 1",
				$filename,
				'%/' . $wpdb->esc_like( $filename )
			)
		);
		return $id;
	}

	/**
	 * Sets a quarter's data date when it has none yet.
	 *
	 * @param int    $id        Quarter id.
	 * @param string $data_date Y-m-d.
	 */
	public static function set_quarter_date_if_empty( $id, $data_date ) {
		global $wpdb;
		$table = GCO_PD_Install::table( 'npcc_quarters' );
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET data_date = %s WHERE id = %d AND data_date IS NULL", $data_date, $id ) );
	}

	/* ----------------------------------------------------------------- FOI responses */

	/**
	 * Responses for one round, keyed by force id.
	 *
	 * @param int $round_id Round id.
	 * @return array<int,object>
	 */
	public static function get_responses( $round_id ) {
		global $wpdb;
		$table = GCO_PD_Install::table( 'responses' );
		$rows  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE round_id = %d", $round_id ) );
		$out   = array();
		foreach ( $rows as $row ) {
			$out[ (int) $row->force_id ] = $row;
		}
		return $out;
	}

	/**
	 * Inserts or updates one force's response in a round after validating it.
	 *
	 * @param int   $round_id Round id.
	 * @param int   $force_id Force id.
	 * @param array $row      Raw row (see GCO_PD_Validator::validate_response()).
	 * @return int|WP_Error Response id; WP_Error carries the per-field errors in its data.
	 */
	public static function save_response( $round_id, $force_id, array $row ) {
		global $wpdb;
		if ( ! self::get_round( $round_id ) ) {
			return new WP_Error( 'gco_pd_no_round', 'That round does not exist.' );
		}
		if ( ! self::get_force( $force_id ) ) {
			return new WP_Error( 'gco_pd_no_force', 'That force does not exist.' );
		}

		$result = GCO_PD_Validator::validate_response( $row );
		if ( ! empty( $result['errors'] ) ) {
			return new WP_Error( 'gco_pd_invalid', 'Some figures are not valid.', $result['errors'] );
		}

		$data                = $result['clean'];
		$data['round_id']    = (int) $round_id;
		$data['force_id']    = (int) $force_id;
		$data['updated_by']  = get_current_user_id();
		$data['updated_at']  = current_time( 'mysql', true );

		$table = GCO_PD_Install::table( 'responses' );
		$id    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE round_id = %d AND force_id = %d", $round_id, $force_id ) );

		if ( $id ) {
			return false === $wpdb->update( $table, $data, array( 'id' => $id ) ) ? new WP_Error( 'gco_pd_db', 'Could not save the response.' ) : $id;
		}
		$wpdb->insert( $table, $data );
		return $wpdb->insert_id ? (int) $wpdb->insert_id : new WP_Error( 'gco_pd_db', 'Could not save the response.' );
	}

	/**
	 * Deletes one force's response from a round.
	 *
	 * @param int $round_id Round id.
	 * @param int $force_id Force id.
	 */
	public static function delete_response( $round_id, $force_id ) {
		global $wpdb;
		$wpdb->delete( GCO_PD_Install::table( 'responses' ), array( 'round_id' => (int) $round_id, 'force_id' => (int) $force_id ), array( '%d', '%d' ) );
	}

	/**
	 * Responses for all published rounds in one query, oldest round first (feeds the calculator).
	 *
	 * @return object[] Rows joined with the round label.
	 */
	public static function get_published_responses() {
		global $wpdb;
		$resp   = GCO_PD_Install::table( 'responses' );
		$rounds = GCO_PD_Install::table( 'rounds' );
		return (array) $wpdb->get_results(
			"SELECT r.*, rd.label AS round_label, rd.published_at AS round_published_at
			 FROM {$resp} r INNER JOIN {$rounds} rd ON rd.id = r.round_id
			 WHERE rd.status = 'published'
			 ORDER BY rd.label ASC, r.force_id ASC"
		);
	}

	/* ------------------------------------------------------------------ NPCC quarters */

	/**
	 * Quarters, newest first.
	 *
	 * @return object[]
	 */
	public static function get_quarters() {
		global $wpdb;
		$table = GCO_PD_Install::table( 'npcc_quarters' );
		return (array) $wpdb->get_results( "SELECT * FROM {$table} ORDER BY data_date DESC, label DESC" );
	}

	/**
	 * Published quarters, oldest first.
	 *
	 * @return object[]
	 */
	public static function get_published_quarters() {
		global $wpdb;
		$table = GCO_PD_Install::table( 'npcc_quarters' );
		return (array) $wpdb->get_results( "SELECT * FROM {$table} WHERE status = 'published' ORDER BY data_date ASC, label ASC" );
	}

	/**
	 * Quarter by id.
	 *
	 * @param int $id Quarter id.
	 * @return object|null
	 */
	public static function get_quarter( $id ) {
		global $wpdb;
		$table = GCO_PD_Install::table( 'npcc_quarters' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
	}

	/**
	 * Quarter by label ("2025/26 Q4").
	 *
	 * @param string $label Quarter label.
	 * @return object|null
	 */
	public static function get_quarter_by_label( $label ) {
		global $wpdb;
		$table = GCO_PD_Install::table( 'npcc_quarters' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE label = %s", $label ) );
	}

	/**
	 * Creates a draft NPCC quarter.
	 *
	 * @param string      $label     "2025/26 Q4".
	 * @param string|null $data_date Y-m-d the NPCC data was drawn.
	 * @return int|WP_Error New quarter id.
	 */
	public static function create_quarter( $label, $data_date = null ) {
		global $wpdb;
		$result = GCO_PD_Validator::validate_quarter(
			array(
				'label'     => $label,
				'data_date' => $data_date,
			)
		);
		if ( ! empty( $result['errors'] ) ) {
			return new WP_Error( 'gco_pd_invalid', implode( ' ', $result['errors'] ), $result['errors'] );
		}
		if ( self::get_quarter_by_label( $result['clean']['label'] ) ) {
			return new WP_Error( 'gco_pd_duplicate_quarter', 'A quarter with that label already exists.' );
		}
		$wpdb->insert(
			GCO_PD_Install::table( 'npcc_quarters' ),
			array(
				'label'     => $result['clean']['label'],
				'data_date' => $result['clean']['data_date'],
				'status'    => 'draft',
			),
			array( '%s', '%s', '%s' )
		);
		return $wpdb->insert_id ? (int) $wpdb->insert_id : new WP_Error( 'gco_pd_db', 'Could not create the quarter.' );
	}

	/**
	 * Publishes or unpublishes a quarter.
	 *
	 * @param int    $id     Quarter id.
	 * @param string $status 'draft' or 'published'.
	 * @return true|WP_Error
	 */
	public static function set_quarter_status( $id, $status ) {
		global $wpdb;
		if ( ! in_array( $status, array( 'draft', 'published' ), true ) ) {
			return new WP_Error( 'gco_pd_bad_status', 'Status must be draft or published.' );
		}
		if ( ! self::get_quarter( $id ) ) {
			return new WP_Error( 'gco_pd_no_quarter', 'That quarter does not exist.' );
		}
		$wpdb->update(
			GCO_PD_Install::table( 'npcc_quarters' ),
			array(
				'status'       => $status,
				'published_at' => 'published' === $status ? current_time( 'mysql', true ) : null,
			),
			array( 'id' => (int) $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
		return true;
	}

	/**
	 * Deletes a draft quarter and its rows.
	 *
	 * @param int $id Quarter id.
	 * @return true|WP_Error
	 */
	public static function delete_quarter( $id ) {
		global $wpdb;
		$quarter = self::get_quarter( $id );
		if ( ! $quarter ) {
			return new WP_Error( 'gco_pd_no_quarter', 'That quarter does not exist.' );
		}
		if ( 'draft' !== $quarter->status ) {
			return new WP_Error( 'gco_pd_quarter_published', 'Only draft quarters can be deleted. Unpublish it first.' );
		}
		$wpdb->delete( GCO_PD_Install::table( 'npcc' ), array( 'quarter_id' => (int) $id ), array( '%d' ) );
		$wpdb->delete( GCO_PD_Install::table( 'npcc_quarters' ), array( 'id' => (int) $id ), array( '%d' ) );
		return true;
	}

	/* --------------------------------------------------------------------- NPCC rows */

	/**
	 * NPCC rows for one quarter, keyed by force id.
	 *
	 * @param int $quarter_id Quarter id.
	 * @return array<int,object>
	 */
	public static function get_npcc_rows( $quarter_id ) {
		global $wpdb;
		$table = GCO_PD_Install::table( 'npcc' );
		$rows  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE quarter_id = %d", $quarter_id ) );
		$out   = array();
		foreach ( $rows as $row ) {
			$out[ (int) $row->force_id ] = $row;
		}
		return $out;
	}

	/**
	 * NPCC rows for all published quarters in one query, oldest quarter first (feeds the calculator).
	 *
	 * @return object[]
	 */
	public static function get_published_npcc_rows() {
		global $wpdb;
		$npcc     = GCO_PD_Install::table( 'npcc' );
		$quarters = GCO_PD_Install::table( 'npcc_quarters' );
		return (array) $wpdb->get_results(
			"SELECT n.*, q.label AS quarter_label, q.data_date AS quarter_data_date
			 FROM {$npcc} n INNER JOIN {$quarters} q ON q.id = n.quarter_id
			 WHERE q.status = 'published'
			 ORDER BY q.data_date ASC, q.label ASC, n.force_id ASC"
		);
	}

	/**
	 * Inserts or updates one force's NPCC row in a quarter after validating it.
	 *
	 * @param int   $quarter_id Quarter id.
	 * @param int   $force_id   Force id.
	 * @param array $row        Raw row (see GCO_PD_Validator::validate_npcc()).
	 * @return true|WP_Error
	 */
	public static function save_npcc_row( $quarter_id, $force_id, array $row ) {
		global $wpdb;
		if ( ! self::get_quarter( $quarter_id ) ) {
			return new WP_Error( 'gco_pd_no_quarter', 'That quarter does not exist.' );
		}
		if ( ! self::get_force( $force_id ) ) {
			return new WP_Error( 'gco_pd_no_force', 'That force does not exist.' );
		}

		$result = GCO_PD_Validator::validate_npcc( $row );
		if ( ! empty( $result['errors'] ) ) {
			return new WP_Error( 'gco_pd_invalid', 'Some figures are not valid.', $result['errors'] );
		}

		$data               = $result['clean'];
		$data['quarter_id'] = (int) $quarter_id;
		$data['force_id']   = (int) $force_id;

		$table  = GCO_PD_Install::table( 'npcc' );
		$exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE quarter_id = %d AND force_id = %d", $quarter_id, $force_id ) );

		if ( $exists ) {
			$ok = $wpdb->update( $table, $data, array( 'quarter_id' => (int) $quarter_id, 'force_id' => (int) $force_id ) );
		} else {
			$ok = $wpdb->insert( $table, $data );
		}
		return false === $ok ? new WP_Error( 'gco_pd_db', 'Could not save the NPCC row.' ) : true;
	}
}
