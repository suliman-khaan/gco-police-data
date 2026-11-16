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
		'gco_police_search'        => 'search',
		'gco_police_stats'         => 'stats',
		'gco_police_leaderboard'   => 'leaderboard',
		'gco_police_glance'        => 'glance',
		'gco_police_waiting_table' => 'waiting_table',
		'gco_police_az'            => 'az',
		'gco_force_waiting_times'  => 'force_waiting_times',
		'gco_force_rank'           => 'force_rank',
		'gco_force_trend'          => 'force_trend',
		'gco_force_context'        => 'force_context',
		'gco_force_updated'        => 'force_updated',
	);

	/**
	 * Tags that need front.js.
	 *
	 * @var string[]
	 */
	const INTERACTIVE = array( 'gco_police_search', 'gco_police_waiting_table', 'gco_police_az' );

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
			'label'   => 'Shotgun',
			'metrics' => array( 'sg_grant' ),
		),
		'firearm'    => array(
			'label'   => 'Firearm',
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
	 * Registers shortcodes and asset hooks.
	 */
	public static function init() {
		foreach ( self::TAGS as $tag => $method ) {
			add_shortcode(
				$tag,
				static function ( $atts, $content, $shortcode_tag ) use ( $method ) {
					return GCO_PD_Shortcodes::run( $method, $shortcode_tag, is_array( $atts ) ? $atts : array() );
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
	 * @param string $method Method suffix.
	 * @param string $tag    Shortcode tag.
	 * @param array  $atts   Attributes.
	 * @return string
	 */
	public static function run( $method, $tag, array $atts ) {
		self::enqueue( in_array( $tag, self::INTERACTIVE, true ) );
		$html = (string) call_user_func( array( __CLASS__, 'sc_' . $method ), $atts );
		return $html;
	}

	/**
	 * Renders a template (theme override first).
	 *
	 * @param string $name Template name without extension.
	 * @param array  $vars Variables.
	 * @return string
	 */
	private static function template( $name, array $vars ) {
		$path = locate_template( array( 'gco-police-data/' . $name . '.php' ) );
		if ( ! $path ) {
			$path = GCO_PD_DIR . 'templates/' . $name . '.php';
		}
		ob_start();
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		extract( $vars, EXTR_SKIP );
		include $path;
		return (string) ob_get_clean();
	}

	/**
	 * The snapshot.
	 *
	 * @return array
	 */
	private static function snapshot() {
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
	private static function resolve_force( array $atts ) {
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
	private static function no_force() {
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
	private static function figure( $metric, $is_average = true ) {
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
	 * [gco_police_search browse_url="/police-firearms-licensing/#browse" placeholder="..."]
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function sc_search( array $atts ) {
		$atts     = shortcode_atts(
			array(
				'placeholder' => 'Search your police force, e.g. Derbyshire or GMP',
				'browse_url'  => '',
				'browse_text' => 'Browse all forces',
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
	 * [gco_police_stats cards="holders,reporting,fastest,average" metric="sg_grant"]
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function sc_stats( array $atts ) {
		$atts     = shortcode_atts(
			array(
				'cards'  => 'holders,reporting,fastest,average',
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
		$quart  = $npcc ? 'NPCC ' . $npcc['quarter'] : '';

		$cards = array();
		foreach ( array_map( 'trim', explode( ',', $atts['cards'] ) ) as $card ) {
			switch ( $card ) {
				case 'holders':
					$cards[] = array( 'Certificate holders', $npcc ? GCO_PD_Format::number( $npcc['holders'] ) : null, $quart );
					break;
				case 'applications':
					$cards[] = array( 'Applications completed (12 months)', $npcc ? GCO_PD_Format::number( $npcc['completed'] ) : null, $quart );
					break;
				case 'sla':
					$cards[] = array( 'Completed within 4 months', $npcc ? GCO_PD_Format::percent( $npcc['pct'] ) : null, $npcc ? 'National figure, target 80%' : '' );
					break;
				case 'reporting':
					$cards[] = array(
						'Forces reporting',
						$snapshot['foi']['reporting'] ? $snapshot['foi']['reporting'] . ' of ' . $snapshot['total_forces'] : null,
						$round ? 'FOI responses, ' . $round : '',
					);
					break;
				case 'fastest':
					$fastest = $foi['fastest'] ?? null;
					$cards[] = array(
						'Fastest: ' . $label,
						$fastest ? GCO_PD_Format::days( $fastest['value'] ) : null,
						$fastest ? $snapshot['forces'][ $fastest['slug'] ]['name'] : '',
					);
					break;
				case 'average':
					$cards[] = array(
						'National average: ' . $label,
						$foi && null !== $foi['average'] ? GCO_PD_Format::days( round( $foi['average'] ) ) : null,
						$foi && $foi['n'] ? 'Across ' . $foi['n'] . ' forces' : '',
					);
					break;
			}
		}
		return self::template( 'portal-stats', array( 'cards' => $cards ) );
	}

	/**
	 * [gco_police_leaderboard limit="5" more_url="" more_text=""]  (limit="all" for the full table)
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function sc_leaderboard( array $atts ) {
		$atts     = shortcode_atts(
			array(
				'limit'     => '5',
				'more_url'  => '',
				'more_text' => 'See the full leaderboard',
			),
			$atts,
			'gco_police_leaderboard'
		);
		$snapshot = self::snapshot();
		$national = $snapshot['npcc']['national'];
		if ( ! $national ) {
			return self::empty_state();
		}

		$all   = 'all' === strtolower( (string) $atts['limit'] );
		$limit = $all ? PHP_INT_MAX : max( 1, (int) $atts['limit'] );

		$entries = $snapshot['npcc']['leaderboard'];
		if ( $all ) {
			$entries = array_merge( $entries, $snapshot['npcc']['unranked'] );
		}
		$rows = array();
		foreach ( array_slice( $entries, 0, $limit ) as $entry ) {
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
			);
		}

		return self::template(
			'portal-leaderboard',
			array(
				'rows'      => $rows,
				'caption'   => 'Applications completed within 4 months of receipt, NPCC ' . $national['quarter'] . ( $national['data_date'] ? ', data to ' . GCO_PD_Format::date( $national['data_date'] ) : '' ),
				'show_more' => ! $all && count( $entries ) > $limit && '' !== $atts['more_url'],
				'more_url'  => $atts['more_url'],
				'more_text' => $atts['more_text'],
				'national'  => GCO_PD_Format::percent( $national['pct'] ),
			)
		);
	}

	/**
	 * [gco_police_glance metric="sg_grant"]
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function sc_glance( array $atts ) {
		$atts     = shortcode_atts( array( 'metric' => 'sg_grant' ), $atts, 'gco_police_glance' );
		$metric   = in_array( $atts['metric'], self::WAIT_METRICS, true ) ? $atts['metric'] : 'sg_grant';
		$snapshot = self::snapshot();
		$ranking  = $snapshot['foi']['rankings'][ $metric ] ?? null;
		if ( ! $ranking || ! $ranking['ranked'] ) {
			return self::empty_state();
		}

		$prepare = static function ( array $entries ) use ( $snapshot ) {
			$out = array();
			foreach ( $entries as $entry ) {
				$force = $snapshot['forces'][ $entry['slug'] ] ?? array( 'page_id' => null );
				$out[] = array(
					'rank'  => $entry['rank'],
					'name'  => $entry['name'],
					'url'   => GCO_PD_Shortcodes::force_url( $force ),
					'days'  => GCO_PD_Format::days( $entry['days'] ),
					'basis' => GCO_PD_Format::basis( $entry['status'] ),
				);
			}
			return $out;
		};

		return self::template(
			'portal-glance',
			array(
				'label'    => GCO_PD_Format::METRIC_LABELS[ $metric ],
				'round'    => GCO_PD_Format::round_label( $snapshot['latest_round']['label'] ?? '' ),
				'shortest' => $prepare( $ranking['shortest'] ),
				'longest'  => $prepare( $ranking['longest'] ),
				'ranked'   => $ranking['ranked'],
			)
		);
	}

	/**
	 * [gco_police_waiting_table]
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function sc_waiting_table( array $atts ) {
		unset( $atts );
		$snapshot = self::snapshot();
		if ( ! $snapshot['foi']['reporting'] && ! array_filter( array_column( $snapshot['forces'], 'foi' ) ) ) {
			return self::empty_state();
		}

		static $instance = 0;
		++$instance;

		$tabs = array();
		foreach ( self::TABS as $key => $tab ) {
			$rows = array();
			foreach ( $snapshot['forces'] as $force ) {
				$cells = array();
				foreach ( $tab['metrics'] as $metric ) {
					$cells[ $metric ] = self::figure( $force['foi']['metrics'][ $metric ] ?? null );
				}
				$first      = $force['foi']['metrics'][ $tab['metrics'][0] ] ?? null;
				$rows[]     = array(
					'name'  => $force['name'],
					'url'   => self::force_url( $force ),
					'cells' => $cells,
					'sort'  => ( $first && null !== $first['value'] ) ? $first['value'] : null,
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

			$labels = array();
			$avgs   = array();
			foreach ( $tab['metrics'] as $metric ) {
				$labels[ $metric ] = GCO_PD_Format::METRIC_LABELS[ $metric ];
				$stat              = $snapshot['foi']['metrics'][ $metric ] ?? null;
				$avgs[ $metric ]   = ( $stat && $stat['n'] ) ? array(
					'text' => GCO_PD_Format::days( round( $stat['average'] ) ),
					'n'    => $stat['n'],
				) : null;
			}
			$tabs[ $key ] = array(
				'label'   => $tab['label'],
				'columns' => $labels,
				'rows'    => $rows,
				'avgs'    => $avgs,
			);
		}

		return self::template(
			'portal-waiting-table',
			array(
				'id'    => 'gco-pd-waits-' . $instance,
				'tabs'  => $tabs,
				'round' => GCO_PD_Format::round_label( $snapshot['latest_round']['label'] ?? '' ),
			)
		);
	}

	/**
	 * [gco_police_az]
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function sc_az( array $atts ) {
		unset( $atts );
		$snapshot = self::snapshot();
		if ( ! $snapshot['forces'] ) {
			return self::empty_state();
		}
		$groups = array();
		foreach ( self::forces_by_name( $snapshot ) as $force ) {
			$letter = strtoupper( substr( $force['name'], 0, 1 ) );
			$groups[ $letter ][] = array(
				'name' => $force['name'],
				'url'  => self::force_url( $force ),
			);
		}
		ksort( $groups );

		static $instance = 0;
		++$instance;
		return self::template(
			'portal-az',
			array(
				'id'     => 'gco-pd-az-' . $instance,
				'groups' => $groups,
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
}
