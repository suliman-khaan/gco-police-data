<?php
/**
 * Shortcodes: portal sections and force-page blocks.
 *
 * Every shortcode reads the precomputed snapshot (one get_option, no per-force queries) and renders a
 * template that a theme can override by copying it to {theme}/gco-police-data/{name}.php. CSS and JS are
 * loaded only on pages that actually use a shortcode.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class GCO_PD_Shortcodes
 */
final class GCO_PD_Shortcodes {

	/**
	 * Shortcode tag => method.
	 *
	 * @var array<string,string>
	 */
	const TAGS = array(
		'gco_police_hero'          => 'hero',
		'gco_police_search'        => 'search',
		'gco_police_stats'         => 'stats',
		'gco_police_leaderboard'   => 'leaderboard',
		'gco_police_glance'        => 'glance',
		'gco_police_waiting_table' => 'waiting_table',
		'gco_police_az'            => 'az',
		'gco_police_guides'        => 'guides',
		'gco_police_research'      => 'research',
		'gco_force_waiting_times'  => 'force_waiting_times',
		'gco_force_rank'           => 'force_rank',
		'gco_force_trend'          => 'force_trend',
		'gco_force_context'        => 'force_context',
		'gco_force_updated'        => 'force_updated',
		'gco_police_faq_schema'    => 'faq_schema',
		'gco_force_page'           => 'force_page',
	);

	/**
	 * Tags that need front.js.
	 *
	 * @var string[]
	 */
	const INTERACTIVE = array( 'gco_police_hero', 'gco_police_search', 'gco_police_leaderboard', 'gco_police_waiting_table', 'gco_police_az', 'gco_force_page' );

	/**
	 * Waiting-time metrics shown on force pages and the portal.
	 *
	 * @var string[]
	 */
	const WAIT_METRICS = array( 'sg_grant', 'fac_grant', 'sg_renew', 'fac_renew', 'variation' );

	/**
	 * Tabs of the portal waiting-times table.
	 *
	 * @var array<string,array{label:string,metrics:string[]}>
	 */
	const TABS = array(
		'shotgun'    => array(
			'label'   => 'Shotgun Grants',
			'metrics' => array( 'sg_grant' ),
		),
		'firearm'    => array(
			'label'   => 'Firearm Grants',
			'metrics' => array( 'fac_grant' ),
		),
		'renewals'   => array(
			'label'   => 'Renewals',
			'metrics' => array( 'sg_renew', 'fac_renew' ),
		),
		'variations' => array(
			'label'   => 'Variations',
			'metrics' => array( 'variation' ),
		),
	);

	/**
	 * Plural phrases for "Top 5 forces for ...".
	 *
	 * @var array<string,string>
	 */
	const METRIC_PHRASES = array(
		'sg_grant'  => 'shotgun certificate grants',
		'fac_grant' => 'firearm certificate grants',
		'sg_renew'  => 'shotgun certificate renewals',
		'fac_renew' => 'firearm certificate renewals',
		'variation' => 'certificate variations',
	);

	/**
	 * Registers shortcodes and asset hooks.
	 */
	public static function init() {
		foreach ( self::TAGS as $tag => $method ) {
			add_shortcode(
				$tag,
				static function ( $atts, $content, $shortcode_tag ) use ( $method ) {
					return GCO_PD_Shortcodes::run( $method, $shortcode_tag, is_array( $atts ) ? $atts : array(), (string) $content );
				}
			);
		}
		add_action( 'init', array( __CLASS__, 'register_assets' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_early' ) );
	}

	/* ---------------------------------------------------------------------- Assets */

	/**
	 * Registers (does not load) the front-end assets.
	 */
	public static function register_assets() {
		wp_register_style( 'gco-pd-front', GCO_PD_URL . 'assets/front.css', array(), GCO_PD_VERSION );
		wp_register_script( 'gco-pd-front', GCO_PD_URL . 'assets/front.js', array(), GCO_PD_VERSION, array( 'strategy' => 'defer', 'in_footer' => true ) );
	}

	/**
	 * Loads assets in the head when the page content contains one of our shortcodes. Shortcodes inside
	 * widgets or builders that this misses still enqueue the assets when they render.
	 */
	public static function maybe_enqueue_early() {
		if ( ! is_singular() ) {
			return;
		}
		$post = get_post();
		if ( ! $post ) {
			return;
		}
		foreach ( array_keys( self::TAGS ) as $tag ) {
			if ( has_shortcode( $post->post_content, $tag ) ) {
				self::enqueue( in_array( $tag, self::INTERACTIVE, true ) );
				if ( in_array( $tag, self::INTERACTIVE, true ) ) {
					return;
				}
			}
		}
	}

	/**
	 * Enqueues the CSS (and the JS when needed).
	 *
	 * @param bool $interactive Whether the shortcode needs front.js.
	 */
	private static function enqueue( $interactive ) {
		wp_enqueue_style( 'gco-pd-front' );
		if ( $interactive ) {
			wp_enqueue_script( 'gco-pd-front' );
		}
	}

	/* --------------------------------------------------------------------- Plumbing */

	/**
	 * Entry point for every shortcode.
	 *
	 * @param string $method  Method suffix.
	 * @param string $tag     Shortcode tag.
	 * @param array  $atts    Attributes.
	 * @param string $content Enclosed content (unused by most shortcodes; '' is passed for self-closing tags).
	 * @return string
	 */
	public static function run( $method, $tag, array $atts, $content = '' ) {
		self::enqueue( in_array( $tag, self::INTERACTIVE, true ) );
		return (string) call_user_func( array( __CLASS__, 'sc_' . $method ), $atts, $content );
	}

	/**
	 * Wraps a block in the portal's card: icon, title, subtitle and an optional header link.
	 * With an empty title and no link the block is returned unwrapped.
	 *
	 * @param string $body  Block markup (already escaped).
	 * @param array  $atts  Attributes with title, subtitle, link_url, link_text.
	 * @param string $icon  Icon name.
	 * @param string $mod   Modifier class suffix.
	 * @param string $link  Ready-made header link markup, used instead of link_url (e.g. an expand button).
	 * @return string
	 */
	private static function card( $body, array $atts, $icon, $mod, $link = '' ) {
		$title = trim( (string) ( $atts['title'] ?? '' ) );
		if ( '' === $link && '' !== (string) ( $atts['link_url'] ?? '' ) ) {
			$link = '<a class="gco-pd-card__link" href="' . esc_url( $atts['link_url'] ) . '">' . esc_html( $atts['link_text'] ) . GCO_PD_Icons::get( 'arrow-right' ) . '</a>';
		}
		if ( '' === $title && '' === $link ) {
			return $body;
		}
		return self::template(
			'part-card',
			array(
				'title'    => $title,
				'subtitle' => trim( (string) ( $atts['subtitle'] ?? '' ) ),
				'icon'     => $icon,
				'mod'      => $mod,
				'link'     => $link,
				'body'     => $body,
			)
		);
	}

	/**
	 * Card attributes merged with a block's own defaults.
	 *
	 * @param array $defaults Block defaults, including its default title/subtitle.
	 * @return array
	 */
	private static function with_card( array $defaults ) {
		return array_merge(
			array(
				'title'     => '',
				'subtitle'  => '',
				'link_url'  => '',
				'link_text' => '',
			),
			$defaults
		);
	}

	/**
	 * Header button that expands a list cut to its first rows.
	 *
	 * @param string $target Id of the element carrying data-gco-expand.
	 * @param string $text   Button text.
	 * @return string
	 */
	private static function expand_button( $target, $text ) {
		return '<button type="button" class="gco-pd-card__link" data-gco-expand-toggle="' . esc_attr( $target ) . '" aria-controls="' . esc_attr( $target ) . '" aria-expanded="false">' . esc_html( $text ) . GCO_PD_Icons::get( 'arrow-right' ) . '</button>';
	}

	/**
	 * Competition ranks (1, 2, 2, 4) for rows already sorted, keyed on $key; null values are unranked.
	 *
	 * @param array  $rows Sorted rows.
	 * @param string $key  Sort value key.
	 * @return array Rows with 'rank'.
	 */
	private static function rank_rows( array $rows, $key ) {
		$rank = 0;
		$prev = null;
		foreach ( $rows as $i => $row ) {
			if ( null === $row[ $key ] ) {
				$rows[ $i ]['rank'] = null;
				continue;
			}
			if ( $row[ $key ] !== $prev ) {
				$rank = $i + 1;
				$prev = $row[ $key ];
			}
			$rows[ $i ]['rank'] = $rank;
		}
		return $rows;
	}

	/**
	 * Renders a template (theme override first).
	 *
	 * @param string $name Template name without extension.
	 * @param array  $vars Variables.
	 * @return string
	 */
	public static function template( $name, array $vars ) {
		$gco_pd_tpl_path = locate_template( array( 'gco-police-data/' . $name . '.php' ) );
		if ( ! $gco_pd_tpl_path ) {
			$gco_pd_tpl_path = GCO_PD_DIR . 'templates/' . $name . '.php';
		}
		unset( $name );
		ob_start();
		// Templates may use any variable name, including $name and $vars.
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		extract( $vars, EXTR_OVERWRITE );
		include $gco_pd_tpl_path;
		return (string) ob_get_clean();
	}

	/**
	 * The snapshot.
	 *
	 * @return array
	 */
	public static function snapshot() {
		return GCO_PD_Cache::get_snapshot();
	}

	/**
	 * Quiet message shown while there is nothing to display yet.
	 *
	 * @return string
	 */
	private static function empty_state() {
		return '<p class="gco-pd-empty">Figures will appear here as soon as the first round of data is published.</p>';
	}

	/**
	 * Permalink of a force's page, or '' when none is linked or it is not published.
	 *
	 * @param array $force Snapshot force.
	 * @return string
	 */
	public static function force_url( array $force ) {
		static $primed = false;
		if ( ! $primed ) {
			$ids = array();
			foreach ( self::snapshot()['forces'] as $item ) {
				if ( ! empty( $item['page_id'] ) ) {
					$ids[] = (int) $item['page_id'];
				}
			}
			if ( $ids ) {
				_prime_post_caches( $ids, false, false ); // One query for all force pages.
			}
			$primed = true;
		}
		if ( empty( $force['page_id'] ) || 'publish' !== get_post_status( (int) $force['page_id'] ) ) {
			return '';
		}
		$url = get_permalink( (int) $force['page_id'] );
		return $url ? $url : '';
	}

	/**
	 * Forces sorted by name.
	 *
	 * @param array $snapshot Snapshot.
	 * @return array[]
	 */
	private static function forces_by_name( array $snapshot ) {
		$forces = array_values( $snapshot['forces'] );
		usort(
			$forces,
			static function ( $a, $b ) {
				return strcmp( $a['name'], $b['name'] );
			}
		);
		return $forces;
	}

	/**
	 * Resolves force="auto" (the force linked to this page) or force="slug".
	 *
	 * @param array $atts Shortcode attributes.
	 * @return array|null Snapshot force.
	 */
	public static function resolve_force( array $atts ) {
		$snapshot = self::snapshot();
		$wanted   = isset( $atts['force'] ) ? sanitize_title( $atts['force'] ) : 'auto';

		if ( 'auto' !== $wanted ) {
			return $snapshot['forces'][ $wanted ] ?? null;
		}
		$page_id = (int) get_queried_object_id();
		if ( ! $page_id ) {
			$page_id = (int) get_the_ID();
		}
		foreach ( $snapshot['forces'] as $force ) {
			if ( $page_id && (int) ( $force['page_id'] ?? 0 ) === $page_id ) {
				return $force;
			}
		}
		return null;
	}

	/**
	 * What a force-page block shows when it cannot find its force.
	 *
	 * @return string
	 */
	public static function no_force() {
		if ( current_user_can( GCO_PD_Install::CAPABILITY ) ) {
			return '<p class="gco-pd-empty">Editors only: no police force is linked to this page. Link it under Police Data &rarr; Forces, or use force="slug" in the shortcode.</p>';
		}
		return '';
	}

	/**
	 * A figure ready for display.
	 *
	 * @param array|null $metric     Snapshot metric.
	 * @param bool       $is_average Waiting time (true) or holder count (false).
	 * @return array{text:?string,basis:string,status:string}
	 */
	public static function figure( $metric, $is_average = true ) {
		if ( ! $metric || null === $metric['value'] ) {
			return array(
				'text'   => null,
				'basis'  => '',
				'status' => GCO_PD_Validator::STATUS_NOT_PROVIDED,
			);
		}
		return array(
			'text'   => $is_average ? GCO_PD_Format::days( $metric['value'] ) : GCO_PD_Format::number( $metric['value'] ),
			'basis'  => GCO_PD_Format::basis( $metric['status'], $is_average ),
			'status' => $metric['status'],
		);
	}

	/**
	 * HTML for a figure: bold value plus Actual/Estimate badge, or grey "Not provided" (never "0").
	 * Returns escaped markup.
	 *
	 * @param array $figure From figure().
	 * @return string
	 */
	public static function figure_html( array $figure ) {
		if ( null === $figure['text'] ) {
			return '<span class="gco-pd-na">Not provided</span>';
		}
		$html = '<strong>' . esc_html( $figure['text'] ) . '</strong>';
		if ( '' !== $figure['basis'] ) {
			$html .= ' <span class="gco-pd-badge gco-pd-badge--' . esc_attr( $figure['status'] ) . '">' . esc_html( $figure['basis'] ) . '</span>';
		}
		return $html;
	}

	/**
	 * HTML for a change indicator ("▼ 8 days faster"), or a dash when there is nothing to compare.
	 * Returns escaped markup.
	 *
	 * @param array|null $change From GCO_PD_Format::wait_change() / sla_change().
	 * @return string
	 */
	public static function change_html( $change ) {
		if ( ! $change ) {
			return '<span class="gco-pd-na">&mdash;</span>';
		}
		return '<span class="gco-pd-change gco-pd-change--' . esc_attr( $change['class'] ) . '"><span aria-hidden="true">' . esc_html( $change['arrow'] ) . '</span> ' . esc_html( $change['text'] ) . '</span>';
	}

	/* ------------------------------------------------------------------ Portal blocks */

	/**
	 * [gco_police_hero title="" image="123" placeholder="" browse_url="#browse" browse_text=""]Intro text[/gco_police_hero]
	 *
	 * @param array  $atts    Attributes. image = attachment id or URL.
	 * @param string $content Intro text.
	 * @return string
	 */
	public static function sc_hero( array $atts, $content = '' ) {
		$atts = shortcode_atts(
			array(
				'title'       => 'Police Firearms Licensing – England & Wales',
				'image'       => '',
				'placeholder' => 'Search for your police force…',
				'browse_url'  => '#browse',
				'browse_text' => 'Browse all 43 police forces',
			),
			$atts,
			'gco_police_hero'
		);

		$image = '';
		if ( ctype_digit( (string) $atts['image'] ) ) {
			$src   = wp_get_attachment_image_url( (int) $atts['image'], 'full' );
			$image = $src ? $src : '';
		} elseif ( '' !== $atts['image'] ) {
			$image = $atts['image'];
		}

		$total = (int) ( self::snapshot()['total_forces'] ?? 0 );
		return self::template(
			'portal-hero',
			array(
				'title'  => $atts['title'],
				'intro'  => trim( wp_strip_all_tags( (string) $content ) ),
				'image'  => $image,
				'search' => self::sc_search(
					array(
						'placeholder' => $atts['placeholder'],
						'browse_url'  => $atts['browse_url'],
						'browse_text' => $total ? str_replace( '43', (string) $total, $atts['browse_text'] ) : $atts['browse_text'],
						'variant'     => 'hero',
					)
				),
			)
		);
	}

	/**
	 * [gco_police_search browse_url="/police-firearms-licensing/#browse" placeholder="..."]
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function sc_search( array $atts ) {
		$atts     = shortcode_atts(
			array(
				'placeholder' => 'Search for your police force…',
				'browse_url'  => '',
				'browse_text' => 'Browse all police forces',
				'variant'     => '',
			),
			$atts,
			'gco_police_search'
		);
		$snapshot = self::snapshot();
		if ( ! $snapshot['forces'] ) {
			return self::empty_state();
		}

		$data = array();
		foreach ( self::forces_by_name( $snapshot ) as $force ) {
			$metric = $force['foi']['metrics']['sg_grant'] ?? null;
			$wait   = $metric && null !== $metric['value'] ? GCO_PD_Format::days( $metric['value'] ) : null;
			$data[] = array(
				'n' => $force['name'],
				's' => $force['slug'],
				'u' => self::force_url( $force ),
				'a' => $force['aliases'],
				'w' => $wait,
			);
		}

		static $instance = 0;
		++$instance;
		return self::template(
			'portal-search',
			array(
				'atts' => $atts,
				'id'   => 'gco-pd-search-' . $instance,
				// Safe inside <script type="application/json">: < > & ' " are hex-escaped.
				'json' => wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ),
			)
		);
	}

	/**
	 * [gco_police_stats cards="certificates,received,completed,sla" metric="sg_grant"]
	 *
	 * Cards: certificates, holders, received, completed (alias: applications), sla, reporting, fastest, average.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function sc_stats( array $atts ) {
		$atts     = shortcode_atts(
			array(
				'cards'  => 'certificates,received,completed,sla',
				'metric' => 'sg_grant',
			),
			$atts,
			'gco_police_stats'
		);
		$snapshot = self::snapshot();
		if ( ! $snapshot['forces'] ) {
			return self::empty_state();
		}

		$metric = in_array( $atts['metric'], self::WAIT_METRICS, true ) ? $atts['metric'] : 'sg_grant';
		$label  = strtolower( GCO_PD_Format::METRIC_LABELS[ $metric ] );
		$npcc   = $snapshot['npcc']['national'];
		$foi    = $snapshot['foi']['metrics'][ $metric ] ?? null;
		$round  = $snapshot['latest_round'] ? GCO_PD_Format::round_label( $snapshot['latest_round']['label'] ) : '';
		$source = $npcc ? 'NPCC firearms licensing statistics, ' . $npcc['quarter'] . ( $npcc['data_date'] ? ' (data to ' . GCO_PD_Format::date( $npcc['data_date'] ) . ')' : '' ) : '';
		$period = $npcc ? 'Last 12 months to the end of ' . $npcc['quarter'] . '. Source: NPCC.' : '';

		$cards = array();
		foreach ( array_map( 'trim', explode( ',', $atts['cards'] ) ) as $card ) {
			switch ( $card ) {
				case 'certificates':
					$cards[] = array( 'users', $npcc ? GCO_PD_Format::number( $npcc['certificates'] ) : null, 'Total firearm and shotgun certificates in England & Wales', $source );
					break;
				case 'holders':
					$cards[] = array( 'users', $npcc ? GCO_PD_Format::number( $npcc['holders'] ) : null, 'Firearm and shotgun certificate holders', $source );
					break;
				case 'received':
					$cards[] = array( 'document', $npcc ? GCO_PD_Format::number( $npcc['received'] ) : null, 'Applications received (reporting period)', $period );
					break;
				case 'completed':
				case 'applications':
					$cards[] = array( 'check', $npcc ? GCO_PD_Format::number( $npcc['completed'] ) : null, 'Applications completed (reporting period)', $period );
					break;
				case 'sla':
					$cards[] = array( 'chart', $npcc ? GCO_PD_Format::percent( $npcc['pct'] ) : null, 'Completed within NPCC service level agreement (SLA)', 'Share of applications completed within 4 months of receipt; the NPCC aspiration is 80%. ' . $source );
					break;
				case 'reporting':
					$cards[] = array(
						'shield',
						$snapshot['foi']['reporting'] ? $snapshot['foi']['reporting'] . ' of ' . $snapshot['total_forces'] : null,
						'Forces reporting',
						$round ? 'FOI responses, ' . $round : '',
					);
					break;
				case 'fastest':
					$fastest = $foi['fastest'] ?? null;
					$cards[] = array(
						'clock',
						$fastest ? GCO_PD_Format::days( $fastest['value'] ) : null,
						'Fastest: ' . $label . ( $fastest ? ' (' . $snapshot['forces'][ $fastest['slug'] ]['name'] . ')' : '' ),
						$round ? 'FOI responses, ' . $round : '',
					);
					break;
				case 'average':
					$cards[] = array(
						'calendar',
						$foi && null !== $foi['average'] ? GCO_PD_Format::days( round( $foi['average'] ) ) : null,
						'National average: ' . $label,
						$foi && $foi['n'] ? 'Across ' . $foi['n'] . ' forces that gave a figure' : '',
					);
					break;
			}
		}
		return self::template( 'portal-stats', array( 'cards' => $cards ) );
	}

	/**
	 * [gco_police_leaderboard limit="5" more_url="" title="" subtitle=""]  (limit="all" shows every force)
	 *
	 * Every force is printed; rows past the limit stay hidden until "View full leaderboard" is pressed.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function sc_leaderboard( array $atts ) {
		$atts     = shortcode_atts(
			self::with_card(
				array(
					'limit'       => '5',
					'more_url'    => '',
					'more_text'   => 'View full leaderboard',
					'show_change' => 'no',
					'title'       => 'National Police Force Leaderboard',
					'subtitle'    => 'Police force performance based on the latest NPCC firearms licensing data.',
				)
			),
			$atts,
			'gco_police_leaderboard'
		);
		$snapshot = self::snapshot();
		$national = $snapshot['npcc']['national'];
		if ( ! $national ) {
			return self::card( self::empty_state(), $atts, 'trophy', 'leaderboard' );
		}

		$all     = 'all' === strtolower( (string) $atts['limit'] );
		$limit   = $all ? PHP_INT_MAX : max( 1, (int) $atts['limit'] );
		$entries = array_merge( $snapshot['npcc']['leaderboard'], $snapshot['npcc']['unranked'] );

		$rows = array();
		foreach ( $entries as $i => $entry ) {
			$force  = $snapshot['forces'][ $entry['slug'] ] ?? array( 'page_id' => null );
			$rows[] = array(
				'rank'      => $entry['rank'],
				'name'      => $entry['name'],
				'url'       => self::force_url( $force ),
				'received'  => GCO_PD_Format::number( $entry['received'] ),
				'completed' => GCO_PD_Format::number( $entry['completed'] ),
				'pct'       => GCO_PD_Format::percent( $entry['pct'] ),
				'class'     => GCO_PD_Format::sla_class( $entry['pct'], $entry['meets_sla'] ),
				'change'    => GCO_PD_Format::sla_change( $entry['change_pp'] ),
				'extra'     => $i >= $limit,
			);
		}

		static $instance = 0;
		++$instance;
		$id         = 'gco-pd-leaders-' . $instance;
		$expandable = ! $all && count( $rows ) > $limit;
		$link       = '';
		if ( '' !== $atts['more_url'] ) {
			$link = '<a class="gco-pd-card__link" href="' . esc_url( $atts['more_url'] ) . '">' . esc_html( $atts['more_text'] ) . GCO_PD_Icons::get( 'arrow-right' ) . '</a>';
		} elseif ( $expandable ) {
			$link = self::expand_button( $id, $atts['more_text'] );
		}

		$body = self::template(
			'portal-leaderboard',
			array(
				'id'          => $id,
				'rows'        => $rows,
				'caption'     => 'Applications completed within 4 months of receipt, NPCC ' . $national['quarter'] . ( $national['data_date'] ? ', data to ' . GCO_PD_Format::date( $national['data_date'] ) : '' ),
				'expandable'  => $expandable && '' === $atts['more_url'],
				'limit'       => $limit,
				'total'       => count( $rows ),
				'show_change' => 'yes' === strtolower( (string) $atts['show_change'] ),
				'national'    => GCO_PD_Format::percent( $national['pct'] ),
			)
		);
		return self::card( $body, $atts, 'trophy', 'leaderboard', $link );
	}

	/**
	 * [gco_police_glance metric="sg_grant" limit="5"]
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function sc_glance( array $atts ) {
		$atts     = shortcode_atts(
			self::with_card(
				array(
					'metric'   => 'sg_grant',
					'title'    => 'Reported Waiting Times at a Glance',
					'subtitle' => 'Based on the latest FOI responses from police forces.',
				)
			),
			$atts,
			'gco_police_glance'
		);
		$metric   = in_array( $atts['metric'], self::WAIT_METRICS, true ) ? $atts['metric'] : 'sg_grant';
		$snapshot = self::snapshot();
		$ranking  = $snapshot['foi']['rankings'][ $metric ] ?? null;
		if ( ! $ranking || ! $ranking['ranked'] ) {
			return self::card( self::empty_state(), $atts, 'clock', 'glance' );
		}

		$prepare = static function ( array $entries ) use ( $snapshot ) {
			$out = array();
			foreach ( $entries as $entry ) {
				$force = $snapshot['forces'][ $entry['slug'] ] ?? array( 'page_id' => null );
				$out[] = array(
					'rank'  => $entry['rank'],
					'name'  => GCO_PD_Format::short_name( $entry['name'] ),
					'full'  => $entry['name'],
					'url'   => GCO_PD_Shortcodes::force_url( $force ),
					'days'  => GCO_PD_Format::days( $entry['days'] ),
					'basis' => GCO_PD_Format::basis( $entry['status'] ),
				);
			}
			return $out;
		};

		$body = self::template(
			'portal-glance',
			array(
				'phrase'   => self::METRIC_PHRASES[ $metric ],
				'round'    => GCO_PD_Format::round_label( $snapshot['latest_round']['label'] ?? '' ),
				'shortest' => $prepare( $ranking['shortest'] ),
				'longest'  => $prepare( $ranking['longest'] ),
				'ranked'   => $ranking['ranked'],
			)
		);
		return self::card( $body, $atts, 'clock', 'glance' );
	}

	/**
	 * [gco_police_waiting_table limit="5"]  (limit="all" shows every force)
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function sc_waiting_table( array $atts ) {
		$atts     = shortcode_atts(
			self::with_card(
				array(
					'limit'     => '5',
					'more_text' => 'View full waiting times table',
					'title'     => 'Firearms Licensing Waiting Times by Police Force',
					'subtitle'  => 'Compare the latest reported processing times for certificate applications across all 43 forces.',
				)
			),
			$atts,
			'gco_police_waiting_table'
		);
		$snapshot = self::snapshot();
		if ( ! $snapshot['foi']['reporting'] && ! array_filter( array_column( $snapshot['forces'], 'foi' ) ) ) {
			return self::card( self::empty_state(), $atts, 'calendar', 'waits' );
		}
		$atts['subtitle'] = str_replace( '43', (string) $snapshot['total_forces'], $atts['subtitle'] );

		$all   = 'all' === strtolower( (string) $atts['limit'] );
		$limit = $all ? PHP_INT_MAX : max( 1, (int) $atts['limit'] );

		static $instance = 0;
		++$instance;
		$id = 'gco-pd-waits-' . $instance;

		$tabs = array();
		foreach ( self::TABS as $key => $tab ) {
			$rows = array();
			foreach ( $snapshot['forces'] as $force ) {
				$cells = array();
				foreach ( $tab['metrics'] as $metric ) {
					$cells[ $metric ] = self::figure( $force['foi']['metrics'][ $metric ] ?? null );
				}
				$first  = $force['foi']['metrics'][ $tab['metrics'][0] ] ?? null;
				$rows[] = array(
					'name'  => $force['name'],
					'url'   => self::force_url( $force ),
					'cells' => $cells,
					'sort'  => ( $first && null !== $first['value'] ) ? (int) $first['value'] : null,
					'stale' => ! empty( $force['foi']['stale'] ),
					'round' => $force['foi']['round'] ?? null,
				);
			}
			usort(
				$rows,
				static function ( $a, $b ) {
					// Figures shortest first; forces without a figure go last, in name order (never ranked as 0).
					if ( null === $a['sort'] && null === $b['sort'] ) {
						return strcmp( $a['name'], $b['name'] );
					}
					if ( null === $a['sort'] ) {
						return 1;
					}
					if ( null === $b['sort'] ) {
						return -1;
					}
					return array( $a['sort'], $a['name'] ) <=> array( $b['sort'], $b['name'] );
				}
			);
			$rows = self::rank_rows( $rows, 'sort' );
			foreach ( $rows as $i => $row ) {
				$rows[ $i ]['extra'] = $i >= $limit;
			}

			$labels = array();
			$avgs   = array();
			foreach ( $tab['metrics'] as $metric ) {
				$labels[ $metric ] = GCO_PD_Format::METRIC_LABELS[ $metric ];
				$stat              = $snapshot['foi']['metrics'][ $metric ] ?? null;
				$avgs[ $metric ]   = ( $stat && $stat['n'] ) ? array(
					'label' => GCO_PD_Format::METRIC_LABELS[ $metric ],
					'text'  => GCO_PD_Format::days( round( $stat['average'] ) ),
					'n'     => $stat['n'],
				) : null;
			}
			$tabs[ $key ] = array(
				'label'   => $tab['label'],
				'columns' => $labels,
				'rows'    => $rows,
				'avgs'    => array_filter( $avgs ),
			);
		}

		$expandable = ! $all && count( $snapshot['forces'] ) > $limit;
		$body       = self::template(
			'portal-waiting-table',
			array(
				'id'         => $id,
				'tabs'       => $tabs,
				'round'      => GCO_PD_Format::round_label( $snapshot['latest_round']['label'] ?? '' ),
				'expandable' => $expandable,
				'limit'      => $limit,
				'total'      => count( $snapshot['forces'] ),
			)
		);
		return self::card( $body, $atts, 'calendar', 'waits', $expandable ? self::expand_button( $id, $atts['more_text'] ) : '' );
	}

	/**
	 * [gco_police_guides ids="4904,9750" category="" limit="4"]
	 *
	 * Cards for guide pages or posts: featured image, title and "Read the guide". ids keeps the given
	 * order; without ids the latest posts in category (or overall) are used.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function sc_guides( array $atts ) {
		$atts  = shortcode_atts(
			self::with_card(
				array(
					'ids'       => '',
					'category'  => '',
					'limit'     => '4',
					'read_text' => 'Read the guide',
					'title'     => 'Application and Gun Cabinet Guides',
					'subtitle'  => 'Practical advice to help with your firearms or shotgun certificate application and home security arrangements.',
				)
			),
			$atts,
			'gco_police_guides'
		);
		$limit = max( 1, min( 12, (int) $atts['limit'] ) );
		$ids   = array_filter( array_map( 'absint', explode( ',', (string) $atts['ids'] ) ) );

		$query = array(
			'post_status'         => 'publish',
			'posts_per_page'      => $limit,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		);
		if ( $ids ) {
			$query['post__in']  = $ids;
			$query['post_type'] = 'any';
			$query['orderby']   = 'post__in';
		} else {
			$query['post_type'] = 'post';
			if ( '' !== $atts['category'] ) {
				$query['category_name'] = sanitize_title( $atts['category'] );
			}
		}
		$posts = get_posts( $query );
		if ( ! $posts ) {
			return '';
		}

		$cards = array();
		foreach ( $posts as $post ) {
			$cards[] = array(
				'url'   => get_permalink( $post ),
				'title' => get_the_title( $post ),
				'image' => get_the_post_thumbnail(
					$post,
					'medium_large',
					array(
						'class'   => 'gco-pd-guide__img',
						'loading' => 'lazy',
						'alt'     => '',
					)
				),
			);
		}
		$body = self::template(
			'portal-guides',
			array(
				'cards'     => $cards,
				'read_text' => $atts['read_text'],
			)
		);
		return self::card( $body, $atts, 'book', 'guides' );
	}

	/**
	 * [gco_police_az]
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function sc_az( array $atts ) {
		$atts     = shortcode_atts(
			self::with_card(
				array(
					'all_text' => 'View all 43 police forces',
					'title'    => 'Browse All Police Forces (England & Wales)',
					'subtitle' => 'Find your local force for detailed licensing information, waiting times and application guidance.',
				)
			),
			$atts,
			'gco_police_az'
		);
		$snapshot = self::snapshot();
		if ( ! $snapshot['forces'] ) {
			return self::card( self::empty_state(), $atts, 'shield', 'az' );
		}
		$groups = array();
		foreach ( self::forces_by_name( $snapshot ) as $force ) {
			$letter              = strtoupper( substr( $force['name'], 0, 1 ) );
			$groups[ $letter ][] = array(
				'name' => $force['name'],
				'url'  => self::force_url( $force ),
			);
		}
		ksort( $groups );

		static $instance = 0;
		++$instance;
		$id       = 'gco-pd-az-' . $instance;
		$all_text = str_replace( '43', (string) count( $snapshot['forces'] ), $atts['all_text'] );
		$link     = '<a class="gco-pd-card__link" href="#' . esc_attr( $id ) . '" data-gco-az-all="' . esc_attr( $id ) . '">' . esc_html( $all_text ) . GCO_PD_Icons::get( 'arrow-right' ) . '</a>';

		$body = self::template(
			'portal-az',
			array(
				'id'     => $id,
				'groups' => $groups,
			)
		);
		return self::card( $body, $atts, 'shield', 'az', $link );
	}

	/**
	 * [gco_police_research title="" next_review="April 2027"]How we research text[/gco_police_research]
	 *
	 * "Last updated" is the publish date of the latest FOI round or NPCC quarter.
	 *
	 * @param array  $atts    Attributes.
	 * @param string $content Body text.
	 * @return string
	 */
	public static function sc_research( array $atts, $content = '' ) {
		$atts     = shortcode_atts(
			array(
				'title'       => 'How We Research UK Firearms Licensing',
				'next_review' => '',
			),
			$atts,
			'gco_police_research'
		);
		$snapshot = self::snapshot();
		$dates    = array_filter(
			array(
				$snapshot['latest_round']['published_at'] ?? null,
				$snapshot['latest_quarter']['published_at'] ?? null,
			)
		);
		$text     = trim( (string) $content );
		if ( '' === $text ) {
			$text = 'Our data is based on Freedom of Information (FOI) requests to all 43 police forces, combined with official NPCC and Home Office statistics. We analyse the responses, standardise the figures and update our information regularly.';
		}

		return self::template(
			'portal-research',
			array(
				'title'       => $atts['title'],
				'text'        => wp_kses_post( wpautop( str_replace( array( '<p>', '</p>' ), '', $text ) ) ),
				'updated'     => $dates ? GCO_PD_Format::date( max( $dates ) ) : '',
				'next_review' => $atts['next_review'],
			)
		);
	}

	/* -------------------------------------------------------------- Force-page blocks */

	/**
	 * [gco_force_waiting_times force="auto"]
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function sc_force_waiting_times( array $atts ) {
		$force = self::resolve_force( $atts );
		if ( ! $force ) {
			return self::no_force();
		}
		if ( empty( $force['foi'] ) ) {
			return '<p class="gco-pd-empty">We have not yet received a response from ' . esc_html( $force['name'] ) . '. This page will update when we do.</p>';
		}

		$rows = array();
		foreach ( self::WAIT_METRICS as $metric ) {
			$data   = $force['foi']['metrics'][ $metric ];
			$rows[] = array(
				'label'  => GCO_PD_Format::METRIC_LABELS[ $metric ],
				'figure' => self::figure( $data ),
				'change' => GCO_PD_Format::wait_change( $data['change'], $data['direction'] ),
			);
		}
		$holders = array();
		foreach ( array( 'sg_holders', 'fac_holders' ) as $metric ) {
			$holders[] = array(
				'label'  => GCO_PD_Format::METRIC_LABELS[ $metric ],
				'figure' => self::figure( $force['foi']['metrics'][ $metric ], false ),
			);
		}

		return self::template(
			'force-waiting-times',
			array(
				'force'   => $force,
				'rows'    => $rows,
				'holders' => $holders,
				'round'   => GCO_PD_Format::round_label( $force['foi']['round'] ),
				'stale'   => ! empty( $force['foi']['stale'] ),
			)
		);
	}

	/**
	 * [gco_force_rank force="auto"]
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function sc_force_rank( array $atts ) {
		$force = self::resolve_force( $atts );
		if ( ! $force ) {
			return self::no_force();
		}

		$rows = array();
		if ( ! empty( $force['npcc'] ) ) {
			$npcc   = $force['npcc'];
			$rows[] = array(
				'label' => 'Applications completed within 4 months',
				'rank'  => $npcc['rank'] ? GCO_PD_Format::ordinal( $npcc['rank'] ) . ' of ' . $npcc['ranked_of'] : null,
				'note'  => GCO_PD_Format::percent( $npcc['pct'] ),
			);
		}
		foreach ( self::WAIT_METRICS as $metric ) {
			$data   = $force['foi']['metrics'][ $metric ] ?? null;
			$rows[] = array(
				'label' => GCO_PD_Format::METRIC_LABELS[ $metric ],
				'rank'  => ( $data && $data['rank'] ) ? GCO_PD_Format::ordinal( $data['rank'] ) . ' of ' . $data['ranked_of'] : null,
				'note'  => ( $data && null !== $data['value'] ) ? GCO_PD_Format::days( $data['value'] ) : null,
			);
		}
		if ( ! array_filter( array_column( $rows, 'rank' ) ) ) {
			return '';
		}

		return self::template(
			'force-rank',
			array(
				'force' => $force,
				'rows'  => $rows,
			)
		);
	}

	/**
	 * [gco_force_trend force="auto" metrics="sg_grant,fac_grant"]
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function sc_force_trend( array $atts ) {
		$force = self::resolve_force( $atts );
		if ( ! $force ) {
			return self::no_force();
		}
		$atts = shortcode_atts(
			array(
				'force'   => 'auto',
				'metrics' => 'sg_grant,fac_grant',
			),
			$atts,
			'gco_force_trend'
		);
		if ( empty( $force['trend'] ) ) {
			return '';
		}

		$chart_metrics = array_values( array_intersect( array_map( 'trim', explode( ',', $atts['metrics'] ) ), self::WAIT_METRICS ) );
		$labels        = array();
		$rows          = array();
		$series        = array();
		foreach ( $force['trend'] as $point ) {
			$labels[] = GCO_PD_Format::round_label( $point['round'] );
			$row      = array( 'round' => GCO_PD_Format::round_label( $point['round'] ), 'cells' => array() );
			foreach ( self::WAIT_METRICS as $metric ) {
				$row['cells'][ $metric ] = self::figure( $point['metrics'][ $metric ] );
			}
			$rows[] = $row;
		}
		foreach ( $chart_metrics as $metric ) {
			$name            = GCO_PD_Format::METRIC_LABELS[ $metric ];
			$series[ $name ] = array();
			foreach ( $force['trend'] as $point ) {
				$series[ $name ][] = $point['metrics'][ $metric ]['value'];
			}
		}

		$chart = count( $force['trend'] ) > 1 ? GCO_PD_SVG_Chart::line(
			$series,
			$labels,
			array(
				'title'       => $force['name'] . ' waiting times',
				'description' => 'Waiting time in days for each published round of FOI data. A gap means the figure was not provided.',
				'unit'        => 'days',
			)
		) : '';

		return self::template(
			'force-trend',
			array(
				'force'   => $force,
				'rows'    => $rows,
				'columns' => array_intersect_key( GCO_PD_Format::METRIC_LABELS, array_flip( self::WAIT_METRICS ) ),
				'chart'   => $chart,
			)
		);
	}

	/**
	 * [gco_force_context force="auto"]
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function sc_force_context( array $atts ) {
		$force = self::resolve_force( $atts );
		if ( ! $force ) {
			return self::no_force();
		}
		$snapshot = self::snapshot();

		$rows = array();
		if ( ! empty( $force['foi'] ) ) {
			foreach ( self::WAIT_METRICS as $metric ) {
				$data    = $force['foi']['metrics'][ $metric ];
				$stat    = $snapshot['foi']['metrics'][ $metric ] ?? null;
				$average = ( $stat && $stat['n'] ) ? round( $stat['average'] ) : null;
				$rows[]  = array(
					'label'   => GCO_PD_Format::METRIC_LABELS[ $metric ],
					'force'   => GCO_PD_Format::days( $data['value'] ),
					'average' => null === $average ? null : GCO_PD_Format::days( $average ),
					'verdict' => GCO_PD_Format::versus_average( $data['value'], $average ),
					'n'       => $stat['n'] ?? 0,
				);
			}
		}

		$facts = array();
		$npcc  = $force['npcc'] ?? null;
		if ( $npcc ) {
			if ( null !== $npcc['pct'] ) {
				$facts[] = sprintf(
					'%1$s completed %2$s of applications within 4 months (national figure %3$s; target 80%%).',
					$force['name'],
					GCO_PD_Format::percent( $npcc['pct'] ),
					GCO_PD_Format::percent( $snapshot['npcc']['national']['pct'] ?? null ) ?? 'not available'
				);
			}
			if ( null !== $npcc['holders'] ) {
				$facts[] = GCO_PD_Format::number( $npcc['holders'] ) . ' certificate holders in total (NPCC ' . $npcc['quarter'] . ').';
			}
			if ( null !== $npcc['completed'] ) {
				$facts[] = GCO_PD_Format::number( $npcc['completed'] ) . ' applications completed in the last 12 months.';
			}
		}
		if ( ! empty( $force['foi'] ) ) {
			foreach ( array( 'sg_holders', 'fac_holders' ) as $metric ) {
				$value = $force['foi']['metrics'][ $metric ]['value'];
				if ( null !== $value ) {
					$facts[] = GCO_PD_Format::number( $value ) . ' ' . strtolower( GCO_PD_Format::METRIC_LABELS[ $metric ] ) . ' (FOI response).';
				}
			}
		}

		if ( ! $rows && ! $facts ) {
			return '';
		}
		return self::template(
			'force-context',
			array(
				'force' => $force,
				'rows'  => $rows,
				'facts' => $facts,
			)
		);
	}

	/**
	 * [gco_force_updated force="auto"]
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function sc_force_updated( array $atts ) {
		$force = self::resolve_force( $atts );
		if ( ! $force ) {
			return self::no_force();
		}
		$snapshot = self::snapshot();
		$foi      = $force['foi'] ?? null;
		$npcc     = $force['npcc'] ?? null;
		if ( ! $foi && ! $npcc ) {
			return '';
		}

		$pdf = '';
		if ( $foi && ! empty( $foi['pdf_id'] ) && GCO_PD_Settings::get( 'show_pdf_public' ) ) {
			$url = wp_get_attachment_url( (int) $foi['pdf_id'] );
			$pdf = $url ? $url : '';
		}

		return self::template(
			'force-updated',
			array(
				'force'   => $force,
				'updated' => $foi ? GCO_PD_Format::round_label( $foi['round'] ) : '',
				'stale'   => $foi && ! empty( $foi['stale'] ),
				'latest'  => GCO_PD_Format::round_label( $snapshot['latest_round']['label'] ?? '' ),
				'npcc'    => $npcc ? 'NPCC Firearms Licensing ' . $npcc['quarter'] . ( ! empty( $snapshot['latest_quarter']['data_date'] ) ? ', data to ' . GCO_PD_Format::date( $snapshot['latest_quarter']['data_date'] ) : '' ) : '',
				'pdf'     => $pdf,
			)
		);
	}

	/**
	 * [gco_force_page force="auto" part="all|hero|body"]
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function sc_force_page( array $atts ) {
		return GCO_PD_Force_Page::render( $atts );
	}

	/* ------------------------------------------------------------------------- FAQs */

	/**
	 * [gco_police_faq_schema title="Frequently Asked Questions" columns="2"]
	 *   [vc_toggle title="Question"]Answer[/vc_toggle] ...
	 * [/gco_police_faq_schema]
	 *
	 * Editors keep adding WPBakery Toggle elements; this renders them as the portal's accordion (native
	 * <details>, no JavaScript) and adds one FAQPage JSON-LD block built from the same questions. Scoped
	 * to this wrapper, not every vc_toggle on the site, so it never duplicates the SEO plugin's schema.
	 *
	 * @param array  $atts    Attributes.
	 * @param string $content Enclosed WPBakery markup.
	 * @return string
	 */
	public static function sc_faq_schema( array $atts, $content = '' ) {
		$atts  = shortcode_atts(
			array(
				'title'   => 'Frequently Asked Questions',
				'columns' => '2',
				'schema'  => 'yes',
			),
			$atts,
			'gco_police_faq_schema'
		);
		$pairs = self::extract_faq_pairs( (string) $content );
		if ( ! $pairs ) {
			return do_shortcode( (string) $content );
		}
		return self::template(
			'portal-faqs',
			array(
				'title'   => $atts['title'],
				'columns' => max( 1, min( 2, (int) $atts['columns'] ) ),
				'pairs'   => $pairs,
				'schema'  => 'no' !== strtolower( (string) $atts['schema'] ),
			)
		);
	}

	/**
	 * Pulls question/answer pairs out of nested `vc_toggle` shortcodes.
	 *
	 * @param string $content Raw enclosed shortcode markup.
	 * @return array{question:string,answer:string,html:string}[] answer is plain text (schema), html is the display markup.
	 */
	private static function extract_faq_pairs( $content ) {
		$pairs = array();
		if ( ! preg_match_all( '/' . get_shortcode_regex( array( 'vc_toggle' ) ) . '/s', $content, $matches ) ) {
			return $pairs;
		}
		foreach ( array_keys( $matches[0] ) as $i ) {
			$shortcode_atts = shortcode_parse_atts( $matches[3][ $i ] );
			$question       = is_array( $shortcode_atts ) && ! empty( $shortcode_atts['title'] ) ? trim( html_entity_decode( (string) $shortcode_atts['title'], ENT_QUOTES, 'UTF-8' ) ) : '';
			// wpautop leaves stray <p>/</p> around toggle content; strip them and re-paragraph.
			$raw    = trim( str_replace( array( '<p>', '</p>' ), '', preg_replace( '#<p[^>]*>#', '', (string) $matches[5][ $i ] ) ) );
			$answer = trim( preg_replace( '/\s+/', ' ', html_entity_decode( wp_strip_all_tags( $raw ), ENT_QUOTES, 'UTF-8' ) ) );
			if ( '' !== $question && '' !== $answer ) {
				$pairs[] = array(
					'question' => $question,
					'answer'   => $answer,
					'html'     => wp_kses_post( wpautop( do_shortcode( $raw ) ) ),
				);
			}
		}
		return $pairs;
	}
}
