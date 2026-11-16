<?php
/**
 * Trend table and chart: every published round for one force. The table is the accessible version of the chart.
 *
 * @var array   $force   Snapshot force.
 * @var array[] $rows    round, cells (metric => figure).
 * @var array   $columns metric => label.
 * @var string  $chart   Inline SVG (empty when there is only one round).
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="gco-pd gco-pd-trend">
	<?php if ( '' !== $chart ) : ?>
		<div class="gco-pd-trend__chart"><?php echo $chart; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside GCO_PD_SVG_Chart. ?></div>
	<?php else : ?>
		<p class="gco-pd-note">A chart will appear once there are two or more rounds of data.</p>
	<?php endif; ?>
	<div class="gco-pd-scroll">
		<table class="gco-pd-table">
			<caption><?php echo esc_html( $force['name'] . ' waiting times by round' ); ?></caption>
			<thead>
				<tr>
					<th scope="col">Round</th>
					<?php foreach ( $columns as $label ) : ?>
						<th scope="col"><?php echo esc_html( $label ); ?></th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( array_reverse( $rows ) as $row ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html( $row['round'] ); ?></th>
					<?php foreach ( $columns as $metric => $label ) : ?>
						<td><?php echo GCO_PD_Shortcodes::figure_html( $row['cells'][ $metric ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
					<?php endforeach; ?>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
