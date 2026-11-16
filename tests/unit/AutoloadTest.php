<?php
/**
 * Smoke test: the autoloader resolves plugin classes.
 *
 * @package GCO_Police_Data
 */

use PHPUnit\Framework\TestCase;

/**
 * Class AutoloadTest
 */
final class AutoloadTest extends TestCase {

	/**
	 * The main class is loadable through the autoloader.
	 */
	public function test_plugin_class_is_loaded(): void {
		$this->assertTrue( class_exists( 'GCO_PD_Plugin' ) );
	}

	/**
	 * Unknown classes are ignored rather than fatal.
	 */
	public function test_unknown_class_is_not_loaded(): void {
		$this->assertFalse( class_exists( 'GCO_PD_Does_Not_Exist' ) );
	}
}
