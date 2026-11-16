<?php
/**
 * Force page: current waiting times, shotgun grant bars and change box, full history.
 *
 * @var bool        $empty   No FOI response yet.
 * @var string      $name    Force name.
 * @var string|null $ref     FOI reference.
 * @var string      $date    Response date, e.g. "12 June 2026".
 * @var string      $round   Round shown, e.g. "June 2026".
 * @var bool        $stale   Force missed the latest round.
 * @var string      $latest  Latest round overall.
 * @var array[]     $rows    label, figure, change.
 * @var string      $chart   Bar chart SVG or ''.
 * @var array|null  $change  days, pct, direction.
 * @var string      $pdf     PDF URL or ''.
 * @var array[]     $history round, cells (metric => figure).
 * @var array       $columns metric => label.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- icons and GCO_PD_Shortcodes helpers return escaped markup.
?>
<section class="gco-pd-card gco-pd-fcard gco-pd-anchor" id="gco-waits">
	<header class="gco-pd-card__head">
		<?php echo GCO_PD_Icons::get( 'clock', 'gco-pd-card__icon' ); ?>
		<div class="gco-pd-card__titles">
			<h2 class="gco-pd-card__title">Current Waiting Times</h2>
			<?php if ( ! $empty ) : ?>
				<p class="gco-pd-card__sub">
					<?php
					$gco_pd_meta = array_filter( array( $ref, $date ? $date : $round ) );
					echo esc_html( sprintf( 'Latest processing times from a Freedom of Information response from %1$s%2$s.', $name, $gco_pd_meta ? ' (' . implode( ', ', $gco_pd_meta ) . ')' : '' ) );
					?>
				</p>
			<?php endif; ?>
		</div>
	</header>

	<?php if ( ! empty( $shared ) ) : ?>
		<p class="gco-pd-fnote gco-pd-fshared"><?php echo GCO_PD_Icons::get( 'users' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( $shared['text'] ); ?></p>
	<?php endif; ?>

	<?php if ( $empty ) : ?>
		<p class="gco-pd-empty"><?php echo esc_html( sprintf( 'We have not yet received a Freedom of Information response from %s. This section will update when we do.', $name ) ); ?></p>
	<?php else : ?>
		<?php if ( $stale && $latest ) : ?>
			<p class="gco-pd-fnote"><?php echo esc_html( sprintf( '%1$s has not yet answered our %2$s request, so these are its %3$s figures.', $name, $latest, $round ) ); ?></p>
		<?php endif; ?>
		<div class="gco-pd-scroll">
			<table class="gco-pd-table gco-pd-table--fwaits">
				<caption class="gco-pd-sr"><?php echo esc_html( $name . ' waiting times, ' . $round ); ?></caption>
				<thead><tr><th scope="col">Application type</th><th scope="col" class="gco-pd-num">Processing time</th></tr></thead>
				<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $row['label'] ); ?></th>
						<td class="gco-pd-num">
							<?php if ( null === $row['figure']['text'] ) : ?>
								<span class="gco-pd-na">Not provided</span>
							<?php else : ?>
								<strong><?php echo esc_html( $row['figure']['text'] ); ?></strong>
								<?php if ( 'Estimate' === $row['figure']['basis'] ) : ?>
									<span class="gco-pd-badge gco-pd-badge--estimate">Estimate</span>
								<?php endif; ?>
								<?php if ( $row['change'] ) : ?>
									<span class="gco-pd-fchange"><?php echo GCO_PD_Shortcodes::change_html( $row['change'] ); ?></span>
								<?php endif; ?>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<?php if ( $chart ) : ?>
			<div class="gco-pd-fchart">
				<div class="gco-pd-fchart__plot">
					<h3 class="gco-pd-fsub"><?php echo GCO_PD_Icons::get( 'chart' ); ?> Reported Shotgun Grant Times</h3>
					<p class="gco-pd-note">Change in reported processing time based on FOI responses.</p>
					<?php echo $chart; ?>
				</div>
				<?php if ( $change ) : ?>
					<div class="gco-pd-fchange-box gco-pd-fchange-box--<?php echo esc_attr( (string) $change['direction'] ); ?>">
						<?php echo GCO_PD_Icons::get( $change['days'] > 0 ? 'arrow-up' : 'arrow-down' ); ?>
						<strong>
							<?php
							$gco_pd_sign = $change['days'] > 0 ? '+' : ( $change['days'] < 0 ? '−' : '' );
							echo esc_html( $gco_pd_sign . abs( $change['days'] ) . ' ' . ( 1 === abs( $change['days'] ) ? 'day' : 'days' ) );
							?>
						</strong>
						<?php if ( null !== $change['pct'] ) : ?>
							<span class="gco-pd-fchange-box__pct">(<?php echo esc_html( ( $change['pct'] > 0 ? '+' : ( $change['pct'] < 0 ? '−' : '' ) ) . abs( $change['pct'] ) . '%' ); ?>)</span>
						<?php endif; ?>
						<p>Change in reported shotgun grant time (previous period vs latest).</p>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( $history ) : ?>
			<details class="gco-pd-fhistory">
				<summary>Full waiting-time history<?php echo GCO_PD_Icons::get( 'chevron', 'gco-pd-faq__chevron' ); ?></summary>
				<div class="gco-pd-scroll">
					<table class="gco-pd-table">
						<caption class="gco-pd-sr"><?php echo esc_html( $name . ' waiting times in every published FOI round' ); ?></caption>
						<thead><tr><th scope="col">FOI round</th>
							<?php foreach ( $columns as $label ) : ?>
								<th scope="col" class="gco-pd-center"><?php echo esc_html( $label ); ?></th>
							<?php endforeach; ?>
						</tr></thead>
						<tbody>
						<?php foreach ( $history as $point ) : ?>
							<tr><th scope="row"><?php echo esc_html( $point['round'] ); ?></th>
								<?php foreach ( $point['cells'] as $figure ) : ?>
									<td class="gco-pd-center"><?php echo GCO_PD_Shortcodes::figure_html( $figure ); ?></td>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</details>
		<?php endif; ?>

		<?php if ( $pdf ) : ?>
			<p class="gco-pd-fnote"><a href="<?php echo esc_url( $pdf ); ?>">Read the FOI response (PDF)</a></p>
		<?php endif; ?>
	<?php endif; ?>
</section>
<?php
// phpcs:enable
