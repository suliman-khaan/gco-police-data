<?php
/**
 * Optional WPBakery elements, so the shortcodes can be placed from the page builder without typing them.
 * Does nothing when WPBakery is not active.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class GCO_PD_WPBakery
 */
final class GCO_PD_WPBakery {

	const CATEGORY = 'GCO Police Data';

	/**
	 * Hooks the mapping.
	 */
	public static function init() {
		add_action( 'vc_before_init', array( __CLASS__, 'map' ) );
		add_action( 'vc_before_init', array( __CLASS__, 'map_containers' ) );
	}

	/**
	 * Registers one element per shortcode.
	 */
	public static function map() {
		if ( ! function_exists( 'vc_map' ) ) {
			return;
		}
		foreach ( self::elements() as $tag => $element ) {
			vc_map(
				array(
					'name'        => $element['name'],
					'base'        => $tag,
					'description' => $element['description'],
					'category'    => self::CATEGORY,
					'params'      => $element['params'],
				)
			);
		}
	}

	/**
	 * A text parameter.
	 *
	 * @param string $name        Attribute.
	 * @param string $heading     Label.
	 * @param string $description Help text.
	 * @param string $default     Default value.
	 * @return array
	 */
	private static function text( $name, $heading, $description = '', $default = '' ) {
		return array(
			'type'        => 'textfield',
			'param_name'  => $name,
			'heading'     => $heading,
			'description' => $description,
			'value'       => $default,
			'admin_label' => true,
		);
	}

	/**
	 * The force parameter shared by force-page blocks.
	 *
	 * @return array
	 */
	private static function force() {
		return self::text( 'force', 'Force', 'Leave as "auto" to use the force linked to this page, or enter a force slug such as derbyshire.', 'auto' );
	}

	/**
	 * Element definitions.
	 *
	 * @return array<string,array>
	 */
	private static function elements() {
		$metrics = array(
			'New shotgun certificate' => 'sg_grant',
			'New firearm certificate' => 'fac_grant',
			'Shotgun renewal'         => 'sg_renew',
			'Firearm renewal'         => 'fac_renew',
			'Variation'               => 'variation',
		);

		$card = array(
			self::text( 'title', 'Title', 'Leave empty for the default title.' ),
			self::text( 'subtitle', 'Subtitle' ),
		);

		return array(
			'gco_police_hero'          => array(
				'name'        => 'Police portal hero',
				'description' => 'Title, intro and force search over a photo',
				'params'      => array(
					self::text( 'title', 'Title', '', 'Police Firearms Licensing – England & Wales' ),
					array(
						'type'       => 'attach_image',
						'param_name' => 'image',
						'heading'    => 'Background photo',
					),
					array(
						'type'       => 'textarea_html',
						'param_name' => 'content',
						'heading'    => 'Intro',
					),
					self::text( 'browse_url', 'Browse-all link', '', '#browse' ),
				),
			),
			'gco_police_search'        => array(
				'name'        => 'Police force search',
				'description' => 'Live search box for the 43 forces',
				'params'      => array(
					self::text( 'placeholder', 'Placeholder text' ),
					self::text( 'browse_url', 'Browse-all link', 'Optional link, e.g. to the A-Z section.' ),
				),
			),
			'gco_police_stats'         => array(
				'name'        => 'Police national stats',
				'description' => 'Stat cards: holders, forces reporting, fastest, national average',
				'params'      => array(
					self::text( 'cards', 'Cards', 'Comma separated: holders, reporting, fastest, average, sla, applications.', 'holders,reporting,fastest,average' ),
					array(
						'type'       => 'dropdown',
						'param_name' => 'metric',
						'heading'    => 'Waiting time used for fastest/average',
						'value'      => $metrics,
					),
				),
			),
			'gco_police_leaderboard'   => array(
				'name'        => 'Police leaderboard',
				'description' => 'Forces ranked by % completed within 4 months',
				'params'      => array(
					self::text( 'limit', 'Rows', 'A number, or "all" for the full table.', '5' ),
					self::text( 'more_url', 'Full leaderboard link' ),
					self::text( 'more_text', 'Link text', '', 'View full leaderboard' ),
					$card[0],
					$card[1],
				),
			),
			'gco_police_glance'        => array(
				'name'        => 'Police at a glance',
				'description' => 'Shortest and longest waits side by side',
				'params'      => array(
					array(
						'type'       => 'dropdown',
						'param_name' => 'metric',
						'heading'    => 'Waiting time',
						'value'      => $metrics,
					),
					$card[0],
					$card[1],
				),
			),
			'gco_police_waiting_table' => array(
				'name'        => 'Police waiting times table',
				'description' => 'Tabbed table: Shotgun, Firearm, Renewals, Variations',
				'params'      => array_merge( array( self::text( 'limit', 'Rows before "show all"', 'A number, or "all".', '5' ) ), $card ),
			),
			'gco_police_az'            => array(
				'name'        => 'Police A-Z',
				'description' => 'Browse all forces alphabetically',
				'params'      => $card,
			),
			'gco_police_guides'        => array(
				'name'        => 'Police guide cards',
				'description' => 'Cards linking to guide pages or posts',
				'params'      => array_merge(
					array(
						self::text( 'ids', 'Page or post IDs', 'Comma separated, in display order. Leave empty to use the latest posts.' ),
						self::text( 'category', 'Category slug', 'Used when no IDs are given.' ),
						self::text( 'limit', 'Number of cards', '', '4' ),
					),
					$card
				),
			),
			'gco_police_research'      => array(
				'name'        => 'Police how we research',
				'description' => 'Method note with last updated / next review dates',
				'params'      => array(
					self::text( 'title', 'Title', '', 'How We Research UK Firearms Licensing' ),
					self::text( 'next_review', 'Next review', 'e.g. April 2027' ),
					array(
						'type'       => 'textarea_html',
						'param_name' => 'content',
						'heading'    => 'Text',
					),
				),
			),
			'gco_force_waiting_times'  => array(
				'name'        => 'Force waiting times',
				'description' => 'Waiting times with Actual/Estimate badges',
				'params'      => array( self::force() ),
			),
			'gco_force_rank'           => array(
				'name'        => 'Force rank',
				'description' => '"5th of 43" per measure',
				'params'      => array( self::force() ),
			),
			'gco_force_trend'          => array(
				'name'        => 'Force trend',
				'description' => 'Trend table and chart across rounds',
				'params'      => array(
					self::force(),
					self::text( 'metrics', 'Chart lines', 'Comma separated: sg_grant, fac_grant, sg_renew, fac_renew, variation.', 'sg_grant,fac_grant' ),
				),
			),
			'gco_force_context'        => array(
				'name'        => 'Force national context',
				'description' => 'This force against the national average',
				'params'      => array( self::force() ),
			),
			'gco_force_page'           => array(
				'name'        => 'Force page (full template)',
				'description' => 'The whole force page (hero, sections and sticky FOI sidebar) from the force profile and data',
				'params'      => array( self::force() ),
			),
			'gco_force_updated'        => array(
				'name'        => 'Force last updated',
				'description' => 'Last updated and source line',
				'params'      => array( self::force() ),
			),
		);
	}

	/**
	 * Separately registered: a container so FAQ toggles dropped inside it get FAQPage schema.
	 */
	public static function map_containers() {
		if ( ! function_exists( 'vc_map' ) ) {
			return;
		}
		vc_map(
			array(
				'name'           => 'Police FAQs (with schema)',
				'base'           => 'gco_police_faq_schema',
				'description'    => 'Wrap Toggle elements with FAQ questions/answers to add FAQPage structured data',
				'category'       => self::CATEGORY,
				'params'         => array(
					self::text( 'title', 'Title', '', 'Frequently Asked Questions' ),
					self::text( 'columns', 'Columns', '1 or 2', '2' ),
				),
				'as_parent'      => array( 'only' => 'vc_toggle' ),
				'content_element' => true,
				'is_container'   => true,
			)
		);
	}
}
