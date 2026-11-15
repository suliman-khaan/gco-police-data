<?php
/**
 * Main plugin class: autoloader and boot.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class GCO_PD_Plugin
 */
final class GCO_PD_Plugin {

	/**
	 * Single instance.
	 *
	 * @var GCO_PD_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Returns the single instance, creating it on first use.
	 *
	 * @return GCO_PD_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Registers the class autoloader once.
	 */
	public static function autoload_register() {
		static $registered = false;
		if ( $registered ) {
			return;
		}
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
		$registered = true;
	}

	/**
	 * Maps GCO_PD_Some_Name to includes/class-some-name.php (or admin/class-some-name.php).
	 *
	 * @param string $class_name Fully qualified class name.
	 */
	public static function autoload( $class_name ) {
		if ( 0 !== strpos( $class_name, 'GCO_PD_' ) ) {
			return;
		}
		$slug = 'class-' . strtolower( str_replace( '_', '-', substr( $class_name, 7 ) ) ) . '.php';
		foreach ( array( 'includes/', 'admin/' ) as $dir ) {
			$path = GCO_PD_DIR . $dir . $slug;
			if ( is_readable( $path ) ) {
				require_once $path;
				return;
			}
		}
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		self::autoload_register();
		add_action( 'plugins_loaded', array( 'GCO_PD_Install', 'maybe_upgrade' ) );
		add_action( 'init', array( $this, 'load_textdomain' ) );
		// Admin, shortcodes and cache hooks are wired up in later milestones.
	}

	/**
	 * Loads translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'gco-police-data', false, dirname( plugin_basename( GCO_PD_FILE ) ) . '/languages' );
	}
}
