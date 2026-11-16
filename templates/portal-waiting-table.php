<?php
/**
 * Tabbed waiting-times table. Without JavaScript every tab panel shows, with every row.
 *
 * @var string $id         Unique id prefix (also the expand target).
 * @var array  $tabs       key => label, columns (metric => label), rows (rank, name, url, cells, stale, round, extra), avgs.
 * @var string $round      Latest round, e.g. "June 2026".
 * @var bool   $expandable Whether rows are cut to the limit.
 * @var int    $limit      Rows shown before expanding.
 * @var int    $total      All forces.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;

$gco_pd_first = true;
$gco_pd_help  = 'Actual average: the force gave an exact average from its records. Estimate: the force could only give an approximate figure. Not provided: the force did not answer that question.';
?>
<div class="gco-pd gco-pd-waits" id="<?php echo esc_attr( $id ); ?>" data-gco-tabs<?php echo $expandable ? ' data-gco-expand' : ''; ?>>
	<div class="gco-pd-tabs" role="tablist" aria-label="Application type">
		<?php foreach ( $tabs as $key => $tab ) : ?>
			<button type="button" class="gco-pd-tab" role="tab" id="<?php echo esc_attr( $id . '-tab-' . $key ); ?>"
				aria-controls="<?php echo esc_attr( $id . '-' . $key ); ?>" aria-selected="<?php echo $gco_pd_first ? 'true' : 'false'; ?>"
				tabindex="<?php echo $gco_pd_first ? '0' : '-1'; ?>"><?php echo esc_html( $tab['label'] ); ?></button>
			<?php $gco_pd_first = false; ?>
		<?php endforeach; ?>
	</div>

	<?php $gco_pd_first = true; ?>
	<?php foreach ( $tabs as $key => $tab ) : ?>
		<?php $gco_pd_single = 1 === count( $tab['columns'] ); ?>
		<div class="gco-pd-panel" role="tabpanel" id="<?php echo esc_attr( $id . '-' . $key ); ?>" aria-labelledby="<?php echo esc_attr( $id . '-tab-' . $key ); ?>"<?php echo $gco_pd_first ? '' : ' data-inactive'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<h3 class="gco-pd-panel__title"><?php echo esc_html( $tab['label'] ); ?></h3>
			<div class="gco-pd-scroll">
				<table class="gco-pd-table gco-pd-table--waits">
					<caption class="gco-pd-sr"><?php echo esc_html( $tab['label'] . ': reported waiting times, latest figure from each force' . ( $round ? ' (latest round ' . $round . ')' : '' ) ); ?></caption>
					<thead>
						<tr>
							<th scope="col" class="gco-pd-rank">Rank</th>
							<th scope="col">Police Force</th>
							<?php if ( $gco_pd_single ) : ?>
								<th scope="col" class="gco-pd-center">Reported Waiting Time</th>
								<th scope="col" class="gco-pd-center">Figure Type <span class="gco-pd-info" tabindex="0" role="note" title="<?php echo esc_attr( $gco_pd_help ); ?>" aria-label="<?php echo esc_attr( $gco_pd_help ); ?>" data-tip="<?php echo esc_attr( $gco_pd_help ); ?>"><?php echo GCO_PD_Icons::get( 'info' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span></th>
							<?php else : ?>
								<?php foreach ( $tab['columns'] as $label ) : ?>
									<th scope="col" class="gco-pd-center"><?php echo esc_html( $label ); ?></th>
								<?php endforeach; ?>
							<?php endif; ?>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $tab['rows'] as $row ) : ?>
						<tr<?php echo $row['extra'] ? ' data-gco-extra' : ''; ?>>
							<td class="gco-pd-rank"><?php echo null !== $row['rank'] ? esc_html( (string) $row['rank'] ) : '<span class="gco-pd-na">&mdash;</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							<th scope="row">
								<?php if ( $row['url'] ) : ?>
									<a href="<?php echo esc_url( $row['url'] ); ?>"><?php echo esc_html( $row['name'] ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $row['name'] ); ?>
								<?php endif; ?>
								<?php if ( $row['stale'] && $row['round'] ) : ?>
									<span class="gco-pd-note">(<?php echo esc_html( GCO_PD_Format::round_label( $row['round'] ) ); ?>)</span>
								<?php endif; ?>
							</th>
							<?php if ( $gco_pd_single ) : ?>
								<?php $gco_pd_fig = reset( $row['cells'] ); ?>
								<td class="gco-pd-center">
									<?php echo null === $gco_pd_fig['text'] ? '<span class="gco-pd-na">Not provided</span>' : '<strong>' . esc_html( $gco_pd_fig['text'] ) . '</strong>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</td>
								<td class="gco-pd-center">
									<?php if ( '' !== $gco_pd_fig['basis'] ) : ?>
										<span class="gco-pd-badge gco-pd-badge--<?php echo esc_attr( $gco_pd_fig['status'] ); ?>"><?php echo esc_html( $gco_pd_fig['basis'] ); ?></span>
									<?php else : ?>
										<span class="gco-pd-badge gco-pd-badge--not_provided">Not provided</span>
									<?php endif; ?>
								</td>
							<?php else : ?>
								<?php foreach ( $row['cells'] as $figure ) : ?>
									<td class="gco-pd-center"><?php echo GCO_PD_Shortcodes::figure_html( $figure ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
								<?php endforeach; ?>
							<?php endif; ?>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php if ( $tab['avgs'] ) : ?>
				<p class="gco-pd-foot">
					<?php
					$gco_pd_parts = array();
					foreach ( $tab['avgs'] as $avg ) {
						$gco_pd_parts[] = sprintf( '%1$s %2$s (%3$s)', strtolower( $avg['label'] ), $avg['text'], GCO_PD_Format::forces_phrase( $avg['n_forces'] ?? $avg['n'], $avg['n'] ) );
					}
					echo esc_html( 'National average: ' . implode( '; ', $gco_pd_parts ) . '.' );
					?>
				</p>
			<?php endif; ?>
		</div>
		<?php $gco_pd_first = false; ?>
	<?php endforeach; ?>
	<?php if ( $expandable ) : ?>
		<p class="gco-pd-foot gco-pd-foot--center">
			<button type="button" class="gco-pd-linkbtn" data-gco-expand-toggle="<?php echo esc_attr( $id ); ?>" aria-controls="<?php echo esc_attr( $id ); ?>" aria-expanded="false" data-label-open="Show top <?php echo esc_attr( (string) $limit ); ?> only"><?php echo esc_html( sprintf( 'Show all %d forces', $total ) ); ?><?php echo GCO_PD_Icons::get( 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
		</p>
	<?php endif; ?>
</div>
