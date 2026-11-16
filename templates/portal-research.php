<?php
/**
 * "How we research" panel with last updated / next review dates.
 *
 * @var string $title       Title.
 * @var string $text        Body (sanitised HTML).
 * @var string $updated     Last published date, e.g. "12 January 2027", or ''.
 * @var string $next_review Next review text, or ''.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="gco-pd gco-pd-research">
	<?php echo GCO_PD_Icons::get( 'info', 'gco-pd-research__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<div class="gco-pd-research__text">
		<h2 class="gco-pd-card__title"><?php echo esc_html( $title ); ?></h2>
		<?php echo $text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses_post(). ?>
	</div>
	<?php if ( '' !== $updated || '' !== $next_review ) : ?>
		<div class="gco-pd-research__dates">
			<?php echo GCO_PD_Icons::get( 'calendar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<dl>
				<?php if ( '' !== $updated ) : ?>
					<div><dt>Last updated:</dt> <dd><?php echo esc_html( $updated ); ?></dd></div>
				<?php endif; ?>
				<?php if ( '' !== $next_review ) : ?>
					<div><dt>Next review:</dt> <dd><?php echo esc_html( $next_review ); ?></dd></div>
				<?php endif; ?>
			</dl>
		</div>
	<?php endif; ?>
</section>
