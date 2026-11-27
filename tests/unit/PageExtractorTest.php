<?php
/**
 * Unit tests for GCO_PD_Page_Extractor (reading old WPBakery force pages).
 *
 * @package GCO_Police_Data
 */

use PHPUnit\Framework\TestCase;

/**
 * Class PageExtractorTest
 */
final class PageExtractorTest extends TestCase {

	/**
	 * A cut-down force page in the layout the 21 existing pages use.
	 *
	 * @return string
	 */
	private function page() {
		return '[vc_row][vc_column][vc_custom_heading text="Testshire Police Firearms Licensing" font_container="tag:h1|font_size:2.25rem"]'
			. '[vc_custom_heading text="Firearms licensing information, waiting times and resources" font_container="tag:h2|font_size:1rem"]'
			. '[vc_column_text]</p><p>Looking for current Testshire Police firearms licensing waiting times? This page combines official information with FOI data.</p><p>[/vc_column_text]'
			. '[/vc_column][/vc_row]'
			. '[vc_row][vc_column width="1/3"][vc_column_text]</p><h2><strong>Contact Details</strong></h2><p>[/vc_column_text]'
			. '[porto_info_box title="Address" subtitle="Testshire Police<br />Firearms &amp; Explosives Licensing Unit<br />High Street<br />TS1 1AA"][/porto_info_box]'
			. '[porto_info_box title="Email" subtitle="firearms@testshire.police.uk"][/porto_info_box]'
			. '[porto_info_box title="Telephone" subtitle="0300 123 4567 or call 101"][/porto_info_box]'
			. '[porto_info_box title="Office / Licensing Hours" subtitle="Mon-Fri 9am-4pm"][/porto_info_box]'
			. '[porto_info_box subtitle="Contact the licensing unit for all applications."][/porto_info_box]'
			. '[/vc_column][vc_column width="2/3"][vc_column_text]</p><h2><strong>Testshire Firearms Licensing Information</strong></h2><p>[/vc_column_text]'
			. '[vc_column_text]Testshire Constabulary covers the county of Testshire.[/vc_column_text]'
			. '[vc_column_text]</p><h2><strong>General Applicant Information</strong></h2><p>[/vc_column_text]'
			. '[vc_column_text]Applications are made online.[/vc_column_text]'
			. '[porto_info_list][porto_info_list_item list_icon="fas fa-check-circle"]Online application system in use[/porto_info_list_item][porto_info_list_item]Postal accepted[/porto_info_list_item][/porto_info_list]'
			. '[/vc_column][/vc_row]'
			. '[vc_row][vc_column][vc_column_text]</p><h2><strong>GP Medical Assessments</strong></h2><p>[/vc_column_text]'
			. '[vc_column_text]Testshire requires medical information.[/vc_column_text]'
			. '[porto_info_list][porto_info_list_item]<strong>MedCert</strong><br />Tel: 020 8063 4503 / medcert.co.uk[/porto_info_list_item]'
			. '[porto_info_list_item]<strong>ShootCert</strong><br />Tel: none-provided / shootcert.co.uk[/porto_info_list_item][/porto_info_list]'
			. '[porto_info_box subtitle="<strong>Know a provider we have missed?</strong><br />Get in touch."][/porto_info_box]'
			. '[vc_column_text]</p><h2><strong>Previous and Current Applicant Reports</strong></h2><p>[/vc_column_text]'
			. '[vc_column_text]Applicants report positive experiences.[/vc_column_text]'
			. '[vc_column_text el_class="quote-text"]<strong>"Always helpful"</strong>[/vc_column_text][vc_column_text]Firearm Certificate Holder<br />The Stalking Directory (2023)[/vc_column_text]'
			. '[/vc_column][/vc_row]'
			. '[vc_row][vc_column][vc_column_text]</p><h2><strong>Current Waiting Times</strong></h2><p>[/vc_column_text][vc_column_text]<table><tr><td>32 days</td></tr></table>[/vc_column_text][/vc_column][/vc_row]'
			. '[vc_row][vc_column][vc_column_text]</p><h2>Inspection Coming Up? Get Ready Before the Visit</h2><p>[/vc_column_text]'
			. '[vc_column_text]Have your cabinet fitted before the visit.[/vc_column_text][products columns="4" ids="5026, 5084"][/vc_column][/vc_row]'
			. '[vc_row][vc_column][vc_column_text]</p><h2><strong>Frequently Asked Questions</strong></h2><p>[/vc_column_text]'
			. '[vc_tta_accordion][vc_tta_section title="Does Testshire Police accept postal applications?"][vc_column_text]Yes, but online is faster.[/vc_column_text][/vc_tta_section][/vc_tta_accordion]'
			. '[porto_info_box subtitle="<strong>Disclaimer and Sources</strong><br />We are not affiliated with Testshire Police.<p><strong>Page reviewed:</strong> 13 June 2026<br /><strong>Next scheduled review:</strong> 13 December 2026"][/porto_info_box]'
			. '[/vc_column][/vc_row]'
			. '[vc_row][vc_column][vc_column_text]</p><h2>Something New</h2><p>[/vc_column_text][/vc_column][/vc_row]';
	}

	public function test_hero_and_contact(): void {
		$profile = GCO_PD_Page_Extractor::extract( $this->page() )['profile'];
		$this->assertSame( 'Firearms licensing information, waiting times and resources', $profile['tagline'] );
		$this->assertStringStartsWith( 'Looking for current Testshire Police', $profile['intro'] );
		$this->assertSame( 'Firearms & Explosives Licensing Unit', $profile['contact_unit'] );
		$this->assertSame( "Testshire Police\nHigh Street\nTS1 1AA", $profile['contact_address'] );
		$this->assertSame( 'firearms@testshire.police.uk', $profile['contact_email'] );
		$this->assertSame( '0300 123 4567 or call 101', $profile['contact_phone'] );
		$this->assertSame( 'Mon-Fri 9am-4pm', $profile['contact_hours'] );
		$this->assertSame( 'Contact the licensing unit for all applications.', $profile['contact_note'] );
	}

	public function test_area_and_applicant_information(): void {
		$profile = GCO_PD_Page_Extractor::extract( $this->page() )['profile'];
		$this->assertSame( 'Testshire', $profile['area_name'] );
		$this->assertSame( 'Testshire Constabulary covers the county of Testshire.', $profile['area_text'] );
		$this->assertSame( 'Applications are made online.', $profile['applicant_text'] );
		$this->assertSame( "Online application system in use\nPostal accepted", $profile['applicant_points'] );
	}

	public function test_gp_providers_and_note(): void {
		$profile = GCO_PD_Page_Extractor::extract( $this->page() )['profile'];
		$this->assertSame( 'Testshire requires medical information.', $profile['gp_text'] );
		$this->assertSame(
			array(
				array(
					'name'    => 'MedCert',
					'phone'   => '020 8063 4503',
					'website' => 'https://medcert.co.uk',
				),
				array(
					'name'    => 'ShootCert',
					'phone'   => '',
					'website' => 'https://shootcert.co.uk',
				),
			),
			$profile['gp_providers']
		);
		$this->assertSame( 'Know a provider we have missed? Get in touch.', $profile['gp_note'] );
	}

	public function test_reports_and_quotes(): void {
		$profile = GCO_PD_Page_Extractor::extract( $this->page() )['profile'];
		$this->assertSame( 'Applicants report positive experiences.', $profile['reports_intro'] );
		$this->assertSame(
			array(
				array(
					'quote'  => 'Always helpful',
					'who'    => 'Firearm Certificate Holder',
					'source' => 'The Stalking Directory (2023)',
				),
			),
			$profile['quotes']
		);
	}

	public function test_faqs_review_dates_and_shared_blocks(): void {
		$result = GCO_PD_Page_Extractor::extract( $this->page() );
		$this->assertSame( 'Does Testshire Police accept postal applications?', $result['profile']['faqs'][0]['question'] );
		$this->assertSame( 'Yes, but online is faster.', $result['profile']['faqs'][0]['answer'] );
		$this->assertSame( '2026-06-13', $result['profile']['reviewed'] );
		$this->assertSame( '2026-12-13', $result['profile']['next_review'] );
		$this->assertSame( '5026,5084', $result['shared']['inspection_products'] );
		$this->assertSame( 'Have your cabinet fitted before the visit.', $result['shared']['inspection_text'] );
		$this->assertStringContainsString( 'not affiliated with Testshire Police', $result['shared']['disclaimer'] );
		$this->assertStringNotContainsString( 'Page reviewed', $result['shared']['disclaimer'] );
	}

	public function test_report_lists_generated_and_unknown_sections(): void {
		$report = GCO_PD_Page_Extractor::extract( $this->page() )['report'];
		$this->assertContains( 'Current Waiting Times (the table and FOI line are generated from the data)', $report['skipped'] );
		$this->assertSame( array( 'Something New' ), $report['unknown'] );
		$this->assertContains( 'quotes (1)', $report['found'] );
	}

	public function test_page_without_headings_is_all_hero(): void {
		$result = GCO_PD_Page_Extractor::extract( '[vc_column_text]Short[/vc_column_text]' );
		$this->assertSame( array(), $result['profile'] );
		$this->assertSame( array(), $result['report']['unknown'] );
	}

	public function test_helpers(): void {
		$this->assertSame( array( 'a' => '1', 'b-c' => 'x y' ), GCO_PD_Page_Extractor::atts( ' a="1" b-c="x y"' ) );
		$this->assertSame( array( 'One', 'Two' ), GCO_PD_Page_Extractor::lines( '<p>One<br />Two</p>' ) );
		$this->assertSame( 'A & B', GCO_PD_Page_Extractor::text( '<strong>A &amp; B</strong>' ) );
		$this->assertSame( '<p>Hi</p>', GCO_PD_Page_Extractor::html( '</p><p data-x="1">Hi</p><p>' ) );
		$this->assertSame( '', GCO_PD_Page_Extractor::date( 'not a date' ) );
	}
}
