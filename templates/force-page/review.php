<?php
/**
 * Force page: sources, disclaimer and review dates.
 *
 * @var string   $disclaimer  Disclaimer (HTML, sanitised on save).
 * @var string[] $sources     Source lines.
 * @var string   $reviewed    Date or ''.
 * @var string   $next_review Date or ''.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="gco-pd-research gco-pd-freview">
	<?php echo GCO_PD_Icons::get( 'info', 'gco-pd-research__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<div class="gco-pd-research__text">
		<h2 class="gco-pd-card__title">Disclaimer and Sources</h2>
		<?php echo wp_kses_post( $disclaimer ); ?>
		<?php if ( $sources ) : ?>
			<p><strong>Sources:</strong> <?php echo esc_html( implode( '; ', $sources ) ); ?>.</p>
		<?php endif; ?>
	</div>
	<?php if ( '' !== $reviewed || '' !== $next_review ) : ?>
		<div class="gco-pd-research__dates">
			<?php echo GCO_PD_Icons::get( 'calendar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<dl>
				<?php if ( '' !== $reviewed ) : ?>
					<div><dt>Page reviewed:</dt> <dd><?php echo esc_html( $reviewed ); ?></dd></div>
				<?php endif; ?>
				<?php if ( '' !== $next_review ) : ?>
					<div><dt>Next review:</dt> <dd><?php echo esc_html( $next_review ); ?></dd></div>
				<?php endif; ?>
			</dl>
		</div>
	<?php endif; ?>
</section>
