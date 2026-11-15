<?php
/**
 * PHPUnit bootstrap for the pure-PHP unit tests (validator, calculator, importer mapping, SVG chart).
 *
 * These classes must not depend on WordPress being loaded, so we only define ABSPATH,
 * the plugin constants and the autoloader here. UI and DB behaviour is covered by the manual QA checklist.
 *
 * @package GCO_Police_Data
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'GCO_PD_DIR', dirname( __DIR__ ) . '/' );
define( 'GCO_PD_VERSION', 'test' );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once GCO_PD_DIR . 'includes/class-plugin.php';

GCO_PD_Plugin::autoload_register();
