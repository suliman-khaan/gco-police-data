<?php
/**
 * Force page: preparing for your firearms security inspection (shared block).
 *
 * @var string $title    Title.
 * @var string $text     Text (HTML, sanitised on save).
 * @var string $image    <img> markup or ''.
 * @var string $btn_text Button text.
 * @var string $btn_url  Button link.
 * @var string $products WooCommerce products markup or ''.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- icons are fixed markup; image and products come from WordPress/WooCommerce.
?>
<section class="gco-pd-card gco-pd-fcard gco-pd-inspect gco-pd-anchor" id="gco-inspection">
	<div class="gco-pd-inspect__row">
		<?php echo GCO_PD_Icons::get( 'shield', 'gco-pd-card__icon' ); ?>
		<div class="gco-pd-inspect__text">
			<h2 class="gco-pd-card__title"><?php echo esc_html( $title ); ?></h2>
			<div class="gco-pd-prose"><?php echo wp_kses_post( $text ); ?></div>
		</div>
		<?php if ( $image ) : ?>
			<div class="gco-pd-inspect__media"><?php echo $image; ?></div>
		<?php endif; ?>
		<?php if ( '' !== $btn_text && '' !== $btn_url ) : ?>
			<div class="gco-pd-inspect__cta"><a class="gco-pd-button" href="<?php echo esc_url( $btn_url ); ?>"><?php echo esc_html( $btn_text ); ?><?php echo GCO_PD_Icons::get( 'arrow-right' ); ?></a></div>
		<?php endif; ?>
	</div>
	<?php if ( $products ) : ?>
		<div class="gco-pd-inspect__products"><?php echo $products; ?></div>
	<?php endif; ?>
</section>
<?php
// phpcs:enable
