<?php
/**
 * "FOI at a glance": the force's latest waiting times and national figures as a row of figure cards under the
 * hero, like the stat cards on the main police page.
 *
 * @var string      $name     Force name.
 * @var array[]     $rows     label, figure (from GCO_PD_Shortcodes::figure()).
 * @var array|null  $change   days, pct, direction: shotgun grant, latest vs previous FOI.
 * @var array[]     $stats    [value, label, icon].
 * @var string|null $ref      FOI reference.
 * @var string      $date     Response date (or round).
 * @var bool        $stale    The force missed the latest round.
 * @var string      $round    Round shown.
 * @var string      $btn_text Shop button text.
 * @var string      $btn_url  Shop button URL.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;

$gco_pd_when = array_filter( array( (string) $ref, $date ) );
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- icons are fixed markup.
?>
<section class="gco-pd-card gco-pd-fcard gco-pd-fglance" id="gco-glance" aria-labelledby="gco-glance-title">
	<header class="gco-pd-card__head">
		<?php echo GCO_PD_Icons::get( 'chart', 'gco-pd-card__icon' ); ?>
		<div class="gco-pd-card__titles">
			<h2 class="gco-pd-card__title" id="gco-glance-title"><?php echo $rows ? 'FOI at a glance' : 'National figures at a glance'; ?></h2>
			<p class="gco-pd-card__sub">
				<?php
				if ( $rows ) {
					echo esc_html(
						$stale
							? sprintf( 'Latest processing times from %1$s: its %2$s response, the most recent it has sent.', $name, $round )
							: sprintf( 'Latest processing times reported by %1$s in its Freedom of Information response%2$s.', $name, $gco_pd_when ? ' (' . implode( ', ', $gco_pd_when ) . ')' : '' )
					);
				} else {
					echo esc_html( sprintf( 'How %s compares nationally.', $name ) );
				}
				?>
			</p>
		</div>
		<?php if ( $rows ) : ?>
			<a class="gco-pd-card__link" href="#gco-waits">See full waiting times <?php echo GCO_PD_Icons::get( 'arrow-down' ); ?></a>
		<?php endif; ?>
	</header>

	<div class="gco-pd-stats gco-pd-stats--plain gco-pd-stats--glance">
		<?php foreach ( $rows as $row ) : ?>
			<div class="gco-pd-stat">
				<span class="gco-pd-stat__icon"><?php echo GCO_PD_Icons::get( 'clock' ); ?></span>
				<div class="gco-pd-stat__text">
					<?php if ( null === $row['figure']['text'] ) : ?>
						<span class="gco-pd-stat__value gco-pd-na">Not provided</span>
					<?php else : ?>
						<strong class="gco-pd-stat__value"><?php echo esc_html( $row['figure']['text'] ); ?></strong>
					<?php endif; ?>
					<span class="gco-pd-stat__label">
						<?php echo esc_html( $row['label'] ); ?>
						<?php if ( null !== $row['figure']['text'] && 'Estimate' === $row['figure']['basis'] ) : ?>
							<span class="gco-pd-badge gco-pd-badge--estimate">Estimate</span>
						<?php endif; ?>
					</span>
				</div>
			</div>
		<?php endforeach; ?>
		<?php foreach ( $stats as $stat ) : ?>
			<div class="gco-pd-stat gco-pd-stat--national">
				<span class="gco-pd-stat__icon"><?php echo GCO_PD_Icons::get( $stat[2] ?? 'trophy' ); ?></span>
				<div class="gco-pd-stat__text">
					<strong class="gco-pd-stat__value"><?php echo esc_html( $stat[0] ); ?></strong>
					<span class="gco-pd-stat__label"><?php echo esc_html( $stat[1] ); ?></span>
				</div>
			</div>
		<?php endforeach; ?>
	</div>

	<?php $gco_pd_has_change = $change && 0 !== $change['days']; ?>
	<?php if ( $gco_pd_has_change || ( '' !== $btn_text && '' !== $btn_url ) ) : ?>
		<div class="gco-pd-fglance__foot">
			<?php if ( $gco_pd_has_change ) : ?>
				<p class="gco-pd-fglance__change gco-pd-fglance__change--<?php echo esc_attr( (string) $change['direction'] ); ?>">
					<?php echo GCO_PD_Icons::get( $change['days'] > 0 ? 'arrow-up' : 'arrow-down' ); ?>
					<span>Shotgun grants <?php echo esc_html( abs( $change['days'] ) . ' ' . ( 1 === abs( $change['days'] ) ? 'day' : 'days' ) . ( $change['days'] > 0 ? ' slower' : ' faster' ) ); ?> than the previous FOI response.</span>
				</p>
			<?php else : ?>
				<span></span>
			<?php endif; ?>
			<?php if ( '' !== $btn_text && '' !== $btn_url ) : ?>
				<a class="gco-pd-button" href="<?php echo esc_url( $btn_url ); ?>"><?php echo esc_html( $btn_text ); ?><?php echo GCO_PD_Icons::get( 'arrow-right' ); ?></a>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</section>
<?php
// phpcs:enable
