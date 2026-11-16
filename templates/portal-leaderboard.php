<?php
/**
 * Leaderboard: forces ranked by % of applications completed within 4 months.
 *
 * @var array[] $rows      rank, name, url, received, completed, pct, class, change.
 * @var string  $caption   Source line.
 * @var bool    $show_more Whether to show the "full leaderboard" link.
 * @var string  $more_url  Link target.
 * @var string  $more_text Link text.
 * @var string  $national  National % within SLA.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="gco-pd gco-pd-leaderboard">
	<div class="gco-pd-scroll">
		<table class="gco-pd-table">
			<caption><?php echo esc_html( $caption ); ?></caption>
			<thead>
				<tr>
					<th scope="col">Rank</th>
					<th scope="col">Police force</th>
					<th scope="col" class="gco-pd-num">Applications received</th>
					<th scope="col" class="gco-pd-num">Applications completed</th>
					<th scope="col" class="gco-pd-num">Completed within 4 months</th>
					<th scope="col">Since last quarter</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $rows as $row ) : ?>
				<tr>
					<td><?php echo null !== $row['rank'] ? esc_html( (string) $row['rank'] ) : '<span class="gco-pd-na">&mdash;</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
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
					<td><?php echo GCO_PD_Shortcodes::change_html( $row['change'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<p class="gco-pd-note">Green: meets the NPCC aspiration of 80% within 4 months. National figure: <?php echo esc_html( (string) $national ); ?>.</p>
	<?php if ( $show_more ) : ?>
		<p><a class="gco-pd-button" href="<?php echo esc_url( $more_url ); ?>"><?php echo esc_html( $more_text ); ?></a></p>
	<?php endif; ?>
</div>
