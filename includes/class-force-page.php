<?php
/**
 * [gco_force_page force="auto" part="all|hero|body"]: the whole force page from one template.
 *
 * Figures come from the snapshot; written content from the force's profile; shared blocks (security
 * inspection, disclaimer, default texts) from the shared defaults. Every section hides itself when it
 * has nothing to show. Templates live in templates/force-page/ and can be overridden from
 * {theme}/gco-police-data/force-page/.
 *
 * The migration writes the page as two WPBakery rows: a full-width row with part="hero" and a normal
 * row with part="body", so the hero can stretch edge to edge inside the theme.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class GCO_PD_Force_Page
 */
final class GCO_PD_Force_Page {

	/**
	 * Waiting-time rows: metric => label used on the force page.
	 *
	 * @var array<string,string>
	 */
	const WAIT_ROWS = array(
		'sg_grant'  => 'New shotgun certificate grant',
		'fac_grant' => 'New firearm certificate grant',
		'sg_renew'  => 'Shotgun certificate renewal',
		'fac_renew' => 'Firearm certificate renewal',
		'variation' => 'Firearm certificate variation',
	);

	/**
	 * Rounds shown in the bar chart (latest last).
	 */
	const CHART_ROUNDS = 4;

	/**
	 * Renders the shortcode.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function render( array $atts ) {
		$atts  = shortcode_atts(
			array(
				'force' => 'auto',
				'part'  => 'all',
			),
			$atts,
			'gco_force_page'
		);
		$force = GCO_PD_Shortcodes::resolve_force( $atts );
		if ( ! $force ) {
			return GCO_PD_Shortcodes::no_force();
		}

		$part = in_array( $atts['part'], array( 'all', 'hero', 'body' ), true ) ? $atts['part'] : 'all';
		$data = self::build( $force );

		$html = '';
		if ( 'body' !== $part ) {
			$html .= GCO_PD_Shortcodes::template( 'force-page/hero', $data['hero'] );
		}
		if ( 'hero' !== $part ) {
			$html .= GCO_PD_Shortcodes::template( 'force-page/body', $data );
		}
		return $html;
	}

	/**
	 * Everything the templates need, section by section (null = section hidden).
	 *
	 * @param array $force Snapshot force.
	 * @return array
	 */
	public static function build( array $force ) {
		$snapshot = GCO_PD_Shortcodes::snapshot();
		$profile  = GCO_PD_Profile::get( (int) $force['id'] );
		$shared   = GCO_PD_Profile::shared();
		$name     = $force['name'];
		$area     = '' !== $profile['area_name'] ? $profile['area_name'] : GCO_PD_Format::short_name( $name );

		$data = array(
			'force'    => $force,
			'name'     => $name,
			'hero'     => self::hero( $force, $profile, $shared ),
			'contact'  => self::contact( $profile ),
			'gp'       => self::gp( $profile, $shared, $name ),
			'area'     => self::area( $force, $profile, $area ),
			'reports'  => self::reports( $profile, $name ),
			'shared'   => self::shared( $force ),
			'waits'    => self::waits( $force, $snapshot ),
			'apps'     => self::applications( $force ),
			'overview' => self::overview( $force, $name ),
			'inspect'  => self::inspection( $shared, $name ),
			'faqs'     => self::faqs( $profile, $name ),
			'guides'   => self::guides( $profile, $shared ),
			'review'   => self::review( $force, $profile, $shared, $name ),
		);
		$data['nav'] = self::nav( $data );
		return $data;
	}

	/* ------------------------------------------------------------------ Sections */

	/**
	 * Hero: breadcrumb, title, tagline, intro, photo, crest.
	 *
	 * @param array $force   Snapshot force.
	 * @param array $profile Profile.
	 * @param array $shared  Shared defaults.
	 * @return array
	 */
	private static function hero( array $force, array $profile, array $shared ) {
		$image_id = $profile['hero_image'] ? $profile['hero_image'] : $shared['hero_image'];
		$image    = $image_id ? wp_get_attachment_image_url( (int) $image_id, 'full' ) : '';

		$crumbs = array( array( 'Home', home_url( '/' ) ) );
		$portal = GCO_PD_Settings::get_int( 'portal_page_id' );
		if ( $portal && 'publish' === get_post_status( $portal ) ) {
			$crumbs[] = array( get_the_title( $portal ), get_permalink( $portal ) );
		}

		$page_id = (int) ( $force['page_id'] ?? 0 );
		$title   = $page_id ? html_entity_decode( get_the_title( $page_id ), ENT_QUOTES, 'UTF-8' ) : '';
		$crumbs[] = array( '' !== $title ? $title : $force['name'], '' );
		return array(
			'title'   => '' !== $title ? $title : $force['name'] . ' Firearms Licensing',
			'tagline' => '' !== $profile['tagline'] ? $profile['tagline'] : GCO_PD_Profile::fill( $shared['tagline'], $force['name'] ),
			'intro'   => $profile['intro'],
			'image'   => $image ? $image : '',
			'crest'   => $profile['crest_image'] ? wp_get_attachment_image(
				(int) $profile['crest_image'],
				'medium',
				false,
				array(
					'class'   => 'gco-pd-fhero__crest',
					'alt'     => $force['name'] . ' crest',
					'loading' => 'eager',
				)
			) : '',
			'crumbs'  => $crumbs,
		);
	}

	/**
	 * Contact card, or null when there is nothing to show.
	 *
	 * @param array $profile Profile.
	 * @return array|null
	 */
	private static function contact( array $profile ) {
		$address = array_values( array_filter( array_map( 'trim', explode( "\n", $profile['contact_address'] ) ), 'strlen' ) );
		$data    = array(
			'unit'    => $profile['contact_unit'],
			'address' => $address,
			'phone'   => $profile['contact_phone'],
			'tel'     => self::tel( $profile['contact_phone'] ),
			'email'   => $profile['contact_email'],
			'hours'   => $profile['contact_hours'],
			'url'     => $profile['contact_url'],
			'note'    => $profile['contact_note'],
		);
		return array_filter( array( $data['unit'], $address, $data['phone'], $data['email'], $data['url'] ) ) ? $data : null;
	}

	/**
	 * First dialable number in a phone field ("0300 1228657 or call 111" => "03001228657").
	 *
	 * @param string $phone Phone text.
	 * @return string
	 */
	public static function tel( $phone ) {
		if ( preg_match( '/\+?\d[\d\s()-]{5,}\d/', (string) $phone, $m ) ) {
			return preg_replace( '/[^\d+]/', '', $m[0] );
		}
		return '';
	}

	/**
	 * GP medical card: force text (or the shared text), shared checklist, providers, note, link.
	 *
	 * @param array  $profile Profile.
	 * @param array  $shared  Shared defaults.
	 * @param string $name    Force name.
	 * @return array|null
	 */
	private static function gp( array $profile, array $shared, $name ) {
		$text   = '' !== $profile['gp_text'] ? $profile['gp_text'] : GCO_PD_Profile::fill( $shared['gp_text'], $name );
		$points = array_values( array_filter( array_map( 'trim', explode( "\n", (string) $shared['gp_points'] ) ), 'strlen' ) );
		if ( '' === $text && ! $points && ! $profile['gp_providers'] ) {
			return null;
		}
		return array(
			'text'      => wpautop( $text ),
			'points'    => $points,
			'providers' => $profile['gp_providers'],
			'note'      => $profile['gp_note'],
			'url'       => $shared['gp_url'],
		);
	}

	/**
	 * "Firearms Licensing in {area}": description, holders sentence, general applicant information.
	 *
	 * @param array  $force   Snapshot force.
	 * @param array  $profile Profile.
	 * @param string $area    Area name.
	 * @return array|null
	 */
	private static function area( array $force, array $profile, $area ) {
		$holders = self::holders_sentence( $force );
		$points  = array_values( array_filter( array_map( 'trim', explode( "\n", $profile['applicant_points'] ) ), 'strlen' ) );
		if ( '' === $profile['area_text'] && '' === $holders && '' === $profile['applicant_text'] && ! $points ) {
			return null;
		}
		return array(
			'title'          => 'Firearms Licensing in ' . $area,
			'text'           => wpautop( $profile['area_text'] ),
			'holders'        => $holders,
			'applicant_text' => wpautop( $profile['applicant_text'] ),
			'points'         => $points,
		);
	}

	/**
	 * "There are 5,670 shotgun certificate holders and 1,684 firearm certificate holders in the ... area".
	 * FOI figures first; NPCC total holders when the force gave none.
	 *
	 * @param array $force Snapshot force.
	 * @return string Plain text.
	 */
	public static function holders_sentence( array $force ) {
		$sg  = $force['foi']['metrics']['sg_holders']['value'] ?? null;
		$fac = $force['foi']['metrics']['fac_holders']['value'] ?? null;
		if ( null !== $sg || null !== $fac ) {
			$parts = array();
			if ( null !== $sg ) {
				$parts[] = GCO_PD_Format::number( $sg ) . ' shotgun certificate holders';
			}
			if ( null !== $fac ) {
				$parts[] = GCO_PD_Format::number( $fac ) . ' firearm certificate holders';
			}
			$text = sprintf(
				'According to the latest figures from %1$s (FOI response, %2$s), there are %3$s in the %1$s area',
				$force['name'],
				GCO_PD_Format::round_label( $force['foi']['round'] ),
				implode( ' and ', $parts )
			);
			if ( null !== $sg && null !== $fac ) {
				$text .= sprintf( ', a combined total of %s certificate holders', GCO_PD_Format::number( $sg + $fac ) );
			}
			return $text . '.';
		}
		$total = $force['npcc']['holders'] ?? null;
		if ( null !== $total ) {
			return sprintf( 'According to the NPCC (%1$s), there are %2$s firearm and shotgun certificate holders in the %3$s area.', $force['npcc']['quarter'], GCO_PD_Format::number( $total ), $force['name'] );
		}
		return '';
	}

	/**
	 * Applicant reports: intro plus quote cards.
	 *
	 * @param array  $profile Profile.
	 * @param string $name    Force name.
	 * @return array|null
	 */
	private static function reports( array $profile, $name ) {
		if ( ! $profile['quotes'] && '' === $profile['reports_intro'] ) {
			return null;
		}
		$quotes = array();
		foreach ( $profile['quotes'] as $quote ) {
			if ( '' !== trim( $quote['quote'] ) ) {
				$quote['quote'] = trim( $quote['quote'], " \t\n\r\0\x0B\"\u{201C}\u{201D}" );
				$quotes[]       = $quote;
			}
		}
		return array(
			'subtitle' => sprintf( 'Real experiences from people who have applied to %s for a shotgun or firearm certificate.', $name ),
			'intro'    => $profile['reports_intro'],
			'quotes'   => $quotes,
		);
	}

	/**
	 * Current waiting times: table, bar chart of recent rounds and the change box.
	 *
	 * @param array $force    Snapshot force.
	 * @param array $snapshot Snapshot.
	 * @return array
	 */
	private static function waits( array $force, array $snapshot ) {
		$foi = $force['foi'] ?? null;
		if ( ! $foi ) {
			return array( 'empty' => true );
		}

		$rows = array();
		foreach ( self::WAIT_ROWS as $metric => $label ) {
			$data   = $foi['metrics'][ $metric ] ?? null;
			$rows[] = array(
				'label'  => $label,
				'figure' => GCO_PD_Shortcodes::figure( $data ),
				'change' => $data ? GCO_PD_Format::wait_change( $data['change'], $data['direction'] ) : null,
			);
		}

		// Bars: shotgun grant in the latest rounds this force answered.
		$trend = array_slice( $force['trend'], -self::CHART_ROUNDS );
		$items = array();
		foreach ( $trend as $i => $point ) {
			$label           = GCO_PD_Format::round_label( $point['round'] ) . ( $i === count( $trend ) - 1 ? ' (latest FOI)' : '' );
			$items[ $label ] = $point['metrics']['sg_grant']['value'] ?? null;
		}
		$chart = count( $items ) > 1 ? GCO_PD_SVG_Chart::bars(
			$items,
			array(
				'title'       => $force['name'] . ': reported shotgun grant times',
				'description' => 'Average days to grant a new shotgun certificate in each FOI response.',
				'unit'        => 'days',
				'axis_label'  => 'Processing time (days)',
			)
		) : '';

		// Change box: latest vs previous round.
		$change = null;
		$latest = $foi['metrics']['sg_grant'] ?? null;
		if ( $latest && null !== $latest['change'] && null !== $latest['value'] ) {
			$previous = $latest['value'] - $latest['change'];
			$change   = array(
				'days'      => (int) $latest['change'],
				'pct'       => $previous > 0 ? round( $latest['change'] / $previous * 100 ) : null,
				'direction' => $latest['direction'],
			);
		}

		$pdf = '';
		if ( ! empty( $foi['pdf_id'] ) && GCO_PD_Settings::get( 'show_pdf_public' ) ) {
			$url = wp_get_attachment_url( (int) $foi['pdf_id'] );
			$pdf = $url ? $url : '';
		}

		$history = array();
		if ( count( $force['trend'] ) > 1 ) {
			foreach ( array_reverse( $force['trend'] ) as $point ) {
				$cells = array();
				foreach ( array_keys( self::WAIT_ROWS ) as $metric ) {
					$cells[ $metric ] = GCO_PD_Shortcodes::figure( $point['metrics'][ $metric ] ?? null );
				}
				$history[] = array(
					'round' => GCO_PD_Format::round_label( $point['round'] ),
					'cells' => $cells,
				);
			}
		}

		return array(
			'empty'   => false,
			'ref'     => $foi['foi_ref'] ?? null,
			'date'    => GCO_PD_Format::date( $foi['response_date'] ?? '' ),
			'round'   => GCO_PD_Format::round_label( $foi['round'] ),
			'stale'   => ! empty( $foi['stale'] ),
			'latest'  => GCO_PD_Format::round_label( $snapshot['latest_round']['label'] ?? '' ),
			'rows'    => $rows,
			'chart'   => $chart,
			'change'  => $change,
			'pdf'     => $pdf,
			'history' => $history,
			'columns' => array_intersect_key( GCO_PD_Format::METRIC_LABELS, self::WAIT_ROWS ),
		);
	}

	/**
	 * "Warwickshire shares a firearms licensing department with West Mercia": the figures are the department's
	 * combined figures, shown for each force and counted once in national totals.
	 *
	 * @param array $force Snapshot force.
	 * @return array|null
	 */
	private static function shared( array $force ) {
		if ( empty( $force['shared']['with'] ) ) {
			return null;
		}
		$names = array_column( $force['shared']['with'], 'name' );
		$last  = array_pop( $names );
		$list  = $names ? implode( ', ', $names ) . ' and ' . $last : $last;
		return array(
			'text' => sprintf(
				'%1$s runs a shared firearms licensing department with %2$s. The figures below are for the shared department and appear on each force\'s page; they are counted once in the national totals.',
				$force['name'],
				$list
			),
		);
	}

	/**
	 * Applications, backlog and service standards from the latest FOI response. Only the parts switched on in
	 * Settings are included; the section is hidden when nothing is left to show.
	 *
	 * @param array $force Snapshot force.
	 * @return array|null
	 */
	private static function applications( array $force ) {
		$extra = $force['foi']['extra'] ?? null;
		if ( ! $extra ) {
			return null;
		}
		$metrics = $extra['metrics'];
		$round   = GCO_PD_Format::round_label( $force['foi']['round'] );

		$figure_row = static function ( $label, $key, $bad = null, $noun = '' ) use ( $metrics ) {
			$metric = $metrics[ $key ] ?? null;
			if ( ! $metric || null === $metric['value'] ) {
				return null;
			}
			return array(
				'label'  => $label,
				'figure' => array(
					'text'   => 'percent' === GCO_PD_Validator::FOI_EXTRA_METRICS[ $key ]['type'] ? rtrim( rtrim( number_format( $metric['value'], 2 ), '0' ), '.' ) . '%' : ( 'days' === GCO_PD_Validator::FOI_EXTRA_METRICS[ $key ]['type'] ? GCO_PD_Format::days( $metric['value'] ) : GCO_PD_Format::number( $metric['value'] ) ),
					'basis'  => 'estimate' === $metric['status'] ? 'Estimate' : '',
					'status' => $metric['status'],
				),
				'change' => GCO_PD_Format::count_change( $metric['change'], $metric['direction'], $bad, $noun ),
			);
		};

		$sections = array();

		if ( GCO_PD_Settings::get( 'show_volumes' ) ) {
			$rows = array_values( array_filter( array( $figure_row( 'Applications received', 'apps_received' ), $figure_row( 'Applications completed', 'apps_completed' ) ) ) );
			if ( $rows ) {
				$sections['volumes'] = array(
					'title'  => 'Application volumes',
					'period' => $extra['period'],
					'rows'   => $rows,
				);
			}
		}

		if ( GCO_PD_Settings::get( 'show_outstanding' ) ) {
			$rows = array_values( array_filter( array( $figure_row( 'Outstanding applications', 'outstanding', 'up', 'applications' ), $figure_row( 'Oldest outstanding application', 'oldest', 'up' ) ) ) );
			if ( $rows ) {
				$sections['outstanding'] = array(
					'title' => 'Outstanding applications',
					'rows'  => $rows,
				);
			}
		}

		if ( GCO_PD_Settings::get( 'show_backlog' ) && ! empty( $extra['backlog'] ) ) {
			$backlog = $extra['backlog'];
			$items   = array();
			foreach ( $backlog['bands'] as $band ) {
				$items[ $band['label'] ] = $band['value'];
			}
			$percent = GCO_PD_Validator::UNIT_PERCENT === $backlog['unit'];
			$rows    = array();
			foreach ( $backlog['bands'] as $band ) {
				$rows[] = array(
					'label' => $band['label'],
					'text'  => null === $band['value'] ? null : ( $percent ? rtrim( rtrim( number_format( $band['value'], 2 ), '0' ), '.' ) . '%' : GCO_PD_Format::number( $band['value'] ) ),
				);
			}
			$sections['backlog'] = array(
				'title'    => 'How long outstanding applications have waited',
				'percent'  => $percent,
				'estimate' => 'estimate' === $backlog['status'],
				'rows'     => $rows,
				'chart'    => GCO_PD_SVG_Chart::bars(
					$items,
					array(
						'title'       => $force['name'] . ': age of outstanding applications',
						'description' => $percent ? 'Share of outstanding applications in each age band, as a percentage.' : 'Number of outstanding applications in each age band.',
						'unit'        => $percent ? '%' : '',
						'axis_label'  => $percent ? 'Percentage of outstanding applications' : 'Outstanding applications',
						'highlight_last' => false,
					)
				),
				'note'     => $percent ? 'This force gave percentages rather than numbers, so they are shown as given.' : '',
			);
		}

		if ( GCO_PD_Settings::get( 'show_standards' ) ) {
			$rows = array_values( array_filter( array( $figure_row( 'Service standard', 'standard_days' ), $figure_row( 'Applications that met the standard', 'standard_met', 'down' ) ) ) );
			if ( $rows || $extra['standard'] ) {
				$sections['standards'] = array(
					'title' => 'Service standard',
					'text'  => $extra['standard'],
					'rows'  => $rows,
				);
			}
		}

		if ( ! $sections ) {
			return null;
		}
		return array(
			'sections' => $sections,
			'round'    => $round,
			'stale'    => ! empty( $force['foi']['stale'] ),
		);
	}

	/**
	 * National overview stat cards from the latest NPCC quarter.
	 *
	 * @param array  $force Snapshot force.
	 * @param string $name  Force name.
	 * @return array|null
	 */
	private static function overview( array $force, $name ) {
		$npcc = $force['npcc'] ?? null;
		if ( ! $npcc ) {
			return null;
		}
		return array(
			'subtitle' => sprintf( 'How %s compares with the national picture for firearms licensing.', $name ),
			'cards'    => array(
				array( 'trophy', $npcc['rank'] ? GCO_PD_Format::ordinal( $npcc['rank'] ) . ' / ' . $npcc['ranked_of'] : null, 'National Performance Ranking (NPCC)' ),
				array( 'users', GCO_PD_Format::number( $npcc['holders'] ), 'Firearm and Shotgun Certificate Holders' ),
				array( 'document', GCO_PD_Format::number( $npcc['received'] ), 'Applications Received (Last 12 Months)' ),
				array( 'check', GCO_PD_Format::number( $npcc['completed'] ), 'Applications Completed (Last 12 Months)' ),
			),
			'pct'      => GCO_PD_Format::percent( $npcc['pct'] ),
			'source'   => 'NPCC Firearms Licensing Performance Report, ' . $npcc['quarter'] . '. Ranking by share of applications completed within 4 months.',
		);
	}

	/**
	 * Shared security inspection block.
	 *
	 * @param array  $shared Shared defaults.
	 * @param string $name   Force name.
	 * @return array|null
	 */
	private static function inspection( array $shared, $name ) {
		if ( '' === $shared['inspection_title'] && '' === $shared['inspection_text'] ) {
			return null;
		}
		$products = '';
		if ( '' !== $shared['inspection_products'] && shortcode_exists( 'products' ) ) {
			$products = do_shortcode( '[products columns="4" ids="' . esc_attr( $shared['inspection_products'] ) . '"]' );
		}
		return array(
			'title'    => GCO_PD_Profile::fill( $shared['inspection_title'], $name ),
			'text'     => wpautop( GCO_PD_Profile::fill( $shared['inspection_text'], $name ) ),
			'image'    => $shared['inspection_image'] ? wp_get_attachment_image(
				(int) $shared['inspection_image'],
				'medium_large',
				false,
				array(
					'class'   => 'gco-pd-inspect__img',
					'loading' => 'lazy',
					'alt'     => '',
				)
			) : '',
			'btn_text' => $shared['inspection_btn_text'],
			'btn_url'  => $shared['inspection_btn_url'],
			'products' => $products,
		);
	}

	/**
	 * FAQ pairs for the shared FAQ template, or null.
	 *
	 * @param array  $profile Profile.
	 * @param string $name    Force name.
	 * @return array|null
	 */
	private static function faqs( array $profile, $name ) {
		$pairs = array();
		foreach ( $profile['faqs'] as $faq ) {
			$answer = trim( preg_replace( '/\s+/', ' ', html_entity_decode( wp_strip_all_tags( $faq['answer'] ), ENT_QUOTES, 'UTF-8' ) ) );
			if ( '' !== $faq['question'] && '' !== $answer ) {
				$pairs[] = array(
					'question' => $faq['question'],
					'answer'   => $answer,
					'html'     => wpautop( $faq['answer'] ),
				);
			}
		}
		if ( ! $pairs ) {
			return null;
		}
		return array(
			'title'   => 'Frequently Asked Questions – ' . $name,
			'columns' => 2,
			'pairs'   => $pairs,
			'schema'  => true,
		);
	}

	/**
	 * Related guides block (rendered markup).
	 *
	 * @param array $profile Profile.
	 * @param array $shared  Shared defaults.
	 * @return string
	 */
	private static function guides( array $profile, array $shared ) {
		$ids = '' !== $profile['guide_ids'] ? $profile['guide_ids'] : $shared['guide_ids'];
		if ( '' === $ids ) {
			return '';
		}
		return GCO_PD_Shortcodes::sc_guides(
			array(
				'ids'       => $ids,
				'title'     => 'Related Guides and Articles',
				'subtitle'  => 'Helpful guides on applications, home inspections and gun cabinet installation.',
				'link_url'  => $shared['guides_url'],
				'link_text' => 'View all firearms licensing guides',
			)
		);
	}

	/**
	 * Sources, disclaimer and review dates.
	 *
	 * @param array  $force   Snapshot force.
	 * @param array  $profile Profile.
	 * @param array  $shared  Shared defaults.
	 * @param string $name    Force name.
	 * @return array|null
	 */
	private static function review( array $force, array $profile, array $shared, $name ) {
		$sources = array();
		if ( ! empty( $force['foi'] ) ) {
			$sources[] = 'Freedom of Information response from ' . $name
				. ( ! empty( $force['foi']['foi_ref'] ) ? ' (' . $force['foi']['foi_ref'] . ')' : '' )
				. ', ' . GCO_PD_Format::round_label( $force['foi']['round'] );
		}
		if ( ! empty( $force['npcc'] ) ) {
			$sources[] = 'NPCC Firearms Licensing Performance Report, ' . $force['npcc']['quarter'];
		}
		$data = array(
			'disclaimer'  => wpautop( GCO_PD_Profile::fill( $shared['disclaimer'], $name ) ),
			'sources'     => $sources,
			'reviewed'    => GCO_PD_Format::date( $profile['reviewed'] ),
			'next_review' => GCO_PD_Format::date( $profile['next_review'] ),
		);
		return ( '' !== trim( wp_strip_all_tags( $data['disclaimer'] ) ) || $sources || $data['reviewed'] || $data['next_review'] ) ? $data : null;
	}

	/**
	 * Jump navigation: only sections that are on the page.
	 *
	 * @param array $data Built sections.
	 * @return array[] id, label (two lines), icon.
	 */
	private static function nav( array $data ) {
		$nav = array();
		if ( $data['contact'] ) {
			$nav[] = array( 'gco-contact', array( 'Contact Details &', 'General Info' ), 'phone' );
		}
		if ( $data['gp'] ) {
			$nav[] = array( 'gco-gp', array( 'GP Medical', 'Assessments' ), 'medical' );
		}
		if ( $data['reports'] ) {
			$nav[] = array( 'gco-reports', array( 'Recent Comments', '& Feedback' ), 'comment' );
		}
		$nav[] = array( 'gco-waits', array( 'Current', 'Waiting Times' ), 'clock' );
		if ( $data['apps'] ) {
			$nav[] = array( 'gco-applications', array( 'Applications', '& Backlog' ), 'document' );
		}
		if ( $data['faqs'] ) {
			$nav[] = array( 'gco-faqs', array( 'FAQs', '' ), 'help' );
		}
		return $nav;
	}
}
