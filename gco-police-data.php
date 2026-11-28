<?php
/**
 * Plugin Name:       GCO Police Data
 * Description:       Stores police force FOI responses and NPCC firearms-licensing figures, and renders the police licensing portal and force pages from them.
 * Version:           0.7.5
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Suliman K.
 * Author URI:        https://sulimankhan.pro
 * License:           Proprietary
 * Text Domain:       gco-police-data
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;

define( 'GCO_PD_VERSION', '0.7.5' );
define( 'GCO_PD_FILE', __FILE__ );
define( 'GCO_PD_DIR', plugin_dir_path( __FILE__ ) );
define( 'GCO_PD_URL', plugin_dir_url( __FILE__ ) );
define( 'GCO_PD_MIN_PHP', '8.1' );
define( 'GCO_PD_MIN_WP', '6.5' );

/**
 * Whether the server meets the minimum PHP and WordPress versions.
 *
 * @return bool
 */
function gco_pd_requirements_met() {
	global $wp_version;
	return version_compare( PHP_VERSION, GCO_PD_MIN_PHP, '>=' )
		&& version_compare( $wp_version, GCO_PD_MIN_WP, '>=' );
}

/**
 * Core files that are missing from the plugin folder (an incomplete upload), as relative paths.
 *
 * @return string[]
 */
function gco_pd_missing_files() {
	$list = GCO_PD_DIR . 'required-files.php';
	if ( ! is_readable( $list ) ) {
		return array( 'required-files.php' );
	}
	$missing = array();
	foreach ( (array) include $list as $file ) {
		if ( ! is_readable( GCO_PD_DIR . $file ) ) {
			$missing[] = $file;
		}
	}
	return $missing;
}

/**
 * Admin notice shown instead of loading the plugin when files are missing.
 */
function gco_pd_files_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	$missing = gco_pd_missing_files();
	printf(
		'<div class="notice notice-error"><p><strong>%1$s</strong> %2$s</p><p>%3$s <code>%4$s</code></p></div>',
		esc_html__( 'GCO Police Data has not been loaded.', 'gco-police-data' ),
		esc_html__( 'Some of its files are missing from the plugin folder, which usually means the upload did not finish. Delete the plugin and upload the zip again under Plugins > Add New > Upload Plugin.', 'gco-police-data' ),
		esc_html__( 'Missing:', 'gco-police-data' ),
		esc_html( implode( ', ', array_slice( $missing, 0, 12 ) ) . ( count( $missing ) > 12 ? sprintf( ' (+%d more)', count( $missing ) - 12 ) : '' ) )
	);
}

/**
 * Admin notice shown instead of loading the plugin when requirements are not met.
 */
function gco_pd_requirements_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html(
			sprintf(
				/* translators: 1: required PHP version, 2: required WordPress version. */
				__( 'GCO Police Data needs PHP %1$s+ and WordPress %2$s+. The plugin has not been loaded.', 'gco-police-data' ),
				GCO_PD_MIN_PHP,
				GCO_PD_MIN_WP
			)
		)
	);
}

/**
 * Activation: refuse to activate on unsupported servers, then run the installer.
 */
function gco_pd_activate() {
	if ( gco_pd_missing_files() ) {
		deactivate_plugins( plugin_basename( GCO_PD_FILE ) );
		wp_die(
			esc_html__( 'GCO Police Data cannot be activated: some of its files are missing, so the upload did not finish. Delete the plugin and upload the zip again.', 'gco-police-data' ),
			'',
			array( 'back_link' => true )
		);
	}
	if ( ! gco_pd_requirements_met() ) {
		deactivate_plugins( plugin_basename( GCO_PD_FILE ) );
		wp_die(
			esc_html(
				sprintf(
					/* translators: 1: required PHP version, 2: required WordPress version. */
					__( 'GCO Police Data needs PHP %1$s+ and WordPress %2$s+.', 'gco-police-data' ),
					GCO_PD_MIN_PHP,
					GCO_PD_MIN_WP
				)
			),
			'',
			array( 'back_link' => true )
		);
	}

	require_once GCO_PD_DIR . 'includes/class-plugin.php';
	GCO_PD_Plugin::autoload_register();

	// The installer (tables, seed data, capability) arrives in milestone M1.
	if ( class_exists( 'GCO_PD_Install' ) ) {
		GCO_PD_Install::activate();
	}
}
register_activation_hook( GCO_PD_FILE, 'gco_pd_activate' );

if ( ! gco_pd_requirements_met() ) {
	add_action( 'admin_notices', 'gco_pd_requirements_notice' );
	return;
}

if ( gco_pd_missing_files() ) {
	add_action( 'admin_notices', 'gco_pd_files_notice' );
	return;
}

require_once GCO_PD_DIR . 'includes/class-plugin.php';

/**
 * Returns the single plugin instance.
 *
 * @return GCO_PD_Plugin
 */
function gco_pd() {
	return GCO_PD_Plugin::instance();
}

gco_pd();
