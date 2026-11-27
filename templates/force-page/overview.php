<?php
/**
 * Force page: national firearms licensing overview (NPCC).
 *
 * @var string  $subtitle Subtitle.
 * @var array[] $cards    [icon, value|null, label].
 * @var string  $pct      % completed within 4 months.
 * @var string  $source   Source line.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- icons are fixed markup.
?>
<section class="gco-pd-card gco-pd-fcard gco-pd-anchor" id="gco-overview">
	<header class="gco-pd-card__head">
		<?php echo GCO_PD_Icons::get( 'trophy', 'gco-pd-card__icon' ); ?>
		<div class="gco-pd-card__titles">
			<h2 class="gco-pd-card__title">National Firearms Licensing Overview</h2>
			<p class="gco-pd-card__sub"><?php echo esc_html( $subtitle ); ?></p>
		</div>
	</header>
	<div class="gco-pd-stats gco-pd-stats--plain">
		<?php foreach ( $cards as $card ) : ?>
			<div class="gco-pd-stat">
				<span class="gco-pd-stat__icon"><?php echo GCO_PD_Icons::get( $card[0] ); ?></span>
				<div class="gco-pd-stat__text">
					<?php if ( null !== $card[1] ) : ?>
						<strong class="gco-pd-stat__value"><?php echo esc_html( $card[1] ); ?></strong>
					<?php else : ?>
						<span class="gco-pd-stat__value gco-pd-na">Not ranked</span>
					<?php endif; ?>
					<span class="gco-pd-stat__label"><?php echo esc_html( $card[2] ); ?></span>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
	<p class="gco-pd-note gco-pd-fsource"><?php echo esc_html( $source . ( $pct ? ' This force completed ' . $pct . ' within 4 months.' : '' ) ); ?></p>
</section>
<?php
// phpcs:enable
