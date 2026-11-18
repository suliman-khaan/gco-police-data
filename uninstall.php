<?php
/**
 * Runs when the plugin is deleted from WP Admin.
 *
 * Data is kept unless the "delete all data on uninstall" setting is ticked.
 *
 * @package GCO_Police_Data
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$gco_pd_settings = get_option( 'gco_pd_settings', array() );

if ( empty( $gco_pd_settings['delete_data'] ) ) {
	return;
}

global $wpdb;

foreach ( array( 'gco_response_types', 'gco_force_profiles', 'gco_npcc', 'gco_npcc_quarters', 'gco_responses', 'gco_rounds', 'gco_forces' ) as $gco_pd_table ) {
	// Table names are fixed strings plus the WordPress prefix; no user input.
	$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}{$gco_pd_table}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// Snapshot options are named gco_pd_snapshot*.
$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->esc_like( 'gco_pd_snapshot' ) . '%'
	)
);

delete_option( 'gco_pd_settings' );
delete_option( 'gco_pd_force_defaults' );
delete_option( 'gco_pd_db_version' );
delete_option( 'gco_pd_cache_version' );

$gco_pd_role = get_role( 'administrator' );
if ( $gco_pd_role ) {
	$gco_pd_role->remove_cap( 'gco_manage_police_data' );
}
