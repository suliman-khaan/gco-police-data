<?php
/**
 * Force page: previous and current applicant reports.
 *
 * @var string  $subtitle Subtitle.
 * @var string  $intro    Summary paragraph.
 * @var array[] $quotes   quote, who, source.
 * @var string  $feedback Feedback invitation (limited HTML) or ''.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="gco-pd-card gco-pd-fcard gco-pd-anchor" id="gco-reports">
	<header class="gco-pd-card__head">
		<?php echo GCO_PD_Icons::get( 'comment', 'gco-pd-card__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<div class="gco-pd-card__titles">
			<h2 class="gco-pd-card__title">Previous and Current Applicant Reports</h2>
			<p class="gco-pd-card__sub"><?php echo esc_html( $subtitle ); ?></p>
		</div>
	</header>
	<?php if ( '' !== $intro ) : ?>
		<p class="gco-pd-freports__intro"><?php echo nl2br( esc_html( $intro ) ); ?></p>
	<?php endif; ?>
	<?php if ( $quotes ) : ?>
		<div class="gco-pd-quotes">
			<?php foreach ( $quotes as $quote ) : ?>
				<figure class="gco-pd-quote">
					<span class="gco-pd-quote__mark" aria-hidden="true">&ldquo;</span>
					<blockquote><p>&ldquo;<?php echo esc_html( $quote['quote'] ); ?>&rdquo;</p></blockquote>
					<?php if ( '' !== $quote['who'] || '' !== $quote['source'] ) : ?>
						<figcaption>
							<?php if ( '' !== $quote['who'] ) : ?>
								<span><?php echo esc_html( $quote['who'] ); ?></span>
							<?php endif; ?>
							<?php if ( '' !== $quote['source'] ) : ?>
								<cite><?php echo esc_html( $quote['source'] ); ?></cite>
							<?php endif; ?>
						</figcaption>
					<?php endif; ?>
				</figure>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
	<?php if ( '' !== trim( wp_strip_all_tags( (string) $feedback ) ) ) : ?>
		<p class="gco-pd-fnote gco-pd-ffeedback"><?php echo wp_kses_post( $feedback ); ?></p>
	<?php endif; ?>
</section>
