<?php
/**
 * Keeps required-files.php (the startup check for incomplete uploads) in step with the real plugin files.
 *
 * @package GCO_Police_Data
 */

use PHPUnit\Framework\TestCase;

/**
 * Class RequiredFilesTest
 */
final class RequiredFilesTest extends TestCase {

	/**
	 * The list as the plugin reads it.
	 *
	 * @return string[]
	 */
	private function listed() {
		return (array) include GCO_PD_DIR . 'required-files.php';
	}

	public function test_every_listed_file_exists() {
		foreach ( $this->listed() as $file ) {
			$this->assertTrue( is_readable( GCO_PD_DIR . $file ), $file . ' is listed in required-files.php but missing' );
		}
	}

	public function test_every_class_file_is_listed() {
		$listed = $this->listed();
		$real   = array_merge(
			array_map(
				static function ( $path ) {
					return 'includes/' . basename( $path );
				},
				glob( GCO_PD_DIR . 'includes/class-*.php' )
			),
			array_map(
				static function ( $path ) {
					return 'admin/' . basename( $path );
				},
				glob( GCO_PD_DIR . 'admin/class-*.php' )
			)
		);
		foreach ( $real as $file ) {
			$this->assertContains( $file, $listed, $file . ' is a class file missing from required-files.php' );
		}
	}
}
