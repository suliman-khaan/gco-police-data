<?php
/**
 * Plugin Name:       GCO Police Data
 * Description:       Stores police force FOI responses and NPCC firearms-licensing figures, and renders the police licensing portal and force pages from them.
 * Version:           0.5.0
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

define( 'GCO_PD_VERSION', '0.5.0' );
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
