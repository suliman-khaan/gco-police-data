<?php
/**
 * Files the plugin cannot run without, relative to the plugin folder. gco-police-data.php checks them before
 * loading anything: a partial upload (a folder skipped by FTP, a host that strips a folder) then shows a plain
 * notice instead of a fatal error that locks wp-admin. A unit test keeps the class files in this list in step
 * with the real ones, so adding a class means adding it here.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;

return array(
	'admin/class-admin.php',
	'admin/views/round-edit.php',
	'admin/views/setup.php',
	'includes/class-cache.php',
	'includes/class-calculator.php',
	'includes/class-cli-portal.php',
	'includes/class-cli.php',
	'includes/class-exporter.php',
	'includes/class-force-page.php',
	'includes/class-format.php',
	'includes/class-icons.php',
	'includes/class-import-parser.php',
	'includes/class-importer.php',
	'includes/class-install.php',
	'includes/class-page-extractor.php',
	'includes/class-plugin.php',
	'includes/class-profile.php',
	'includes/class-repository.php',
	'includes/class-settings.php',
	'includes/class-setup.php',
	'includes/class-shortcodes.php',
	'includes/class-svg-chart.php',
	'includes/class-validator.php',
	'includes/class-wpbakery.php',
	'includes/class-xlsx-reader.php',
	'includes/data/forces.php',
	'includes/data/portal-page.txt',
	'templates/force-page/body.php',
	'templates/portal-hero.php',
);
