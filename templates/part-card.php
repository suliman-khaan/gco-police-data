<?php
/**
 * Card shell used by the portal blocks: icon, title, subtitle, header link, body.
 *
 * @var string $title    Title.
 * @var string $subtitle Subtitle.
 * @var string $icon     Icon name.
 * @var string $mod      Modifier class suffix.
 * @var string $link     Header link markup (already escaped).
 * @var string $body     Body markup (already escaped).
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="gco-pd gco-pd-card gco-pd-card--<?php echo esc_attr( $mod ); ?>">
	<header class="gco-pd-card__head">
		<?php echo GCO_PD_Icons::get( $icon, 'gco-pd-card__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<div class="gco-pd-card__titles">
			<?php if ( '' !== $title ) : ?>
				<h2 class="gco-pd-card__title"><?php echo esc_html( $title ); ?></h2>
			<?php endif; ?>
			<?php if ( '' !== $subtitle ) : ?>
				<p class="gco-pd-card__sub"><?php echo esc_html( $subtitle ); ?></p>
			<?php endif; ?>
		</div>
		<?php echo $link; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built and escaped by GCO_PD_Shortcodes. ?>
	</header>
	<div class="gco-pd-card__body">
		<?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered templates. ?>
	</div>
</section>
