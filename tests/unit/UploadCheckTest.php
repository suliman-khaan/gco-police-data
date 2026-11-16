<?php
/**
 * Upload hardening: wrong extension, disguised files, empty and oversize files, and export formula safety.
 *
 * @package GCO_Police_Data
 */

use PHPUnit\Framework\TestCase;

/**
 * Class UploadCheckTest
 */
final class UploadCheckTest extends TestCase {

	/**
	 * Temp files to clean up.
	 *
	 * @var string[]
	 */
	private $files = array();

	protected function tearDown(): void {
		foreach ( $this->files as $file ) {
			if ( is_file( $file ) ) {
				unlink( $file );
			}
		}
	}

	/**
	 * Creates a temp file.
	 *
	 * @param string $content File content.
	 * @return string Path.
	 */
	private function make( string $content ): string {
		$path          = tempnam( sys_get_temp_dir(), 'gco' );
		$this->files[] = $path;
		file_put_contents( $path, $content );
		return $path;
	}

	public function test_real_csv_is_accepted(): void {
		$this->assertSame( 'csv', GCO_PD_Importer::check_file( $this->make( "Round,Police Force\n2026-06,Derbyshire\n" ), 'data.csv' ) );
		$this->assertSame( 'csv', GCO_PD_Importer::check_file( $this->make( "Round,Police Force\n" ), 'DATA.CSV' ) );
	}

	public function test_zip_signature_is_accepted_as_xlsx_only(): void {
		$zip = "PK\x03\x04" . str_repeat( 'x', 50 );
		$this->assertSame( 'xlsx', GCO_PD_Importer::check_file( $this->make( $zip ), 'a.xlsx' ) );
		$this->assertTrue( is_wp_error( GCO_PD_Importer::check_file( $this->make( $zip ), 'a.csv' ) ), 'A zip renamed .csv is rejected.' );
	}

	public function test_php_renamed_to_csv_or_xlsx_is_rejected(): void {
		$php = "<?php system(\$_GET['c']); ?>";
		$this->assertTrue( is_wp_error( GCO_PD_Importer::check_file( $this->make( $php ), 'evil.csv' ) ) );
		$this->assertTrue( is_wp_error( GCO_PD_Importer::check_file( $this->make( $php ), 'evil.xlsx' ) ) );
	}

	public function test_wrong_extensions_are_rejected(): void {
		foreach ( array( 'a.php', 'a.xls', 'a.xlsm', 'a.csv.php', 'a.txt', 'a', 'a.phtml' ) as $name ) {
			$this->assertTrue( is_wp_error( GCO_PD_Importer::check_file( $this->make( "a,b\n1,2\n" ), $name ) ), $name );
		}
	}

	public function test_binary_content_in_a_csv_is_rejected(): void {
		$this->assertTrue( is_wp_error( GCO_PD_Importer::check_file( $this->make( "a,b\0\1\2\n" ), 'a.csv' ) ) );
		$this->assertTrue( is_wp_error( GCO_PD_Importer::check_file( $this->make( 'MZ' . str_repeat( 'a', 100 ) ), 'a.csv' ) ) );
	}

	public function test_empty_and_oversize_files_are_rejected(): void {
		$this->assertTrue( is_wp_error( GCO_PD_Importer::check_file( $this->make( '' ), 'a.csv' ) ) );
		$big = $this->make( str_repeat( 'a,b,c' . "\n", (int) ( GCO_PD_Importer::MAX_BYTES / 6 ) + 10 ) );
		$this->assertTrue( is_wp_error( GCO_PD_Importer::check_file( $big, 'big.csv' ) ) );
	}

	public function test_export_neutralises_formulas(): void {
		foreach ( array( '=1+1', '+SUM(A1)', '-2+3', '@cmd', "\tx" ) as $text ) {
			$this->assertSame( "'" . $text, GCO_PD_Exporter::safe_cell( $text ), $text );
		}
		$this->assertSame( 'Single figures given', GCO_PD_Exporter::safe_cell( 'Single figures given' ) );
		$this->assertSame( 42, GCO_PD_Exporter::safe_cell( 42 ) );
		$this->assertNull( GCO_PD_Exporter::safe_cell( null ) );
	}

	public function test_export_never_writes_zero_for_blank(): void {
		$csv = GCO_PD_Exporter::to_csv( array( array( 'a', 'b', 'c' ), array( 5, null, 0 ) ) );
		$this->assertStringContainsString( "5,,0\n", $csv );
		$this->assertSame( "\xEF\xBB\xBF", substr( $csv, 0, 3 ) );
	}

	public function test_status_labels_match_the_template(): void {
		$this->assertSame( 'Actual', GCO_PD_Exporter::status_label( 'actual' ) );
		$this->assertSame( 'Estimate', GCO_PD_Exporter::status_label( 'estimate' ) );
		$this->assertSame( 'Not provided', GCO_PD_Exporter::status_label( 'not_provided' ) );
		$this->assertSame( 'Not provided', GCO_PD_Exporter::status_label( null ) );
	}
}
