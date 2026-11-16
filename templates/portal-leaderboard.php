<?php
/**
 * Leaderboard: forces ranked by % of applications completed within 4 months. Rows past the limit are
 * printed but hidden until the list is expanded (all rows show without JavaScript).
 *
 * @var string  $id          Element id.
 * @var array[] $rows        rank, name, url, received, completed, pct, class, change, extra.
 * @var string  $caption     Source line.
 * @var bool    $expandable  Whether rows are cut to the limit.
 * @var int     $limit       Rows shown before expanding.
 * @var int     $total       All rows.
 * @var bool    $show_change Show the change-since-last-quarter column.
 * @var string  $national    National % within SLA.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="gco-pd gco-pd-leaderboard" id="<?php echo esc_attr( $id ); ?>"<?php echo $expandable ? ' data-gco-expand' : ''; ?>>
	<div class="gco-pd-scroll">
		<table class="gco-pd-table">
			<caption class="gco-pd-sr"><?php echo esc_html( $caption ); ?></caption>
			<thead>
				<tr>
					<th scope="col" class="gco-pd-rank">Rank</th>
					<th scope="col">Police Force</th>
					<th scope="col" class="gco-pd-num">Applications Received</th>
					<th scope="col" class="gco-pd-num">Applications Completed</th>
					<th scope="col" class="gco-pd-num">% Within SLA</th>
					<?php if ( $show_change ) : ?>
						<th scope="col">Since last quarter</th>
					<?php endif; ?>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $rows as $row ) : ?>
				<tr<?php echo $row['extra'] ? ' data-gco-extra' : ''; ?>>
					<td class="gco-pd-rank"><?php echo null !== $row['rank'] ? esc_html( (string) $row['rank'] ) : '<span class="gco-pd-na">&mdash;</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
					<th scope="row">
						<?php if ( $row['url'] ) : ?>
							<a href="<?php echo esc_url( $row['url'] ); ?>"><?php echo esc_html( $row['name'] ); ?></a>
						<?php else : ?>
							<?php echo esc_html( $row['name'] ); ?>
						<?php endif; ?>
					</th>
					<td class="gco-pd-num"><?php echo null !== $row['received'] ? esc_html( $row['received'] ) : '<span class="gco-pd-na">Not provided</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
					<td class="gco-pd-num"><?php echo null !== $row['completed'] ? esc_html( $row['completed'] ) : '<span class="gco-pd-na">Not provided</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
					<td class="gco-pd-num">
						<?php if ( null !== $row['pct'] ) : ?>
							<span class="gco-pd-pct gco-pd-pct--<?php echo esc_attr( $row['class'] ); ?>"><?php echo esc_html( $row['pct'] ); ?></span>
						<?php else : ?>
							<span class="gco-pd-na">Not ranked</span>
						<?php endif; ?>
					</td>
					<?php if ( $show_change ) : ?>
						<td><?php echo GCO_PD_Shortcodes::change_html( $row['change'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
					<?php endif; ?>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<p class="gco-pd-foot">
		<?php if ( $expandable ) : ?>
			<span data-gco-collapsed-only><?php echo esc_html( sprintf( 'Showing top %1$d of %2$d police forces.', $limit, $total ) ); ?></span>
			<button type="button" class="gco-pd-linkbtn" data-gco-expand-toggle="<?php echo esc_attr( $id ); ?>" aria-controls="<?php echo esc_attr( $id ); ?>" aria-expanded="false" data-label-open="Show top <?php echo esc_attr( (string) $limit ); ?> only">View the full national leaderboard for all forces<?php echo GCO_PD_Icons::get( 'arrow-right' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
		<?php else : ?>
			<?php echo esc_html( sprintf( 'National figure: %s completed within 4 months (NPCC aspiration 80%%).', $national ) ); ?>
		<?php endif; ?>
	</p>
</div>
