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

// Minimal stand-ins for the few WordPress APIs the pure classes touch.
if ( ! class_exists( 'WP_Error' ) ) {
	// phpcs:ignore Generic.Files.OneObjectStructurePerFile
	class WP_Error {
		private $code;
		private $message;
		public function __construct( $code = '', $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}
		public function get_error_code() {
			return $this->code;
		}
		public function get_error_message() {
			return $this->message;
		}
	}
}
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}
require_once GCO_PD_DIR . 'includes/class-plugin.php';

GCO_PD_Plugin::autoload_register();
