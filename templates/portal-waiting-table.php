<?php
/**
 * Tabbed waiting-times table. Without JavaScript every tab panel is shown one after another.
 *
 * @var string $id    Unique id prefix.
 * @var array  $tabs  key => label, columns (metric => label), rows, avgs.
 * @var string $round Latest round, e.g. "June 2026".
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;

$gco_pd_first = true;
?>
<div class="gco-pd gco-pd-waits" data-gco-tabs>
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
		<div class="gco-pd-panel" role="tabpanel" id="<?php echo esc_attr( $id . '-' . $key ); ?>" aria-labelledby="<?php echo esc_attr( $id . '-tab-' . $key ); ?>"<?php echo $gco_pd_first ? '' : ' data-inactive'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<h3 class="gco-pd-panel__title"><?php echo esc_html( $tab['label'] ); ?> waiting times</h3>
			<div class="gco-pd-scroll">
				<table class="gco-pd-table">
					<caption>Average time to process applications, latest figures from each force<?php echo $round ? esc_html( ' (latest round ' . $round . ')' ) : ''; ?></caption>
					<thead>
						<tr>
							<th scope="col">Police force</th>
							<?php foreach ( $tab['columns'] as $label ) : ?>
								<th scope="col"><?php echo esc_html( $label ); ?></th>
							<?php endforeach; ?>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $tab['rows'] as $row ) : ?>
						<tr>
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
							<?php foreach ( $row['cells'] as $figure ) : ?>
								<td><?php echo GCO_PD_Shortcodes::figure_html( $figure ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
					</tbody>
					<?php if ( array_filter( $tab['avgs'] ) ) : ?>
					<tfoot>
						<tr>
							<th scope="row">National average</th>
							<?php foreach ( $tab['avgs'] as $avg ) : ?>
								<td><?php echo $avg ? '<strong>' . esc_html( $avg['text'] ) . '</strong> <span class="gco-pd-note">(' . esc_html( (string) $avg['n'] ) . ' forces)</span>' : '<span class="gco-pd-na">Not available</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							<?php endforeach; ?>
						</tr>
					</tfoot>
					<?php endif; ?>
				</table>
			</div>
		</div>
		<?php $gco_pd_first = false; ?>
	<?php endforeach; ?>
	<p class="gco-pd-note">Forces that did not provide a figure are shown as "Not provided" and left out of the averages.</p>
</div>
