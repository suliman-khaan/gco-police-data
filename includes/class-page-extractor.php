<?php
/**
 * Reads the written content out of an existing (pre-template) force page built in WPBakery, so the
 * migration can carry it into the force profile. Pure PHP: no WordPress calls, unit tested.
 *
 * The old pages share one layout: a hero (h1, h2 tagline, intro), then sections that each start with
 * an <h2> inside a vc_column_text ("Contact Details", "... Firearms Licensing Information",
 * "General Applicant Information", "GP Medical Assessments", "Previous and Current Applicant Reports",
 * "Frequently Asked Questions" ...). Each section is cut at its heading and read with the element
 * types it uses (porto_info_box, porto_info_list_item, vc_tta_section ...). Anything that is not
 * recognised is listed in the report for a manual check, never silently dropped.
 *
 * Output values are raw; GCO_PD_Profile::sanitize() cleans them when they are saved.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class GCO_PD_Page_Extractor
 */
final class GCO_PD_Page_Extractor {

	/**
	 * Section headings (lower case, matched as "contains") => section key.
	 *
	 * @var array<string,string>
	 */
	const SECTIONS = array(
		'contact details'               => 'contact',
		'firearms licensing information' => 'area',
		'general applicant information' => 'applicant',
		'gp medical'                    => 'gp',
		'applicant reports'             => 'reports',
		'waiting time'                  => 'waits',
		'national licensing context'    => 'national',
		'inspection'                    => 'inspection',
		'frequently asked questions'    => 'faqs',
		'helpful guides'                => 'guides',
	);

	/**
	 * Sections whose content is now generated from the data, so it is intentionally not carried over.
	 *
	 * @var string[]
	 */
	const GENERATED = array( 'waits', 'national', 'guides' );

	/**
	 * Extracts profile values, shared-block candidates and a report.
	 *
	 * @param string $content Page content (WPBakery shortcodes).
	 * @return array{profile:array,shared:array,report:array{found:string[],skipped:string[],unknown:string[]}}
	 */
	public static function extract( $content ) {
		$content = str_replace( "\r\n", "\n", (string) $content );
		$profile = array();
		$shared  = array();
		$report  = array(
			'found'   => array(),
			'skipped' => array(),
			'unknown' => array(),
		);

		$sections = self::split_sections( $content );

		self::read_hero( $sections['_hero'] ?? '', $profile );

		foreach ( $sections as $key => $section ) {
			if ( '_hero' === $key ) {
				continue;
			}
			list( $type, $heading ) = explode( '|', $key, 2 ) + array( '', '' );
			switch ( $type ) {
				case 'contact':
					self::read_contact( $section, $profile );
					break;
				case 'area':
					$profile['area_text'] = self::column_texts( $section );
					$profile['area_name'] = self::area_from_heading( $heading );
					break;
				case 'applicant':
					$profile['applicant_text']   = self::column_texts( $section );
					$profile['applicant_points'] = implode( "\n", self::list_items( $section ) );
					break;
				case 'gp':
					self::read_gp( $section, $profile );
					break;
				case 'reports':
					self::read_reports( $section, $profile );
					break;
				case 'inspection':
					$shared['inspection_title'] = $heading;
					$shared['inspection_text']  = self::column_texts( $section );
					if ( preg_match( '/\[products[^\]]*\bids="([^"]+)"/', $section, $m ) ) {
						$shared['inspection_products'] = preg_replace( '/\s+/', '', $m[1] );
					}
					break;
				case 'faqs':
					self::read_faqs( $section, $profile, $shared );
					break;
				default:
					if ( in_array( $type, self::GENERATED, true ) ) {
						$report['skipped'][] = $heading . ' (now generated from the data)';
					} else {
						$report['unknown'][] = $heading;
					}
			}
		}

		foreach ( $profile as $field => $value ) {
			if ( ( is_array( $value ) && $value ) || ( ! is_array( $value ) && '' !== trim( (string) $value ) ) ) {
				$report['found'][] = $field . ( is_array( $value ) ? ' (' . count( $value ) . ')' : '' );
			}
		}
		return array(
			'profile' => array_filter(
				$profile,
				static function ( $value ) {
					return is_array( $value ) ? (bool) $value : '' !== trim( (string) $value );
				}
			),
			'shared'  => array_filter(
				$shared,
				static function ( $value ) {
					return '' !== trim( (string) $value );
				}
			),
			'report'  => $report,
		);
	}

	/* ----------------------------------------------------------------- Sections */

	/**
	 * Cuts the page at each <h2> heading. Keys: "_hero" for everything before the first heading,
	 * then "type|Heading text" (a numeric suffix keeps repeated types apart).
	 *
	 * @param string $content Content.
	 * @return array<string,string>
	 */
	public static function split_sections( $content ) {
		if ( ! preg_match_all( '#<h2[^>]*>(.*?)</h2>#is', $content, $matches, PREG_OFFSET_CAPTURE ) ) {
			return array( '_hero' => $content );
		}
		$out   = array( '_hero' => substr( $content, 0, $matches[0][0][1] ) );
		$count = count( $matches[0] );
		for ( $i = 0; $i < $count; $i++ ) {
			$start   = $matches[0][ $i ][1] + strlen( $matches[0][ $i ][0] );
			$end     = $i + 1 < $count ? $matches[0][ $i + 1 ][1] : strlen( $content );
			$heading = self::text( $matches[1][ $i ][0] );
			$type    = 'unknown';
			foreach ( self::SECTIONS as $needle => $key ) {
				if ( false !== stripos( $heading, $needle ) ) {
					$type = $key;
					break;
				}
			}
			$key = $type . '|' . $heading;
			while ( isset( $out[ $key ] ) ) {
				$key .= ' ';
			}
			$out[ $key ] = substr( $content, $start, $end - $start );
		}
		return $out;
	}

	/**
	 * Hero: tagline (the h2 custom heading) and intro (first substantial text block).
	 *
	 * @param string $hero    Content before the first section.
	 * @param array  $profile Profile (modified).
	 */
	private static function read_hero( $hero, array &$profile ) {
		foreach ( self::shortcodes( $hero, 'vc_custom_heading' ) as $heading ) {
			if ( false !== strpos( $heading['atts']['font_container'] ?? '', 'tag:h2' ) ) {
				$profile['tagline'] = self::text( $heading['atts']['text'] ?? '' );
				break;
			}
		}
		foreach ( self::shortcodes( $hero, 'vc_column_text' ) as $block ) {
			$text = self::text( $block['content'] );
			if ( strlen( $text ) > 60 ) {
				$profile['intro'] = $text;
				break;
			}
		}
	}

	/**
	 * Contact: porto_info_box elements titled Address / Email / Telephone / Hours, plus an untitled note.
	 *
	 * @param string $section Section content.
	 * @param array  $profile Profile (modified).
	 */
	private static function read_contact( $section, array &$profile ) {
		foreach ( self::shortcodes( $section, 'porto_info_box' ) as $box ) {
			$title    = strtolower( self::text( $box['atts']['title'] ?? '' ) );
			$subtitle = $box['atts']['subtitle'] ?? '';
			if ( '' === $title ) {
				if ( ! isset( $profile['contact_note'] ) ) {
					$profile['contact_note'] = self::text( $subtitle );
				}
			} elseif ( false !== strpos( $title, 'address' ) ) {
				$lines = self::lines( $subtitle );
				foreach ( $lines as $i => $line ) {
					if ( preg_match( '/licensing|unit|department|bureau/i', $line ) ) {
						$profile['contact_unit'] = $line;
						unset( $lines[ $i ] );
						break;
					}
				}
				$profile['contact_address'] = implode( "\n", $lines );
			} elseif ( false !== strpos( $title, 'email' ) ) {
				$profile['contact_email'] = preg_match( '/[\w.+-]+@[\w-]+(\.[\w-]+)+/', self::text( $subtitle ), $m ) ? $m[0] : '';
			} elseif ( false !== strpos( $title, 'phone' ) ) {
				$profile['contact_phone'] = self::text( $subtitle );
			} elseif ( false !== strpos( $title, 'hour' ) ) {
				$profile['contact_hours'] = self::text( $subtitle );
			} elseif ( false !== strpos( $title, 'web' ) && preg_match( '#https?://[^\s"<]+#', $subtitle, $m ) ) {
				$profile['contact_url'] = $m[0];
			}
		}
	}

	/**
	 * GP medical: text, providers ("<strong>Name</strong><br />Tel: 0123 / site.co.uk") and note.
	 *
	 * @param string $section Section content.
	 * @param array  $profile Profile (modified).
	 */
	private static function read_gp( $section, array &$profile ) {
		$profile['gp_text'] = self::column_texts( $section );
		$providers          = array();
		foreach ( self::shortcodes( $section, 'porto_info_list_item' ) as $item ) {
			$lines = self::lines( $item['content'] );
			if ( ! $lines ) {
				continue;
			}
			$provider = array(
				'name'    => array_shift( $lines ),
				'phone'   => '',
				'website' => '',
			);
			foreach ( preg_split( '#\s/\s|\n#', implode( "\n", $lines ) ) as $part ) {
				$part = trim( $part );
				if ( preg_match( '/^tel:?\s*(.*)$/i', $part, $m ) ) {
					$phone             = trim( $m[1] );
					$provider['phone'] = preg_match( '/none|n\/a/i', $phone ) ? '' : $phone;
				} elseif ( preg_match( '#^(https?://)?([a-z0-9-]+\.)+[a-z]{2,}(/\S*)?$#i', $part ) ) {
					$provider['website'] = preg_match( '#^https?://#i', $part ) ? $part : 'https://' . $part;
				}
			}
			$providers[] = $provider;
		}
		$profile['gp_providers'] = $providers;
		foreach ( self::shortcodes( $section, 'porto_info_box' ) as $box ) {
			$note = self::text( $box['atts']['subtitle'] ?? '' );
			if ( '' !== $note ) {
				$profile['gp_note'] = $note;
				break;
			}
		}
	}

	/**
	 * Applicant reports: summary paragraph and quote cards (quote-text block + who/source block).
	 *
	 * @param string $section Section content.
	 * @param array  $profile Profile (modified).
	 */
	private static function read_reports( $section, array &$profile ) {
		$quotes = array();
		$intro  = array();
		$blocks = self::shortcodes( $section, 'vc_column_text' );
		$count  = count( $blocks );
		for ( $i = 0; $i < $count; $i++ ) {
			$block = $blocks[ $i ];
			if ( false !== strpos( $block['atts']['el_class'] ?? '', 'quote-text' ) ) {
				$lines    = isset( $blocks[ $i + 1 ] ) ? self::lines( $blocks[ $i + 1 ]['content'] ) : array();
				$quotes[] = array(
					'quote'  => trim( self::text( $block['content'] ), " \"\u{201C}\u{201D}" ),
					'who'    => $lines[0] ?? '',
					'source' => $lines[1] ?? '',
				);
				++$i;
			} else {
				$text = self::text( $block['content'] );
				if ( '' !== $text ) {
					$intro[] = $text;
				}
			}
		}
		$profile['reports_intro'] = implode( "\n\n", $intro );
		$profile['quotes']        = $quotes;
	}

	/**
	 * FAQs from accordion/tab sections, plus the review dates and disclaimer box that follow them.
	 *
	 * @param string $section Section content.
	 * @param array  $profile Profile (modified).
	 * @param array  $shared  Shared candidates (modified).
	 */
	private static function read_faqs( $section, array &$profile, array &$shared ) {
		$faqs = array();
		foreach ( array( 'vc_tta_section', 'vc_toggle' ) as $tag ) {
			foreach ( self::shortcodes( $section, $tag ) as $item ) {
				$question = self::text( $item['atts']['title'] ?? '' );
				$answer   = self::column_texts( $item['content'] );
				if ( '' === $answer ) {
					$answer = self::html( $item['content'] );
				}
				if ( '' !== $question && '' !== self::text( $answer ) ) {
					$faqs[] = array(
						'question' => $question,
						'answer'   => $answer,
					);
				}
			}
		}
		$profile['faqs'] = $faqs;

		foreach ( self::shortcodes( $section, 'porto_info_box' ) as $box ) {
			$raw = $box['atts']['subtitle'] ?? '';
			if ( preg_match( '/reviewed:?\s*(?:<\/strong>)?\s*([0-9]{1,2}\s+[a-z]+\s+[0-9]{4})/i', $raw, $m ) ) {
				$profile['reviewed'] = self::date( $m[1] );
			}
			if ( preg_match( '/next\s+(?:scheduled\s+)?review:?\s*(?:<\/strong>)?\s*([0-9]{1,2}\s+[a-z]+\s+[0-9]{4})/i', $raw, $m ) ) {
				$profile['next_review'] = self::date( $m[1] );
			}
			if ( preg_match( '/disclaimer/i', $raw ) ) {
				// The disclaimer text without its heading and the review dates.
				$text = preg_replace( '#<strong>\s*Disclaimer[^<]*</strong>\s*(<br\s*/?>)?#i', '', $raw );
				$text = preg_replace( '#(<p>)?\s*<strong>\s*(Page reviewed|Next scheduled review)[\s\S]*$#i', '', $text );
				$shared['disclaimer'] = self::html( $text );
			}
		}
	}

	/* ------------------------------------------------------------------ Helpers */

	/**
	 * Every occurrence of a shortcode: attributes and enclosed content.
	 *
	 * @param string $content Content.
	 * @param string $tag     Tag.
	 * @return array{atts:array<string,string>,content:string}[]
	 */
	public static function shortcodes( $content, $tag ) {
		$tag     = preg_quote( $tag, '#' );
		$pattern = '#\[' . $tag . '(\s[^\]]*)?\](?:(.*?)\[/' . $tag . '\])?#s';
		if ( ! preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER ) ) {
			return array();
		}
		$out = array();
		foreach ( $matches as $match ) {
			$out[] = array(
				'atts'    => self::atts( $match[1] ?? '' ),
				'content' => $match[2] ?? '',
			);
		}
		return $out;
	}

	/**
	 * Parses key="value" attributes (WPBakery escapes quotes inside values, so this is safe).
	 *
	 * @param string $text Attribute text.
	 * @return array<string,string>
	 */
	public static function atts( $text ) {
		$atts = array();
		if ( preg_match_all( '/([\w-]+)="([^"]*)"/', (string) $text, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$atts[ $match[1] ] = $match[2];
			}
		}
		return $atts;
	}

	/**
	 * Joined HTML of the vc_column_text blocks in a section, without headings and empty blocks.
	 *
	 * @param string $section Section content.
	 * @return string
	 */
	private static function column_texts( $section ) {
		$parts = array();
		foreach ( self::shortcodes( $section, 'vc_column_text' ) as $block ) {
			if ( false !== strpos( $block['atts']['el_class'] ?? '', 'quote-text' ) ) {
				continue;
			}
			$html = self::html( preg_replace( '#<h[1-6][^>]*>.*?</h[1-6]>#is', '', $block['content'] ) );
			if ( '' !== self::text( $html ) && false === strpos( $html, '[addtoany' ) ) {
				$parts[] = $html;
			}
		}
		return implode( "\n", $parts );
	}

	/**
	 * Plain text of each porto_info_list_item.
	 *
	 * @param string $section Section content.
	 * @return string[]
	 */
	private static function list_items( $section ) {
		$items = array();
		foreach ( self::shortcodes( $section, 'porto_info_list_item' ) as $item ) {
			$text = self::text( $item['content'] );
			if ( '' !== $text ) {
				$items[] = $text;
			}
		}
		return $items;
	}

	/**
	 * Cleans a fragment to simple HTML: paragraphs, emphasis, links and lists, tidy <p> blocks.
	 *
	 * @param string $html Raw fragment (wpautop leftovers included).
	 * @return string
	 */
	public static function html( $html ) {
		$html = html_entity_decode( str_replace( array( '``', '`{`', '`}`' ), array( '"', '[', ']' ), (string) $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$html = strip_tags( $html, '<p><br><strong><b><em><i><u><a><ul><ol><li>' );
		$html = preg_replace( '#<(p|li|ul|ol|strong|em|b|i|u)\s[^>]*>#i', '<$1>', $html ); // Drop data-* and style attributes.
		$html = preg_replace( '#<p>\s*</p>#', '', $html );
		$html = preg_replace( '#^\s*</p>|<p>\s*$#', '', trim( $html ) );
		return trim( preg_replace( "/\n{3,}/", "\n\n", $html ) );
	}

	/**
	 * Plain text (tags removed, entities decoded, whitespace collapsed).
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function text( $html ) {
		$html = preg_replace( '#<br\s*/?>|</p>#i', ' ', (string) $html );
		$text = html_entity_decode( strip_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return trim( preg_replace( '/\s+/u', ' ', $text ) );
	}

	/**
	 * Non-empty text lines of a fragment split at <br>, </p> and newlines.
	 *
	 * @param string $html HTML.
	 * @return string[]
	 */
	public static function lines( $html ) {
		$html  = preg_replace( '#<br\s*/?>|</p>|<p[^>]*>#i', "\n", (string) $html );
		$lines = array();
		foreach ( explode( "\n", html_entity_decode( strip_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) as $line ) {
			$line = trim( preg_replace( '/\s+/u', ' ', $line ) );
			if ( '' !== $line ) {
				$lines[] = $line;
			}
		}
		return $lines;
	}

	/**
	 * "Derbyshire Firearms Licensing Information" => "Derbyshire".
	 *
	 * @param string $heading Heading.
	 * @return string
	 */
	private static function area_from_heading( $heading ) {
		return trim( preg_replace( '/\s*(Police|Constabulary)?\s*Firearms Licensing Information.*$/i', '', $heading ) );
	}

	/**
	 * "13 June 2026" => "2026-06-13" ('' when it cannot be read).
	 *
	 * @param string $text Date text.
	 * @return string
	 */
	public static function date( $text ) {
		$time = strtotime( trim( $text ) . ' 12:00 UTC' );
		return false === $time ? '' : gmdate( 'Y-m-d', $time );
	}
}
