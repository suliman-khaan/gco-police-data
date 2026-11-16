<?php
/**
 * Calculator: turns published FOI rounds and NPCC quarters into one display-ready snapshot.
 *
 * Pure PHP (no WordPress, no database): GCO_PD_Cache gathers the rows and stores the result, and the
 * shortcodes only read it. Rules:
 *  - NULL / "not provided" is never 0: it is never ranked, never averaged, never summed.
 *  - Ties share a rank (1, 2, 2, 4).
 *  - Each force's "current" FOI figures come from its most recent published round; a force with no row in
 *    the latest round keeps its last figures and is flagged stale.
 *  - % within SLA is always calculated (completed in SLA / completed) and compared with integers.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class GCO_PD_Calculator
 */
final class GCO_PD_Calculator {

	/**
	 * Snapshot layout version; bump when the structure changes so old snapshots are rebuilt.
	 */
	const SCHEMA = 1;

	/**
	 * Metrics that can be ranked (shorter wait is better). Holders are not ranked.
	 *
	 * @var string[]
	 */
	const RANKED_METRICS = array( 'sg_grant', 'fac_grant', 'sg_renew', 'fac_renew', 'variation' );

	/**
	 * NPCC / Home Office aspiration: 80% completed within 4 months, as a fraction 4/5.
	 */
	const SLA_NUM = 4;
	const SLA_DEN = 5;

	/**
	 * How many forces the "at a glance" lists show.
	 */
	const GLANCE_SIZE = 5;

	/**
	 * Builds the snapshot.
	 *
	 * @param array $input {
	 *     @type array[] $forces    id, slug, name, country, aliases, page_id, is_active.
	 *     @type array[] $rounds    PUBLISHED rounds: id, label, published_at.
	 *     @type array[] $responses Rows of published rounds (response columns + round_id, force_id).
	 *     @type array[] $quarters  PUBLISHED quarters: id, label, data_date, published_at.
	 *     @type array[] $npcc      Rows of published quarters (NPCC columns + quarter_id, force_id).
	 * }
	 * @return array Snapshot.
	 */
	public static function build( array $input ) {
		$forces = array();
		foreach ( $input['forces'] ?? array() as $force ) {
			if ( ! empty( $force['is_active'] ) ) {
				$forces[ (int) $force['id'] ] = $force;
			}
		}

		$rounds   = self::sorted_by_label( $input['rounds'] ?? array() );
		$quarters = self::sorted_by_label( $input['quarters'] ?? array() );

		$snapshot = self::empty_snapshot();
		foreach ( $forces as $force ) {
			$snapshot['forces'][ $force['slug'] ] = array(
				'id'      => (int) $force['id'],
				'slug'    => $force['slug'],
				'name'    => $force['name'],
				'country' => $force['country'] ?? 'England',
				'aliases' => self::split_aliases( $force['aliases'] ?? '' ),
				'page_id' => empty( $force['page_id'] ) ? null : (int) $force['page_id'],
				'foi'     => null,
				'npcc'    => null,
				'trend'   => array(),
			);
		}
		$snapshot['total_forces'] = count( $forces );

		self::build_foi( $snapshot, $forces, $rounds, $input['responses'] ?? array() );
		self::build_npcc( $snapshot, $forces, $quarters, $input['npcc'] ?? array() );

		return $snapshot;
	}

	/**
	 * Snapshot used before any data is published.
	 *
	 * @return array
	 */
	public static function empty_snapshot() {
		return array(
			'schema'       => self::SCHEMA,
			'built_at'     => null,
			'total_forces' => 0,
			'forces'       => array(),
			'latest_round' => null,
			'latest_quarter' => null,
			'rounds'       => array(),
			'quarters'     => array(),
			'foi'          => array(
				'reporting'   => 0,
				'metrics'     => array(),
				'rankings'    => array(),
				'coverage'    => array(
					'actual'       => 0,
					'estimate'     => 0,
					'not_provided' => 0,
				),
				'holders'     => array(),
			),
			'npcc'         => array(
				'national'    => null,
				'leaderboard' => array(),
				'unranked'    => array(),
			),
		);
	}

	/* ----------------------------------------------------------------------- FOI */

	/**
	 * Fills the FOI part of the snapshot.
	 *
	 * @param array $snapshot  Snapshot (modified).
	 * @param array $forces    Active forces by id.
	 * @param array $rounds    Published rounds, oldest first.
	 * @param array $responses Response rows.
	 */
	private static function build_foi( array &$snapshot, array $forces, array $rounds, array $responses ) {
		if ( ! $rounds ) {
			return;
		}

		$label_by_round = array();
		$index_by_label = array();
		foreach ( $rounds as $i => $round ) {
			$label_by_round[ (int) $round['id'] ] = $round['label'];
			$index_by_label[ $round['label'] ]    = $i;
			$snapshot['rounds'][]                 = array(
				'label'        => $round['label'],
				'published_at' => $round['published_at'] ?? null,
			);
		}
		$latest_label              = $rounds[ count( $rounds ) - 1 ]['label'];
		$snapshot['latest_round'] = array(
			'label'        => $latest_label,
			'published_at' => $rounds[ count( $rounds ) - 1 ]['published_at'] ?? null,
		);

		// force id => round label => row.
		$by_force = array();
		foreach ( $responses as $row ) {
			$force_id = (int) $row['force_id'];
			$label    = $label_by_round[ (int) $row['round_id'] ] ?? null;
			if ( null === $label || ! isset( $forces[ $force_id ] ) ) {
				continue;
			}
			$by_force[ $force_id ][ $label ] = $row;
		}

		$current  = array(); // slug => array{label, row, metrics}.
		$coverage = array(
			'actual'       => 0,
			'estimate'     => 0,
			'not_provided' => 0,
		);
		$reporting = 0;

		foreach ( $forces as $force_id => $force ) {
			$slug = $force['slug'];
			if ( empty( $by_force[ $force_id ] ) ) {
				continue;
			}
			$labels = array_keys( $by_force[ $force_id ] );
			usort(
				$labels,
				static function ( $a, $b ) use ( $index_by_label ) {
					return $index_by_label[ $a ] <=> $index_by_label[ $b ];
				}
			);

			// Trend: every published round this force answered, oldest first.
			foreach ( $labels as $label ) {
				$snapshot['forces'][ $slug ]['trend'][] = array(
					'round'   => $label,
					'metrics' => self::metric_values( $by_force[ $force_id ][ $label ] ),
				);
			}

			$label   = $labels[ count( $labels ) - 1 ];
			$row     = $by_force[ $force_id ][ $label ];
			$metrics = self::metric_values( $row );
			$idx     = $index_by_label[ $label ];

			// Change vs the adjacent previous round, only when this force answered it too.
			$previous = null;
			if ( $idx > 0 && isset( $by_force[ $force_id ][ $rounds[ $idx - 1 ]['label'] ] ) ) {
				$previous = self::metric_values( $by_force[ $force_id ][ $rounds[ $idx - 1 ]['label'] ] );
			}
			foreach ( $metrics as $key => $metric ) {
				$metrics[ $key ]['rank']      = null;
				$metrics[ $key ]['ranked_of'] = null;
				$metrics[ $key ]['change']    = null;
				$metrics[ $key ]['direction'] = null;
				if ( $previous && self::usable( $metric ) && self::usable( $previous[ $key ] ) ) {
					$delta                        = $metric['value'] - $previous[ $key ]['value'];
					$metrics[ $key ]['change']    = $delta;
					$metrics[ $key ]['direction'] = $delta < 0 ? 'down' : ( $delta > 0 ? 'up' : 'same' );
				}
			}

			$current[ $slug ] = array(
				'label'   => $label,
				'metrics' => $metrics,
			);

			$snapshot['forces'][ $slug ]['foi'] = array(
				'round'         => $label,
				'stale'         => $label !== $latest_label,
				'response_date' => $row['response_date'] ?? null,
				'foi_ref'       => isset( $row['foi_ref'] ) && '' !== $row['foi_ref'] ? (string) $row['foi_ref'] : null,
				'pdf_id'        => empty( $row['pdf_id'] ) ? null : (int) $row['pdf_id'],
				'metrics'       => $metrics,
				'coverage'      => self::coverage( $metrics ),
			);

			if ( $label === $latest_label ) {
				$any = false;
				foreach ( $metrics as $metric ) {
					if ( self::usable( $metric ) ) {
						$any = true;
					}
					++$coverage[ self::status_of( $metric ) ];
				}
				if ( $any ) {
					++$reporting;
				}
			}
		}

		$snapshot['foi']['reporting'] = $reporting;
		$snapshot['foi']['coverage']  = $coverage;

		// Rankings and the at-a-glance lists.
		foreach ( self::RANKED_METRICS as $key ) {
			$entries = array();
			foreach ( $current as $slug => $data ) {
				if ( self::usable( $data['metrics'][ $key ] ) ) {
					$entries[] = array(
						'slug'   => $slug,
						'name'   => $snapshot['forces'][ $slug ]['name'],
						'days'   => $data['metrics'][ $key ]['value'],
						'status' => $data['metrics'][ $key ]['status'],
					);
				}
			}
			usort(
				$entries,
				static function ( $a, $b ) {
					return array( $a['days'], $a['name'] ) <=> array( $b['days'], $b['name'] );
				}
			);
			$entries = self::assign_ranks( $entries, array( 'days' ) );
			$count   = count( $entries );

			foreach ( $entries as $entry ) {
				$snapshot['forces'][ $entry['slug'] ]['foi']['metrics'][ $key ]['rank']      = $entry['rank'];
				$snapshot['forces'][ $entry['slug'] ]['foi']['metrics'][ $key ]['ranked_of'] = $count;
			}

			$longest = $entries;
			usort(
				$longest,
				static function ( $a, $b ) {
					return array( $b['days'], $a['name'] ) <=> array( $a['days'], $b['name'] );
				}
			);

			$snapshot['foi']['rankings'][ $key ] = array(
				'ranked'   => $count,
				'shortest' => array_slice( $entries, 0, self::GLANCE_SIZE ),
				'longest'  => array_slice( $longest, 0, self::GLANCE_SIZE ),
			);
		}

		// National figures per metric over each force's current usable value.
		foreach ( array_keys( GCO_PD_Validator::FOI_METRICS ) as $key ) {
			$values    = array();
			$estimates = 0;
			$fastest   = null;
			foreach ( $current as $slug => $data ) {
				$metric = $data['metrics'][ $key ];
				if ( ! self::usable( $metric ) ) {
					continue;
				}
				$values[] = $metric['value'];
				if ( GCO_PD_Validator::STATUS_ESTIMATE === $metric['status'] ) {
					++$estimates;
				}
				if ( null === $fastest || $metric['value'] < $fastest['value'] ) {
					$fastest = array(
						'slug'  => $slug,
						'value' => $metric['value'],
					);
				}
			}
			$snapshot['foi']['metrics'][ $key ] = self::describe( $values, $estimates, $fastest );
		}
		foreach ( array( 'sg_holders', 'fac_holders' ) as $key ) {
			$snapshot['foi']['holders'][ $key ] = array(
				'n'   => $snapshot['foi']['metrics'][ $key ]['n'],
				'sum' => $snapshot['foi']['metrics'][ $key ]['sum'],
			);
		}
	}

	/**
	 * Reads the seven metrics from a response row.
	 *
	 * @param array $row Response row.
	 * @return array<string,array{value:?int,status:string}>
	 */
	private static function metric_values( array $row ) {
		$out = array();
		foreach ( GCO_PD_Validator::FOI_METRICS as $key => $meta ) {
			$value  = $row[ $meta['column'] ] ?? null;
			$value  = ( null === $value || '' === $value ) ? null : (int) $value;
			$status = $row[ $meta['status'] ] ?? GCO_PD_Validator::STATUS_NOT_PROVIDED;
			if ( null === $value || ! in_array( $status, array( GCO_PD_Validator::STATUS_ACTUAL, GCO_PD_Validator::STATUS_ESTIMATE ), true ) ) {
				// A value without a usable status, or a status without a value, is not a figure.
				$value  = null;
				$status = GCO_PD_Validator::STATUS_NOT_PROVIDED;
			}
			$out[ $key ] = array(
				'value'  => $value,
				'status' => $status,
			);
		}
		return $out;
	}

	/**
	 * Whether a metric holds a real figure that may be ranked and averaged.
	 *
	 * @param array $metric Metric.
	 * @return bool
	 */
	private static function usable( array $metric ) {
		return null !== $metric['value'] && GCO_PD_Validator::STATUS_NOT_PROVIDED !== $metric['status'];
	}

	/**
	 * Status key for coverage counting.
	 *
	 * @param array $metric Metric.
	 * @return string
	 */
	private static function status_of( array $metric ) {
		return self::usable( $metric ) ? $metric['status'] : GCO_PD_Validator::STATUS_NOT_PROVIDED;
	}

	/**
	 * Counts actual / estimate / not provided across a force's metrics.
	 *
	 * @param array $metrics Metrics.
	 * @return array<string,int>
	 */
	private static function coverage( array $metrics ) {
		$counts = array(
			'actual'       => 0,
			'estimate'     => 0,
			'not_provided' => 0,
		);
		foreach ( $metrics as $metric ) {
			++$counts[ self::status_of( $metric ) ];
		}
		return $counts;
	}

	/**
	 * Summary of a list of values. Empty list gives nulls, never zeros.
	 *
	 * @param int[]      $values    Values.
	 * @param int        $estimates How many were estimates.
	 * @param array|null $fastest   Smallest value and its force.
	 * @return array
	 */
	private static function describe( array $values, $estimates, $fastest ) {
		$n = count( $values );
		if ( 0 === $n ) {
			return array(
				'n'         => 0,
				'estimates' => 0,
				'sum'       => null,
				'average'   => null,
				'median'    => null,
				'min'       => null,
				'max'       => null,
				'fastest'   => null,
			);
		}
		sort( $values );
		$middle = intdiv( $n, 2 );
		$median = ( 1 === $n % 2 ) ? $values[ $middle ] : ( $values[ $middle - 1 ] + $values[ $middle ] ) / 2;
		return array(
			'n'         => $n,
			'estimates' => (int) $estimates,
			'sum'       => array_sum( $values ),
			'average'   => round( array_sum( $values ) / $n, 1 ),
			'median'    => $median,
			'min'       => $values[0],
			'max'       => $values[ $n - 1 ],
			'fastest'   => $fastest,
		);
	}

	/* ---------------------------------------------------------------------- NPCC */

	/**
	 * Fills the NPCC part of the snapshot.
	 *
	 * @param array $snapshot Snapshot (modified).
	 * @param array $forces   Active forces by id.
	 * @param array $quarters Published quarters, oldest first.
	 * @param array $rows     NPCC rows.
	 */
	private static function build_npcc( array &$snapshot, array $forces, array $quarters, array $rows ) {
		if ( ! $quarters ) {
			return;
		}

		$label_by_quarter = array();
		foreach ( $quarters as $quarter ) {
			$label_by_quarter[ (int) $quarter['id'] ] = $quarter['label'];
			$snapshot['quarters'][]                   = array(
				'label'     => $quarter['label'],
				'data_date' => $quarter['data_date'] ?? null,
			);
		}
		$latest   = $quarters[ count( $quarters ) - 1 ];
		$previous = count( $quarters ) > 1 ? $quarters[ count( $quarters ) - 2 ] : null;

		$snapshot['latest_quarter'] = array(
			'label'     => $latest['label'],
			'data_date' => $latest['data_date'] ?? null,
		);

		$latest_rows   = array(); // force id => row.
		$previous_rows = array();
		foreach ( $rows as $row ) {
			$force_id = (int) $row['force_id'];
			if ( ! isset( $forces[ $force_id ] ) ) {
				continue;
			}
			$quarter_id = (int) $row['quarter_id'];
			if ( $quarter_id === (int) $latest['id'] ) {
				$latest_rows[ $force_id ] = $row;
			} elseif ( $previous && $quarter_id === (int) $previous['id'] ) {
				$previous_rows[ $force_id ] = $row;
			}
		}

		$entries   = array();
		$unranked  = array();
		$sums      = self::npcc_sums( $latest_rows );
		$prev_sums = self::npcc_sums( $previous_rows );

		foreach ( $latest_rows as $force_id => $row ) {
			$slug    = $forces[ $force_id ]['slug'];
			$in_sla  = self::int_or_null( $row['completed_in_sla'] ?? null );
			$done    = self::int_or_null( $row['apps_completed_12m'] ?? null );
			$has_pct = ( null !== $in_sla && null !== $done && $done > 0 );

			$prev_pct = null;
			if ( isset( $previous_rows[ $force_id ] ) ) {
				$p_sla  = self::int_or_null( $previous_rows[ $force_id ]['completed_in_sla'] ?? null );
				$p_done = self::int_or_null( $previous_rows[ $force_id ]['apps_completed_12m'] ?? null );
				if ( null !== $p_sla && null !== $p_done && $p_done > 0 ) {
					$prev_pct = $p_sla / $p_done;
				}
			}

			$pct    = $has_pct ? $in_sla / $done : null;
			$figure = array(
				'quarter'      => $latest['label'],
				'pct'          => null === $pct ? null : round( $pct, 6 ),
				'meets_sla'    => $has_pct ? ( $in_sla * self::SLA_DEN >= $done * self::SLA_NUM ) : null,
				'received'     => self::int_or_null( $row['apps_received_12m'] ?? null ),
				'completed'    => $done,
				'in_sla'       => $in_sla,
				'holders'      => self::int_or_null( $row['total_holders'] ?? null ),
				'certificates' => self::int_or_null( $row['total_certificates'] ?? null ),
				'permits'      => self::int_or_null( $row['temporary_permits'] ?? null ),
				'change_pp'    => ( null !== $pct && null !== $prev_pct ) ? round( ( $pct - $prev_pct ) * 100, 2 ) : null,
				'rank'         => null,
				'ranked_of'    => null,
			);
			$snapshot['forces'][ $slug ]['npcc'] = $figure;

			$entry = array_merge(
				$figure,
				array(
					'slug' => $slug,
					'name' => $forces[ $force_id ]['name'],
				)
			);
			if ( $has_pct ) {
				$entries[] = $entry;
			} else {
				$unranked[] = $entry;
			}
		}

		// Leaderboard: higher % within SLA first; ties go to the force that completed more applications.
		usort(
			$entries,
			static function ( $a, $b ) {
				$cross = ( $b['in_sla'] * $a['completed'] ) <=> ( $a['in_sla'] * $b['completed'] ); // b%/a% ordering without floats.
				if ( 0 !== $cross ) {
					return $cross;
				}
				$by_completed = $b['completed'] <=> $a['completed'];
				return 0 !== $by_completed ? $by_completed : strcmp( $a['name'], $b['name'] );
			}
		);

		$count = count( $entries );
		$rank  = 0;
		foreach ( $entries as $i => $entry ) {
			$same = $i > 0
				&& ( $entry['in_sla'] * $entries[ $i - 1 ]['completed'] ) === ( $entries[ $i - 1 ]['in_sla'] * $entry['completed'] )
				&& $entry['completed'] === $entries[ $i - 1 ]['completed'];
			$rank = $same ? $rank : $i + 1;

			$entries[ $i ]['rank']      = $rank;
			$entries[ $i ]['ranked_of'] = $count;
			$snapshot['forces'][ $entry['slug'] ]['npcc']['rank']      = $rank;
			$snapshot['forces'][ $entry['slug'] ]['npcc']['ranked_of'] = $count;
		}
		usort(
			$unranked,
			static function ( $a, $b ) {
				return strcmp( $a['name'], $b['name'] );
			}
		);

		$meeting = 0;
		foreach ( $entries as $entry ) {
			if ( $entry['meets_sla'] ) {
				++$meeting;
			}
		}

		$national_pct = $sums['pct_completed'] > 0 ? $sums['pct_in_sla'] / $sums['pct_completed'] : null;
		$prev_pct     = $prev_sums['pct_completed'] > 0 ? $prev_sums['pct_in_sla'] / $prev_sums['pct_completed'] : null;

		$snapshot['npcc'] = array(
			'national'    => array(
				'quarter'          => $latest['label'],
				'data_date'        => $latest['data_date'] ?? null,
				'forces_in_report' => count( $latest_rows ),
				'forces_ranked'    => $count,
				'forces_meeting'   => $meeting,
				'holders'          => $sums['holders'],
				'certificates'     => $sums['certificates'],
				'received'         => $sums['received'],
				'completed'        => $sums['completed'],
				'in_sla'           => $sums['in_sla'],
				'pct'              => null === $national_pct ? null : round( $national_pct, 6 ),
				'change_pp'        => ( null !== $national_pct && null !== $prev_pct ) ? round( ( $national_pct - $prev_pct ) * 100, 2 ) : null,
				'sla_target'       => self::SLA_NUM / self::SLA_DEN,
			),
			'leaderboard' => $entries,
			'unranked'    => $unranked,
		);
	}

	/**
	 * Sums NPCC columns, skipping NULLs (a missing figure is not 0). The % is built only from forces that
	 * have both "completed" and "completed in SLA", so a half-filled row cannot skew it.
	 *
	 * @param array $rows Rows by force id.
	 * @return array
	 */
	private static function npcc_sums( array $rows ) {
		$sums = array(
			'holders'       => null,
			'certificates'  => null,
			'received'      => null,
			'completed'     => null,
			'in_sla'        => null,
			'pct_completed' => 0,
			'pct_in_sla'    => 0,
		);
		$map = array(
			'holders'      => 'total_holders',
			'certificates' => 'total_certificates',
			'received'     => 'apps_received_12m',
			'completed'    => 'apps_completed_12m',
			'in_sla'       => 'completed_in_sla',
		);
		foreach ( $rows as $row ) {
			foreach ( $map as $key => $column ) {
				$value = self::int_or_null( $row[ $column ] ?? null );
				if ( null !== $value ) {
					$sums[ $key ] = ( $sums[ $key ] ?? 0 ) + $value;
				}
			}
			$done   = self::int_or_null( $row['apps_completed_12m'] ?? null );
			$in_sla = self::int_or_null( $row['completed_in_sla'] ?? null );
			if ( null !== $done && null !== $in_sla ) {
				$sums['pct_completed'] += $done;
				$sums['pct_in_sla']    += $in_sla;
			}
		}
		return $sums;
	}

	/* -------------------------------------------------------------------- Helpers */

	/**
	 * Standard competition ranking over entries already sorted best-first: 1, 2, 2, 4.
	 *
	 * @param array    $entries Sorted entries.
	 * @param string[] $keys    Fields that decide a tie.
	 * @return array Entries with a `rank` field.
	 */
	private static function assign_ranks( array $entries, array $keys ) {
		$rank = 0;
		foreach ( $entries as $i => $entry ) {
			$same = false;
			if ( $i > 0 ) {
				$same = true;
				foreach ( $keys as $key ) {
					if ( $entry[ $key ] !== $entries[ $i - 1 ][ $key ] ) {
						$same = false;
					}
				}
			}
			$rank                  = $same ? $rank : $i + 1;
			$entries[ $i ]['rank'] = $rank;
		}
		return $entries;
	}

	/**
	 * Rows sorted oldest first by their label (YYYY-MM and "2025/26 Q4" both sort correctly as text).
	 *
	 * @param array $items Items with a label.
	 * @return array
	 */
	private static function sorted_by_label( array $items ) {
		$items = array_values( $items );
		usort(
			$items,
			static function ( $a, $b ) {
				return strcmp( $a['label'], $b['label'] );
			}
		);
		return $items;
	}

	/**
	 * Whole number or null (never 0 for a missing value).
	 *
	 * @param mixed $value Raw.
	 * @return int|null
	 */
	private static function int_or_null( $value ) {
		return ( null === $value || '' === $value ) ? null : (int) $value;
	}

	/**
	 * Splits the stored comma-separated alias list.
	 *
	 * @param string|null $aliases Stored aliases.
	 * @return string[]
	 */
	private static function split_aliases( $aliases ) {
		if ( null === $aliases || '' === trim( (string) $aliases ) ) {
			return array();
		}
		return array_values( array_filter( array_map( 'trim', explode( ',', (string) $aliases ) ), 'strlen' ) );
	}
}
