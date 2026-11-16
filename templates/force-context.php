<?php
/**
 * National context for one force: its waits against the national average, plus holder and application facts.
 *
 * @var array    $force Snapshot force.
 * @var array[]  $rows  label, force, average, verdict, n.
 * @var string[] $facts Sentences.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="gco-pd gco-pd-context">
	<?php if ( $rows ) : ?>
		<div class="gco-pd-scroll">
			<table class="gco-pd-table">
				<caption><?php echo esc_html( $force['name'] . ' compared with the national average' ); ?></caption>
				<thead>
					<tr>
						<th scope="col">Application</th>
						<th scope="col"><?php echo esc_html( $force['name'] ); ?></th>
						<th scope="col">National average</th>
						<th scope="col">Difference</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $row['label'] ); ?></th>
						<td><?php echo null !== $row['force'] ? '<strong>' . esc_html( $row['force'] ) . '</strong>' : '<span class="gco-pd-na">Not provided</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
						<td><?php echo null !== $row['average'] ? esc_html( $row['average'] ) . ' <span class="gco-pd-note">(' . esc_html( GCO_PD_Format::forces_phrase( $row['n_forces'] ?? $row['n'], $row['n'] ) ) . ')</span>' : '<span class="gco-pd-na">Not available</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
						<td><?php echo '' !== $row['verdict'] ? esc_html( $row['verdict'] ) : '<span class="gco-pd-na">&mdash;</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
	<?php if ( $facts ) : ?>
		<ul class="gco-pd-facts">
			<?php foreach ( $facts as $fact ) : ?>
				<li><?php echo esc_html( $fact ); ?></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>
