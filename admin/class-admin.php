<?php
/**
 * Admin: menus, screens and form handlers (WP Admin > Police Data).
 *
 * Every screen and every handler requires the gco_manage_police_data capability; every handler is
 * POST-only and verifies a nonce. Drafts are edited freely; published rounds/quarters are read-only
 * until unpublished, so live pages never change by accident.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class GCO_PD_Admin
 */
final class GCO_PD_Admin {

	const SLUG_ROUNDS   = 'gco-police-data';
	const SLUG_ROUND    = 'gco-pd-round';
	const SLUG_QUARTERS = 'gco-pd-quarters';
	const SLUG_IMPORT   = 'gco-pd-import';
	const SLUG_FORCES   = 'gco-pd-forces';
	const SLUG_SETTINGS = 'gco-pd-settings';
	const SLUG_PAGES    = 'gco-pd-force-pages';
	const SLUG_PROFILE  = 'gco-pd-force-profile';

	/**
	 * Handlers: action name => method.
	 *
	 * @var array<string,string>
	 */
	const HANDLERS = array(
		'gco_pd_create_round'   => 'handle_create_round',
		'gco_pd_round_status'   => 'handle_round_status',
		'gco_pd_delete_round'   => 'handle_delete_round',
		'gco_pd_save_round'     => 'handle_save_round',
		'gco_pd_create_quarter' => 'handle_create_quarter',
		'gco_pd_quarter_status' => 'handle_quarter_status',
		'gco_pd_delete_quarter' => 'handle_delete_quarter',
		'gco_pd_save_forces'    => 'handle_save_forces',
		'gco_pd_save_profile'   => 'handle_save_profile',
		'gco_pd_save_shared'    => 'handle_save_shared',
		'gco_pd_import_upload'  => 'handle_import_upload',
		'gco_pd_import_commit'  => 'handle_import_commit',
		'gco_pd_import_cancel'  => 'handle_import_cancel',
		'gco_pd_save_settings'  => 'handle_save_settings',
		'gco_pd_export_foi'     => 'handle_export_foi',
		'gco_pd_export_npcc'    => 'handle_export_npcc',
	);

	/**
	 * Registers hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_head', array( __CLASS__, 'hide_editor_pages' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		foreach ( self::HANDLERS as $action => $method ) {
			add_action( 'admin_post_' . $action, array( __CLASS__, $method ) );
		}
	}

	/* ---------------------------------------------------------------------- Menu */

	/**
	 * Adds the Police Data menu.
	 */
	public static function register_menu() {
		$cap = GCO_PD_Install::CAPABILITY;
		add_menu_page( 'Police Data', 'Police Data', $cap, self::SLUG_ROUNDS, array( __CLASS__, 'page_rounds' ), 'dashicons-shield', 58 );
		add_submenu_page( self::SLUG_ROUNDS, 'FOI Rounds', 'FOI Rounds', $cap, self::SLUG_ROUNDS, array( __CLASS__, 'page_rounds' ) );
		add_submenu_page( self::SLUG_ROUNDS, 'NPCC Quarters', 'NPCC Quarters', $cap, self::SLUG_QUARTERS, array( __CLASS__, 'page_quarters' ) );
		add_submenu_page( self::SLUG_ROUNDS, 'Import', 'Import', $cap, self::SLUG_IMPORT, array( __CLASS__, 'page_import' ) );
		add_submenu_page( self::SLUG_ROUNDS, 'Forces', 'Forces', $cap, self::SLUG_FORCES, array( __CLASS__, 'page_forces' ) );
		add_submenu_page( self::SLUG_ROUNDS, 'Force Pages', 'Force Pages', $cap, self::SLUG_PAGES, array( __CLASS__, 'page_force_pages' ) );
		add_submenu_page( self::SLUG_ROUNDS, 'Edit force page', 'Edit force page', $cap, self::SLUG_PROFILE, array( __CLASS__, 'page_profile' ) );
		add_submenu_page( self::SLUG_ROUNDS, 'Settings', 'Settings', $cap, self::SLUG_SETTINGS, array( __CLASS__, 'page_settings' ) );
		// The round editor is reachable by link only; it stays out of the menu.
		add_submenu_page( self::SLUG_ROUNDS, 'Edit round', 'Edit round', $cap, self::SLUG_ROUND, array( __CLASS__, 'page_round' ) );
	}

	/**
	 * Keeps the editor screens out of the menu. This runs on admin_head, after WordPress has checked
	 * access to the page: removing them in admin_menu also removes them from that check and gives
	 * "Sorry, you are not allowed to access this page".
	 */
	public static function hide_editor_pages() {
		remove_submenu_page( self::SLUG_ROUNDS, self::SLUG_ROUND );
		remove_submenu_page( self::SLUG_ROUNDS, self::SLUG_PROFILE );
	}

	/**
	 * Loads CSS/JS on this plugin's screens only.
	 */
	public static function enqueue() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 0 !== strpos( $page, 'gco-' ) && self::SLUG_ROUNDS !== $page ) {
			return;
		}
		wp_enqueue_style( 'gco-pd-admin', GCO_PD_URL . 'assets/admin.css', array(), GCO_PD_VERSION );
		if ( self::SLUG_PROFILE === $page || self::SLUG_PAGES === $page ) {
			wp_enqueue_media();
			wp_enqueue_script( 'gco-pd-profile', GCO_PD_URL . 'assets/admin-profile.js', array(), GCO_PD_VERSION, true );
		}
		if ( self::SLUG_ROUND === $page ) {
			wp_enqueue_media();
			wp_enqueue_script( 'gco-pd-admin', GCO_PD_URL . 'assets/admin.js', array(), GCO_PD_VERSION, true );
		}
	}

	/* ------------------------------------------------------------------- Helpers */

	/**
	 * Admin URL for one of our screens.
	 *
	 * @param string $page Slug.
	 * @param array  $args Extra query args.
	 * @return string
	 */
	public static function url( $page, array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Stops unless the user may manage police data (screens).
	 */
	private static function require_cap() {
		if ( ! current_user_can( GCO_PD_Install::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to manage police data.', 'gco-police-data' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Common guard for form handlers: capability, POST, nonce.
	 *
	 * @param string $action Action / nonce name.
	 */
	private static function guard( $action ) {
		self::require_cap();
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			wp_die( esc_html__( 'Invalid request.', 'gco-police-data' ), '', array( 'response' => 405 ) );
		}
		check_admin_referer( $action );
	}

	/**
	 * Queues a notice for the next screen load.
	 *
	 * @param string $type    success|error|warning|info.
	 * @param string $message Plain text.
	 */
	private static function notice( $type, $message ) {
		$key     = 'gco_pd_notices_' . get_current_user_id();
		$notices = get_transient( $key );
		$notices = is_array( $notices ) ? $notices : array();
		$notices[] = array( $type, $message );
		set_transient( $key, $notices, 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * Prints and clears queued notices.
	 */
	private static function print_notices() {
		$key     = 'gco_pd_notices_' . get_current_user_id();
		$notices = get_transient( $key );
		delete_transient( $key );
		foreach ( is_array( $notices ) ? $notices : array() as $notice ) {
			printf( '<div class="notice notice-%s"><p>%s</p></div>', esc_attr( $notice[0] ), esc_html( $notice[1] ) );
		}
	}

	/**
	 * Redirects to one of our screens and stops.
	 *
	 * @param string $page Slug.
	 * @param array  $args Query args.
	 */
	private static function go( $page, array $args = array() ) {
		wp_safe_redirect( self::url( $page, $args ) );
		exit;
	}

	/**
	 * Reads a trimmed text field from POST.
	 *
	 * @param string $key Field.
	 * @return string
	 */
	private static function post_text( $key ) {
		return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- guard() ran.
	}

	/**
	 * Reads an integer field from POST.
	 *
	 * @param string $key Field.
	 * @return int
	 */
	private static function post_int( $key ) {
		return isset( $_POST[ $key ] ) ? absint( wp_unslash( $_POST[ $key ] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- guard() ran.
	}

	/**
	 * Tells the rest of the plugin that published data may have changed (the calculator rebuilds in M3).
	 */
	private static function data_changed() {
		do_action( 'gco_pd_data_changed' );
	}

	/**
	 * Includes a view with variables.
	 *
	 * @param string $view Name under admin/views/.
	 * @param array  $vars Variables.
	 */
	private static function render( $view, array $vars = array() ) {
		self::require_cap();
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		extract( $vars, EXTR_SKIP );
		echo '<div class="wrap gco-pd">';
		self::print_notices();
		include GCO_PD_DIR . 'admin/views/' . $view . '.php';
		echo '</div>';
	}

	/* ------------------------------------------------------------------- Screens */

	/**
	 * FOI rounds list.
	 */
	public static function page_rounds() {
		$rounds = GCO_PD_Repository::get_rounds();
		$stats  = array();
		foreach ( $rounds as $round ) {
			$stats[ (int) $round->id ] = GCO_PD_Repository::get_round_summary( (int) $round->id );
		}
		self::render( 'rounds', compact( 'rounds', 'stats' ) );
	}

	/**
	 * Round editor grid.
	 */
	public static function page_round() {
		self::require_cap();
		$round_id = isset( $_GET['round'] ) ? absint( wp_unslash( $_GET['round'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$round    = $round_id ? GCO_PD_Repository::get_round( $round_id ) : null;
		if ( ! $round ) {
			// Headers are already sent on a screen, so show the problem instead of redirecting.
			printf(
				'<div class="wrap"><div class="notice notice-error"><p>%s</p></div><p><a href="%s">&larr; All rounds</a></p></div>',
				esc_html__( 'That round does not exist.', 'gco-police-data' ),
				esc_url( self::url( self::SLUG_ROUNDS ) )
			);
			return;
		}

		$responses = GCO_PD_Repository::get_responses( $round_id );
		$forces    = array();
		foreach ( GCO_PD_Repository::get_forces() as $force ) {
			if ( $force->is_active || isset( $responses[ (int) $force->id ] ) ) {
				$forces[] = $force;
			}
		}

		// Rows that failed validation on the last save are shown again exactly as typed.
		$grid_key = 'gco_pd_grid_' . get_current_user_id() . '_' . $round_id;
		$failed   = get_transient( $grid_key );
		delete_transient( $grid_key );
		$failed = is_array( $failed ) ? $failed : array(
			'errors' => array(),
			'rows'   => array(),
		);

		self::render( 'round-edit', compact( 'round', 'forces', 'responses', 'failed' ) );
	}

	/**
	 * NPCC quarters list.
	 */
	public static function page_quarters() {
		$quarters = GCO_PD_Repository::get_quarters();
		$counts   = array();
		foreach ( $quarters as $quarter ) {
			$counts[ (int) $quarter->id ] = count( GCO_PD_Repository::get_npcc_rows( (int) $quarter->id ) );
		}
		self::render( 'quarters', compact( 'quarters', 'counts' ) );
	}

	/**
	 * Import screen (upload, then preview).
	 */
	public static function page_import() {
		self::require_cap();
		$token   = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$preview = $token ? GCO_PD_Importer::get_preview( $token ) : null;
		if ( $token && ! $preview ) {
			self::notice( 'warning', 'That preview has expired. Upload the file again.' );
		}
		self::render( 'import', compact( 'token', 'preview' ) );
	}

	/**
	 * Forces screen.
	 */
	public static function page_forces() {
		self::require_cap();
		$pages = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => 500,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		self::render(
			'forces',
			array(
				'forces' => GCO_PD_Repository::get_forces(),
				'pages'  => $pages,
			)
		);
	}

	/**
	 * Settings screen.
	 */
	public static function page_settings() {
		self::render(
			'settings',
			array(
				'settings' => GCO_PD_Settings::all(),
				'pages'    => get_posts(
					array(
						'post_type'      => 'page',
						'post_status'    => 'publish',
						'posts_per_page' => 500,
						'orderby'        => 'title',
						'order'          => 'ASC',
						'no_found_rows'  => true,
					)
				),
			)
		);
	}

	/* ------------------------------------------------------------------- Handlers */

	/**
	 * Creates a draft FOI round.
	 */
	public static function handle_create_round() {
		self::guard( 'gco_pd_create_round' );
		$id = GCO_PD_Repository::create_round( self::post_text( 'label' ) );
		if ( is_wp_error( $id ) ) {
			self::notice( 'error', $id->get_error_message() );
			self::go( self::SLUG_ROUNDS );
		}
		self::notice( 'success', 'Draft round created. Enter the figures below, or use Import.' );
		self::go( self::SLUG_ROUND, array( 'round' => $id ) );
	}

	/**
	 * Publishes or unpublishes a round.
	 */
	public static function handle_round_status() {
		self::guard( 'gco_pd_round_status' );
		$id      = self::post_int( 'id' );
		$publish = 'publish' === self::post_text( 'to' );
		$round   = GCO_PD_Repository::get_round( $id );
		if ( ! $round ) {
			self::notice( 'error', 'That round does not exist.' );
			self::go( self::SLUG_ROUNDS );
		}

		$result = GCO_PD_Repository::set_round_status( $id, $publish ? 'published' : 'draft' );
		if ( is_wp_error( $result ) ) {
			self::notice( 'error', $result->get_error_message() );
		} else {
			self::data_changed();
			if ( $publish ) {
				$summary = GCO_PD_Repository::get_round_summary( $id );
				self::notice(
					'success',
					sprintf(
						'Round %1$s published: %2$d of %3$d forces have data, %4$d figures not provided.',
						$round->label,
						$summary['forces_with_data'],
						$summary['total_forces'],
						$summary['not_provided']
					)
				);
			} else {
				self::notice( 'success', sprintf( 'Round %s is a draft again and is no longer shown on the site.', $round->label ) );
			}
		}
		self::go( self::SLUG_ROUNDS );
	}

	/**
	 * Deletes a draft round.
	 */
	public static function handle_delete_round() {
		self::guard( 'gco_pd_delete_round' );
		$result = GCO_PD_Repository::delete_round( self::post_int( 'id' ) );
		self::notice( is_wp_error( $result ) ? 'error' : 'success', is_wp_error( $result ) ? $result->get_error_message() : 'Draft round deleted.' );
		self::go( self::SLUG_ROUNDS );
	}

	/**
	 * Saves the round editor grid. The whole grid arrives as one JSON field so a 43-row form never
	 * runs into PHP's max_input_vars limit.
	 */
	public static function handle_save_round() {
		self::guard( 'gco_pd_save_round' );
		$round_id = self::post_int( 'round' );
		$round    = GCO_PD_Repository::get_round( $round_id );
		if ( ! $round || 'draft' !== $round->status ) {
			self::notice( 'error', 'Only draft rounds can be edited. Unpublish the round first.' );
			self::go( self::SLUG_ROUNDS );
		}

		$raw = isset( $_POST['payload'] ) ? wp_unslash( $_POST['payload'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- decoded and validated per field below.
		if ( ! is_string( $raw ) || strlen( $raw ) > 1048576 ) {
			self::notice( 'error', 'The submitted data was too large or malformed.' );
			self::go( self::SLUG_ROUND, array( 'round' => $round_id ) );
		}
		$payload = json_decode( $raw, true );
		if ( ! is_array( $payload ) || count( $payload ) > 100 ) {
			self::notice( 'error', 'The submitted data could not be read.' );
			self::go( self::SLUG_ROUND, array( 'round' => $round_id ) );
		}

		$saved    = 0;
		$deleted  = 0;
		$warnings = array();
		$failed   = array(
			'errors' => array(),
			'rows'   => array(),
		);

		$existing = GCO_PD_Repository::get_responses( $round_id );

		foreach ( $payload as $force_id => $row ) {
			$force_id = absint( $force_id );
			$force    = $force_id ? GCO_PD_Repository::get_force( $force_id ) : null;
			if ( ! $force || ! is_array( $row ) ) {
				continue;
			}
			$row = self::clean_grid_row( $row );

			if ( self::is_empty_row( $row ) ) {
				// A completely blank row means "no response from this force this round".
				if ( isset( $existing[ $force_id ] ) ) {
					GCO_PD_Repository::delete_response( $round_id, $force_id );
					++$deleted;
				}
				continue;
			}

			if ( ! empty( $row['pdf_id'] ) && 'application/pdf' !== get_post_mime_type( (int) $row['pdf_id'] ) ) {
				$failed['errors'][ $force_id ] = array( 'pdf_id' => 'That attachment is not a PDF.' );
				$failed['rows'][ $force_id ]   = $row;
				continue;
			}

			$check = GCO_PD_Validator::validate_response( $row );
			if ( $check['errors'] ) {
				$failed['errors'][ $force_id ] = $check['errors'];
				$failed['rows'][ $force_id ]   = $row;
				continue;
			}
			foreach ( $check['warnings'] as $message ) {
				$warnings[] = $force->name . ': ' . $message;
			}

			$result = GCO_PD_Repository::save_response( $round_id, $force_id, $row );
			if ( is_wp_error( $result ) ) {
				$failed['errors'][ $force_id ] = array( 'row' => $result->get_error_message() );
				$failed['rows'][ $force_id ]   = $row;
			} else {
				++$saved;
			}
		}

		if ( $failed['errors'] ) {
			set_transient( 'gco_pd_grid_' . get_current_user_id() . '_' . $round_id, $failed, 10 * MINUTE_IN_SECONDS );
			self::notice( 'error', sprintf( '%d row(s) have problems and were NOT saved (highlighted below). %d row(s) saved.', count( $failed['errors'] ), $saved ) );
		} else {
			self::notice( 'success', sprintf( 'Saved %1$d row(s)%2$s.', $saved, $deleted ? sprintf( ', cleared %d', $deleted ) : '' ) );
		}
		foreach ( array_slice( $warnings, 0, 10 ) as $message ) {
			self::notice( 'warning', $message );
		}
		self::go( self::SLUG_ROUND, array( 'round' => $round_id ) );
	}

	/**
	 * Sanitises one submitted grid row.
	 *
	 * @param array $row Decoded JSON row.
	 * @return array
	 */
	private static function clean_grid_row( array $row ) {
		$clean = array();
		foreach ( GCO_PD_Validator::FOI_METRICS as $meta ) {
			$clean[ $meta['column'] ] = isset( $row[ $meta['column'] ] ) ? sanitize_text_field( (string) $row[ $meta['column'] ] ) : '';
			$clean[ $meta['status'] ] = isset( $row[ $meta['status'] ] ) ? sanitize_text_field( (string) $row[ $meta['status'] ] ) : '';
		}
		$clean['foi_ref']       = isset( $row['foi_ref'] ) ? sanitize_text_field( (string) $row['foi_ref'] ) : '';
		$clean['response_date'] = isset( $row['response_date'] ) ? sanitize_text_field( (string) $row['response_date'] ) : '';
		$clean['notes']         = isset( $row['notes'] ) ? wp_kses_post( (string) $row['notes'] ) : '';
		$clean['pdf_id']        = isset( $row['pdf_id'] ) ? absint( $row['pdf_id'] ) : 0;
		return $clean;
	}

	/**
	 * True when a grid row has nothing in it (all blank, all "not provided", no reference/date/notes/PDF).
	 *
	 * @param array $row Cleaned row.
	 * @return bool
	 */
	private static function is_empty_row( array $row ) {
		foreach ( GCO_PD_Validator::FOI_METRICS as $meta ) {
			if ( '' !== $row[ $meta['column'] ] ) {
				return false;
			}
			if ( ! in_array( GCO_PD_Validator::normalize_status( $row[ $meta['status'] ] ), array( null, GCO_PD_Validator::STATUS_NOT_PROVIDED ), true ) ) {
				return false;
			}
		}
		return '' === $row['foi_ref'] && '' === $row['response_date'] && '' === trim( $row['notes'] ) && ! $row['pdf_id'];
	}

	/**
	 * Creates a draft NPCC quarter.
	 */
	public static function handle_create_quarter() {
		self::guard( 'gco_pd_create_quarter' );
		$id = GCO_PD_Repository::create_quarter( self::post_text( 'label' ), self::post_text( 'data_date' ) );
		if ( is_wp_error( $id ) ) {
			self::notice( 'error', $id->get_error_message() );
		} else {
			self::notice( 'success', 'Draft quarter created. Use Import to add the 43 force rows.' );
		}
		self::go( self::SLUG_QUARTERS );
	}

	/**
	 * Publishes or unpublishes a quarter.
	 */
	public static function handle_quarter_status() {
		self::guard( 'gco_pd_quarter_status' );
		$id      = self::post_int( 'id' );
		$publish = 'publish' === self::post_text( 'to' );
		$quarter = GCO_PD_Repository::get_quarter( $id );
		if ( ! $quarter ) {
			self::notice( 'error', 'That quarter does not exist.' );
			self::go( self::SLUG_QUARTERS );
		}
		if ( $publish && ! GCO_PD_Repository::get_npcc_rows( $id ) ) {
			self::notice( 'error', 'This quarter has no force rows yet. Import the NPCC data first.' );
			self::go( self::SLUG_QUARTERS );
		}
		$result = GCO_PD_Repository::set_quarter_status( $id, $publish ? 'published' : 'draft' );
		if ( is_wp_error( $result ) ) {
			self::notice( 'error', $result->get_error_message() );
		} else {
			self::data_changed();
			self::notice(
				'success',
				$publish
					? sprintf( 'Quarter %1$s published with %2$d forces.', $quarter->label, count( GCO_PD_Repository::get_npcc_rows( $id ) ) )
					: sprintf( 'Quarter %s is a draft again and is no longer shown on the site.', $quarter->label )
			);
		}
		self::go( self::SLUG_QUARTERS );
	}

	/**
	 * Deletes a draft quarter.
	 */
	public static function handle_delete_quarter() {
		self::guard( 'gco_pd_delete_quarter' );
		$result = GCO_PD_Repository::delete_quarter( self::post_int( 'id' ) );
		self::notice( is_wp_error( $result ) ? 'error' : 'success', is_wp_error( $result ) ? $result->get_error_message() : 'Draft quarter deleted.' );
		self::go( self::SLUG_QUARTERS );
	}

	/**
	 * Saves the Forces screen (all rows at once; about 170 fields, well under max_input_vars).
	 */
	public static function handle_save_forces() {
		self::guard( 'gco_pd_save_forces' );
		$posted = isset( $_POST['force'] ) && is_array( $_POST['force'] ) ? wp_unslash( $_POST['force'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each field sanitised below.
		$errors = array();
		$saved  = 0;

		foreach ( array_slice( $posted, 0, 200, true ) as $id => $fields ) {
			$id = absint( $id );
			if ( ! $id || ! is_array( $fields ) ) {
				continue;
			}
			$result = GCO_PD_Repository::update_force(
				$id,
				array(
					'name'      => sanitize_text_field( $fields['name'] ?? '' ),
					'aliases'   => sanitize_text_field( $fields['aliases'] ?? '' ),
					'page_id'   => absint( $fields['page_id'] ?? 0 ),
					'is_active' => ! empty( $fields['is_active'] ),
				)
			);
			if ( is_wp_error( $result ) ) {
				$errors[] = $result->get_error_message();
			} else {
				++$saved;
			}
		}

		self::data_changed();
		if ( $errors ) {
			self::notice( 'error', implode( ' ', array_unique( $errors ) ) );
		}
		self::notice( 'success', sprintf( 'Saved %d forces.', $saved ) );
		self::go( self::SLUG_FORCES );
	}

	/**
	 * Receives the upload and builds the preview.
	 */
	public static function handle_import_upload() {
		self::guard( 'gco_pd_import_upload' );
		if ( empty( $_FILES['file'] ) || ! is_array( $_FILES['file'] ) ) {
			self::notice( 'error', 'Choose a file to upload.' );
			self::go( self::SLUG_IMPORT );
		}
		$file = $_FILES['file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated by GCO_PD_Importer::check_upload().
		$ext  = GCO_PD_Importer::check_upload( $file );
		if ( is_wp_error( $ext ) ) {
			self::notice( 'error', $ext->get_error_message() );
			self::go( self::SLUG_IMPORT );
		}

		$name    = sanitize_file_name( wp_unslash( (string) $file['name'] ) );
		$preview = GCO_PD_Importer::build_preview( $file['tmp_name'], $ext, $name );
		if ( is_wp_error( $preview ) ) {
			self::notice( 'error', $preview->get_error_message() );
			self::go( self::SLUG_IMPORT );
		}
		self::go( self::SLUG_IMPORT, array( 'token' => GCO_PD_Importer::store_preview( $preview ) ) );
	}

	/**
	 * Writes the previewed rows to draft data.
	 */
	public static function handle_import_commit() {
		self::guard( 'gco_pd_import_commit' );
		$token   = self::post_text( 'token' );
		$preview = GCO_PD_Importer::get_preview( $token );
		if ( ! $preview ) {
			self::notice( 'error', 'That preview has expired. Upload the file again.' );
			self::go( self::SLUG_IMPORT );
		}
		$summary = GCO_PD_Importer::commit( $preview );
		GCO_PD_Importer::delete_preview( $token );

		$message = sprintf(
			'Import finished: %1$d FOI rows and %2$d NPCC rows saved as drafts.',
			$summary['foi_saved'],
			$summary['npcc_saved']
		);
		if ( $summary['rounds_created'] ) {
			$message .= ' New round(s): ' . implode( ', ', $summary['rounds_created'] ) . '.';
		}
		if ( $summary['quarters_created'] ) {
			$message .= ' New quarter(s): ' . implode( ', ', $summary['quarters_created'] ) . '.';
		}
		if ( $summary['skipped'] || $summary['failed'] ) {
			$message .= sprintf( ' %d row(s) could not be saved.', $summary['skipped'] + $summary['failed'] );
		}
		self::notice( $summary['failed'] || $summary['skipped'] ? 'warning' : 'success', $message . ' Nothing is live until you publish.' );
		self::go( $summary['npcc_saved'] && ! $summary['foi_saved'] ? self::SLUG_QUARTERS : self::SLUG_ROUNDS );
	}

	/**
	 * Throws a preview away.
	 */
	public static function handle_import_cancel() {
		self::guard( 'gco_pd_import_cancel' );
		GCO_PD_Importer::delete_preview( self::post_text( 'token' ) );
		self::notice( 'info', 'Import cancelled. Nothing was changed.' );
		self::go( self::SLUG_IMPORT );
	}

	/**
	 * Saves settings.
	 */
	public static function handle_save_settings() {
		self::guard( 'gco_pd_save_settings' );
		GCO_PD_Settings::save_from_input( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- guard() ran; only checkbox presence is read.
		self::data_changed();
		self::notice( 'success', 'Settings saved.' );
		self::go( self::SLUG_SETTINGS );
	}

	/**
	 * Streams an FOI export (one round or all).
	 */
	public static function handle_export_foi() {
		self::guard( 'gco_pd_export_foi' );
		$id = self::post_int( 'id' );
		self::download( $id ? 'gco-foi-round-' . ( GCO_PD_Repository::get_round( $id )->label ?? $id ) : 'gco-foi-all-rounds', GCO_PD_Exporter::foi_csv( $id ? array( $id ) : array() ) );
	}

	/**
	 * Streams an NPCC export (one quarter or all).
	 */
	public static function handle_export_npcc() {
		self::guard( 'gco_pd_export_npcc' );
		$id      = self::post_int( 'id' );
		$quarter = $id ? GCO_PD_Repository::get_quarter( $id ) : null;
		self::download( $quarter ? 'gco-npcc-' . $quarter->label : 'gco-npcc-all-quarters', GCO_PD_Exporter::npcc_csv( $id ? array( $id ) : array() ) );
	}

	/**
	 * Sends a CSV download and stops.
	 *
	 * @param string $name Base file name.
	 * @param string $csv  Content.
	 */
	private static function download( $name, $csv ) {
		$filename = sanitize_file_name( str_replace( '/', '-', $name ) . '-' . gmdate( 'Ymd' ) . '.csv' );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV, formula-neutralised by GCO_PD_Exporter.
		exit;
	}

	/* ------------------------------------------------------------- Force pages */

	/**
	 * Force Pages: every force with its page, template status and profile completeness, plus the
	 * shared defaults used on every force page.
	 */
	public static function page_force_pages() {
		self::require_cap();
		$forces   = GCO_PD_Repository::get_forces();
		$profiles = GCO_PD_Profile::all();
		$rows     = array();
		foreach ( $forces as $force ) {
			$page_id = (int) $force->page_id;
			$content = $page_id ? (string) get_post_field( 'post_content', $page_id ) : '';
			$profile = $profiles[ (int) $force->id ] ?? GCO_PD_Profile::empty_profile();
			$rows[]  = array(
				'force'    => $force,
				'page_id'  => $page_id,
				'status'   => $page_id ? get_post_status( $page_id ) : '',
				'template' => $page_id && has_shortcode( $content, 'gco_force_page' ),
				'filled'   => GCO_PD_Profile::completeness( $profile ),
				'updated'  => $profile['_updated_at'] ?? '',
			);
		}
		self::render(
			'force-pages',
			array(
				'rows'   => $rows,
				'shared' => GCO_PD_Profile::shared(),
				'fields' => GCO_PD_Profile::shared_fields(),
			)
		);
	}

	/**
	 * Tabbed editor for one force's page content.
	 */
	public static function page_profile() {
		self::require_cap();
		$force_id = isset( $_GET['force_id'] ) ? absint( $_GET['force_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$force    = $force_id ? GCO_PD_Repository::get_force( $force_id ) : null;
		if ( ! $force ) {
			self::notice( 'error', 'Choose a force to edit.' );
			self::render( 'force-pages-missing' );
			return;
		}
		self::render(
			'profile-edit',
			array(
				'force'   => $force,
				'profile' => GCO_PD_Profile::get( $force_id ),
				'fields'  => GCO_PD_Profile::fields(),
				'tabs'    => GCO_PD_Profile::tabs(),
				'shared'  => GCO_PD_Profile::shared(),
			)
		);
	}

	/**
	 * Saves one force's page content.
	 */
	public static function handle_save_profile() {
		self::guard( 'gco_pd_save_profile' );
		$force_id = self::post_int( 'force_id' );
		$input    = isset( $_POST['profile'] ) && is_array( $_POST['profile'] ) ? wp_unslash( $_POST['profile'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- GCO_PD_Profile::sanitize().
		$result   = GCO_PD_Profile::save( $force_id, $input );
		if ( is_wp_error( $result ) ) {
			self::notice( 'error', $result->get_error_message() );
		} else {
			self::notice( 'success', 'Force page saved.' );
		}
		$tab = sanitize_key( self::post_text( 'tab' ) );
		self::go( self::SLUG_PROFILE, array_filter( array( 'force_id' => $force_id, 'tab' => $tab ) ) );
	}

	/**
	 * Saves the shared force page defaults.
	 */
	public static function handle_save_shared() {
		self::guard( 'gco_pd_save_shared' );
		$input = isset( $_POST['shared'] ) && is_array( $_POST['shared'] ) ? wp_unslash( $_POST['shared'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- GCO_PD_Profile::sanitize().
		GCO_PD_Profile::save_shared( $input );
		self::notice( 'success', 'Shared force page defaults saved.' );
		self::go( self::SLUG_PAGES, array( 'view' => 'shared' ) );
	}
}
