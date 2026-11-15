<?php
/**
 * Tests the XLSX reader and import parser against the real agreed template, plus the helpers.
 *
 * @package GCO_Police_Data
 */

use PHPUnit\Framework\TestCase;

/**
 * Class ImportParserTest
 */
final class ImportParserTest extends TestCase {

	/**
	 * Parsed template sheets, cached across tests.
	 *
	 * @var array|null
	 */
	private static $sheets = null;

	/**
	 * Template path.
	 *
	 * @return string
	 */
	private function template(): string {
		return GCO_PD_DIR . 'dev/reference/GCO-Police-FOI-Data-Template.xlsx';
	}

	/**
	 * Reads the template once.
	 *
	 * @return array
	 */
	private function sheets(): array {
		if ( ! is_file( $this->template() ) ) {
			$this->markTestSkipped( 'Template not present.' );
		}
		if ( ! class_exists( 'ZipArchive' ) ) {
			$this->markTestSkipped( 'zip extension missing.' );
		}
		if ( null === self::$sheets ) {
			self::$sheets = GCO_PD_Xlsx_Reader::read( $this->template() );
		}
		$this->assertIsArray( self::$sheets );
		return self::$sheets;
	}

	public function test_column_index(): void {
		$this->assertSame( 0, GCO_PD_Xlsx_Reader::column_index( 'A1' ) );
		$this->assertSame( 25, GCO_PD_Xlsx_Reader::column_index( 'Z9' ) );
		$this->assertSame( 26, GCO_PD_Xlsx_Reader::column_index( 'AA1' ) );
		$this->assertSame( 19, GCO_PD_Xlsx_Reader::column_index( 'T2' ) );
	}

	public function test_template_has_the_expected_sheets(): void {
		$names = array_keys( $this->sheets() );
		foreach ( array( 'FOI Data', 'NPCC Data', 'Forces' ) as $expected ) {
			$this->assertContains( $expected, $names );
		}
	}

	public function test_instruction_sheets_are_not_data_sheets(): void {
		$sheets = $this->sheets();
		foreach ( array( 'How to fill', 'Forces', 'Coverage' ) as $name ) {
			if ( isset( $sheets[ $name ] ) ) {
				$this->assertNull( GCO_PD_Import_Parser::parse_sheet( $sheets[ $name ] )['type'], $name );
			}
		}
	}

	public function test_foi_sheet_gives_the_derbyshire_row(): void {
		$parsed = GCO_PD_Import_Parser::parse_sheet( $this->sheets()['FOI Data'] );
		$this->assertSame( 'foi', $parsed['type'] );
		$this->assertSame( array(), $parsed['errors'] );
		$this->assertCount( 1, $parsed['rows'], 'Blank pre-formatted rows must be skipped.' );

		$row = $parsed['rows'][0];
		$this->assertSame( '2026-06', $row['period'] );
		$this->assertSame( 'Derbyshire Constabulary', $row['force'] );
		$this->assertSame( '2026-06-11', $row['data']['response_date'] );
		$this->assertSame( '01/FOI/26/008471/V', $row['data']['foi_ref'] );
		$this->assertSame( 32, $row['data']['sg_grant_days'] );
		$this->assertSame( 'DONE-Y_Derby_Police_11.06.26.pdf', $row['pdf_file'] );

		$result = GCO_PD_Validator::validate_response( $row['data'] );
		$this->assertSame( array(), $result['errors'] );
		$this->assertSame( 10541, $result['clean']['sg_holders'] );
		$this->assertSame( 3224, $result['clean']['fac_holders'] );
	}

	public function test_npcc_sheet_gives_43_valid_rows_and_the_national_figures(): void {
		$parsed = GCO_PD_Import_Parser::parse_sheet( $this->sheets()['NPCC Data'] );
		$this->assertSame( 'npcc', $parsed['type'] );
		$this->assertSame( array(), $parsed['errors'] );
		$this->assertCount( 43, $parsed['rows'] );

		$holders   = 0;
		$completed = 0;
		$in_sla    = 0;
		foreach ( $parsed['rows'] as $row ) {
			$this->assertSame( '2025/26 Q4', $row['period'] );
			$this->assertSame( '2026-03-23', $row['data']['data_date'] );
			$result = GCO_PD_Validator::validate_npcc( $row['data'] );
			$this->assertSame( array(), $result['errors'], $row['force'] );
			$holders   += $result['clean']['total_holders'];
			$completed += $result['clean']['apps_completed_12m'];
			$in_sla    += $result['clean']['completed_in_sla'];
		}

		$this->assertSame( 476079, $holders );
		$this->assertSame( 70.67, round( $in_sla / $completed * 100, 2 ) );
	}

	public function test_normalize_header(): void {
		$this->assertSame( 'shotgungrantdays', GCO_PD_Import_Parser::normalize_header( ' Shotgun Grant (days) ' ) );
		$this->assertSame( 'pctcompletedwithinsla', GCO_PD_Import_Parser::normalize_header( '% Completed Within SLA' ) );
		$this->assertNotSame(
			GCO_PD_Import_Parser::normalize_header( '% Completed Within SLA' ),
			GCO_PD_Import_Parser::normalize_header( 'Completed Within SLA' )
		);
	}

	public function test_to_date(): void {
		$this->assertSame( '2026-06-11', GCO_PD_Import_Parser::to_date( 46184 ) );
		$this->assertSame( '2026-03-23', GCO_PD_Import_Parser::to_date( 46104.0 ) );
		$this->assertSame( '2026-03-23', GCO_PD_Import_Parser::to_date( '23/03/2026' ) );
		$this->assertSame( '2026-03-03', GCO_PD_Import_Parser::to_date( '3-3-2026' ) );
		$this->assertSame( '2026-03-23', GCO_PD_Import_Parser::to_date( '2026-03-23' ) );
		$this->assertSame( '2026-03-23', GCO_PD_Import_Parser::to_date( '2026-03-23 00:00:00' ) );
		$this->assertNull( GCO_PD_Import_Parser::to_date( null ) );
		$this->assertNull( GCO_PD_Import_Parser::to_date( '  ' ) );
		$this->assertFalse( GCO_PD_Import_Parser::to_date( '31/02/2026' ) );
		$this->assertFalse( GCO_PD_Import_Parser::to_date( 'next week' ) );
		$this->assertFalse( GCO_PD_Import_Parser::to_date( 12 ) );
	}

	public function test_to_round_label(): void {
		$this->assertSame( '2026-06', GCO_PD_Import_Parser::to_round_label( '2026-06' ) );
		$this->assertSame( '2026-06', GCO_PD_Import_Parser::to_round_label( '2026-6' ) );
		$this->assertSame( '2026-06', GCO_PD_Import_Parser::to_round_label( 46174 ) ); // Excel turned it into 1 June 2026.
		$this->assertSame( '', GCO_PD_Import_Parser::to_round_label( null ) );
	}

	public function test_to_percent_and_text(): void {
		$this->assertEqualsWithDelta( 0.8962, GCO_PD_Import_Parser::to_percent( 0.8962 ), 0.00001 );
		$this->assertEqualsWithDelta( 0.8962, GCO_PD_Import_Parser::to_percent( '89.62%' ), 0.00001 );
		$this->assertNull( GCO_PD_Import_Parser::to_percent( '' ) );
		$this->assertSame( '32', GCO_PD_Import_Parser::to_text( 32.0 ) );
		$this->assertNull( GCO_PD_Import_Parser::to_text( '   ' ) );
	}

	/**
	 * Builds sheet rows from a header list and data rows.
	 *
	 * @param array $header Header cells.
	 * @param array $data   Data rows.
	 * @return array
	 */
	private function rows( array $header, array $data ): array {
		$rows = array( array( 'row' => 1, 'cells' => $header ) );
		foreach ( $data as $i => $cells ) {
			$rows[] = array( 'row' => $i + 2, 'cells' => $cells );
		}
		return $rows;
	}

	public function test_missing_required_column_is_reported(): void {
		$header = array_values( GCO_PD_Import_Parser::FOI_HEADERS );
		$header = array_values( array_diff( $header, array( 'Variation (days)' ) ) );
		$parsed = GCO_PD_Import_Parser::parse_sheet( $this->rows( $header, array() ) );
		$this->assertSame( 'foi', $parsed['type'] );
		$this->assertNotEmpty( $parsed['errors'] );
		$this->assertStringContainsString( 'Variation (days)', $parsed['errors'][0] );
	}

	public function test_columns_map_by_name_not_position_and_extras_are_ignored(): void {
		$header = array( 'Extra Thing', 'Police Force', 'Quarter', 'Total Certificate Holders', 'Applications Received (12 months)', 'Applications Completed (12 months)', 'Completed Within SLA', '% Completed Within SLA' );
		$data   = array( array( 'x', 'GMP', '2025/26 Q4', 100, 50, 40, 30, 0.75 ) );
		$parsed = GCO_PD_Import_Parser::parse_sheet( $this->rows( $header, $data ) );

		$this->assertSame( 'npcc', $parsed['type'] );
		$this->assertSame( array(), $parsed['errors'] );
		$this->assertNotEmpty( array_filter( $parsed['notices'], static fn( $n ) => false !== strpos( $n, 'Extra Thing' ) ) );
		$this->assertSame( 'GMP', $parsed['rows'][0]['force'] );
		$this->assertSame( 40, $parsed['rows'][0]['data']['apps_completed_12m'] );
		$this->assertNull( $parsed['rows'][0]['data']['temporary_permits'], 'An absent column stays NULL, never 0.' );
		$this->assertEqualsWithDelta( 0.75, $parsed['rows'][0]['pct'], 0.0001 );
	}

	public function test_too_many_rows_are_refused(): void {
		$header = array_values( GCO_PD_Import_Parser::FOI_HEADERS );
		$data   = array_fill( 0, GCO_PD_Import_Parser::MAX_DATA_ROWS + 1, array_fill( 0, count( $header ), 'x' ) );
		$parsed = GCO_PD_Import_Parser::parse_sheet( $this->rows( $header, $data ) );
		$this->assertNotEmpty( $parsed['errors'] );
		$this->assertSame( array(), $parsed['rows'] );
	}

	public function test_bad_dates_become_row_errors(): void {
		$header = array_values( GCO_PD_Import_Parser::FOI_HEADERS );
		$cells  = array_fill( 0, count( $header ), null );
		$cells[ array_search( 'Response Date', $header, true ) ] = 'whenever';
		$parsed = GCO_PD_Import_Parser::parse_sheet( $this->rows( $header, array( $cells ) ) );
		$this->assertArrayHasKey( 'response_date', $parsed['rows'][0]['errors'] );
	}

	public function test_csv_with_bom_and_semicolons(): void {
		$path = tempnam( sys_get_temp_dir(), 'gco' );
		file_put_contents( $path, "\xEF\xBB\xBFQuarter;Police Force;Total Certificate Holders\n2025/26 Q4;\"Avon & Somerset\";19825\n\n" );
		$rows = GCO_PD_Import_Parser::read_csv( $path );
		unlink( $path );
		$this->assertCount( 2, $rows );
		$this->assertSame( 'Quarter', $rows[0]['cells'][0] );
		$this->assertSame( 'Avon & Somerset', $rows[1]['cells'][1] );
		$this->assertSame( '19825', $rows[1]['cells'][2] );
	}

	public function test_csv_in_windows_1252_is_converted(): void {
		if ( ! function_exists( 'mb_convert_encoding' ) ) {
			$this->markTestSkipped( 'mbstring missing.' );
		}
		$path = tempnam( sys_get_temp_dir(), 'gco' );
		file_put_contents( $path, "Police Force,Notes\nDerbyshire,Caf\xE9 \x96 note\n" );
		$rows = GCO_PD_Import_Parser::read_csv( $path );
		unlink( $path );
		$this->assertSame( "Caf\u{E9} \u{2013} note", $rows[1]['cells'][1] );
	}

	public function test_exported_csv_can_be_parsed_again(): void {
		$header = array_values( GCO_PD_Import_Parser::NPCC_HEADERS );
		$csv    = GCO_PD_Exporter::to_csv(
			array(
				$header,
				array( '2025/26 Q4', '2026-03-23', 'Avon and Somerset Police', 19825, 25410, 5810, 5657, 5070, 0.896235, 5, '=cmd|calc' ),
				array( '2025/26 Q4', '2026-03-23', 'Bedfordshire Police', 4592, null, 1587, 1227, 413, '', null, '' ),
			)
		);
		$path = tempnam( sys_get_temp_dir(), 'gco' );
		file_put_contents( $path, $csv );
		$parsed = GCO_PD_Import_Parser::parse_sheet( GCO_PD_Import_Parser::read_csv( $path ) );
		unlink( $path );

		$this->assertSame( array(), $parsed['errors'] );
		$this->assertCount( 2, $parsed['rows'] );
		$this->assertSame( 5070, GCO_PD_Validator::validate_npcc( $parsed['rows'][0]['data'] )['clean']['completed_in_sla'] );
		$this->assertSame( "'=cmd|calc", $parsed['rows'][0]['data']['notes'], 'Formula text is neutralised on export.' );
		$this->assertNull( GCO_PD_Validator::validate_npcc( $parsed['rows'][1]['data'] )['clean']['total_certificates'], 'Blank stays NULL through a round trip.' );
	}
}
