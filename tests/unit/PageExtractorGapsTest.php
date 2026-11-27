<?php
/**
 * The waiting-times paragraphs and the national comparison text of an old force page are read into the profile,
 * while the table, the share prompt and the number cards (generated from data now) are not.
 *
 * @package GCO_Police_Data
 */

use PHPUnit\Framework\TestCase;

/**
 * Class PageExtractorGapsTest
 */
final class PageExtractorGapsTest extends TestCase {

	/**
	 * A cut-down old page with only the two sections under test.
	 *
	 * @return string
	 */
	private function page() {
		return '[vc_row][vc_column][vc_custom_heading text="Testshire Police Firearms Licensing" font_container="tag:h1"][/vc_column][/vc_row]'
			. '<h2>Current Waiting Times</h2>'
			. '[vc_column_text]<p>One of the most common questions is how long Testshire takes.</p>'
			. '<table class="gc-table"><tbody><tr><th>Application Type</th><th>Reported Wait Time</th></tr><tr><td>Shotgun</td><td>49 days</td></tr></tbody></table>'
			. '<p>While individual applications vary, these figures are a useful guide.</p>[/vc_column_text]'
			. '[porto_info_box subtitle="The response was provided on 03 June 2026 under reference number FOI2026/00001."][/porto_info_box]'
			. '[vc_column_text]<h4><strong>Know someone applying with Testshire?</strong></h4><h5>Share these latest waiting times.</h5>[/vc_column_text]'
			. '[vc_column_text]<p>[addtoany]</p>[/vc_column_text]'
			. '<h2>National Licensing Context</h2>'
			. '[vc_column_text]<p>Licensing departments across England and Wales are responsible for more than 627,000 certificates nationally.</p>[/vc_column_text]'
			. '[vc_column_text]How does Testshire compare nationally? Due to its small area, demand is much lower than in most forces, which is worth knowing.[/vc_column_text]'
			. '[vc_column_text]<h3>25th/43</h3>[/vc_column_text]'
			. '[vc_column_text]National Performance Ranking[/vc_column_text]'
			. '[vc_column_text]Firearm and Shotgun Certificate Holders[/vc_column_text]';
	}

	public function test_waiting_times_paragraphs_are_kept_without_the_table_or_share_prompt() {
		$result = GCO_PD_Page_Extractor::extract( $this->page() );
		$text   = $result['profile']['waits_text'] ?? '';
		$this->assertStringContainsString( 'One of the most common questions', $text );
		$this->assertStringContainsString( 'While individual applications vary', $text );
		$this->assertStringNotContainsString( '49 days', $text );
		$this->assertStringNotContainsString( 'Know someone', $text );
		$this->assertStringNotContainsString( 'addtoany', $text );
	}

	public function test_national_paragraphs_are_kept_without_the_number_card_labels() {
		$result = GCO_PD_Page_Extractor::extract( $this->page() );
		$text   = $result['profile']['national_text'] ?? '';
		$this->assertStringContainsString( '627,000 certificates', $text );
		$this->assertStringContainsString( 'How does Testshire compare nationally', $text );
		$this->assertStringNotContainsString( 'National Performance Ranking', $text );
		$this->assertStringNotContainsString( 'Firearm and Shotgun Certificate Holders', $text );
	}

	public function test_the_report_says_the_table_and_cards_are_generated() {
		$report = GCO_PD_Page_Extractor::extract( $this->page() )['report'];
		$this->assertContains( 'waits_text', $report['found'] );
		$this->assertContains( 'national_text', $report['found'] );
		$this->assertStringContainsString( 'generated from the data', implode( ' ', $report['skipped'] ) );
		$this->assertSame( array(), $report['unknown'] );
	}

	/**
	 * A page with an FOI box holding a force-specific note, and an inspection section with its own wording.
	 *
	 * @return string
	 */
	private function page_with_notes() {
		return '[vc_row][vc_column][vc_custom_heading text="Testshire Police Firearms Licensing" font_container="tag:h1"][/vc_column][/vc_row]'
			. '<h2>Current Waiting Times</h2>'
			. '[porto_info_box subtitle="The information shown in this section was obtained through a Freedom of Information (FOI) request submitted by Gun Cabinets Online to Testshire Police. The response was provided on 03 June 2026 under reference number FOI2026/00001. Testshire Police confirmed that renewal times are not tracked. Times given in weeks have been converted into days."][/porto_info_box]'
			. '<h2>Inspection Coming Up? Get Ready Before the Visit</h2>'
			. '[vc_column_text]<p>Order online, install your cabinet and be ready when the officer visits.</p>[/vc_column_text]';
	}

	public function test_force_specific_waiting_time_notes_are_kept_without_the_foi_statements() {
		$note = GCO_PD_Page_Extractor::extract( $this->page_with_notes() )['profile']['waits_note'] ?? '';
		$this->assertStringContainsString( 'renewal times are not tracked', $note );
		$this->assertStringContainsString( 'converted into days', $note );
		$this->assertStringNotContainsString( 'response was provided', $note );
		$this->assertStringNotContainsString( 'obtained through a Freedom of Information', $note );
		$this->assertStringStartsWith( '<p>', $note );
	}

	public function test_a_box_with_only_foi_statements_gives_no_note() {
		$page   = '<h2>Current Waiting Times</h2>[porto_info_box subtitle="The response was provided on 03 June 2026 under reference number X1."][/porto_info_box]';
		$result = GCO_PD_Page_Extractor::extract( $page );
		$this->assertArrayNotHasKey( 'waits_note', $result['profile'] );
	}

	public function test_inspection_wording_is_kept_for_the_force_as_well_as_offered_as_shared_text() {
		$result = GCO_PD_Page_Extractor::extract( $this->page_with_notes() );
		$this->assertStringContainsString( 'Order online, install your cabinet', $result['profile']['inspection_text'] ?? '' );
		$this->assertStringContainsString( 'Order online, install your cabinet', $result['shared']['inspection_text'] ?? '' );
	}
}
