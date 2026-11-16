<?php
/**
 * Current waiting times for one force, with Actual/Estimate badges and change since the last round.
 *
 * @var array   $force   Snapshot force.
 * @var array[] $rows    label, figure, change.
 * @var array[] $holders label, figure.
 * @var string  $round   Round the figures come from, e.g. "June 2026".
 * @var bool    $stale   True when this force did not answer the latest round.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="gco-pd gco-pd-force-waits">
	<div class="gco-pd-scroll">
		<table class="gco-pd-table">
			<caption><?php echo esc_html( $force['name'] . ' firearms licensing waiting times' ); ?><?php echo $round ? esc_html( ', FOI response ' . $round ) : ''; ?></caption>
			<thead>
				<tr>
					<th scope="col">Application</th>
					<th scope="col">Waiting time</th>
					<th scope="col">Since last round</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $rows as $row ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html( $row['label'] ); ?></th>
					<td><?php echo GCO_PD_Shortcodes::figure_html( $row['figure'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
					<td><?php echo GCO_PD_Shortcodes::change_html( $row['change'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php if ( $stale ) : ?>
		<p class="gco-pd-note"><?php echo esc_html( $force['name'] . ' did not respond in the latest round, so these are its most recent figures.' ); ?></p>
	<?php endif; ?>
	<dl class="gco-pd-holders">
		<?php foreach ( $holders as $holder ) : ?>
			<div>
				<dt><?php echo esc_html( $holder['label'] ); ?></dt>
				<dd><?php echo GCO_PD_Shortcodes::figure_html( $holder['figure'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></dd>
			</div>
		<?php endforeach; ?>
	</dl>
</div>
