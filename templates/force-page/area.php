<?php
/**
 * Force page: "Firearms Licensing in {area}" card with general applicant information.
 *
 * @var string   $title          Title.
 * @var string   $text           Description (HTML, sanitised on save).
 * @var string   $holders        Holders sentence (plain).
 * @var string   $applicant_text General applicant information (HTML).
 * @var string[] $points         Applicant checklist.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="gco-pd-card gco-pd-fcard gco-pd-anchor" id="gco-area">
	<h2 class="gco-pd-card__title gco-pd-fcard__title"><?php echo esc_html( $title ); ?></h2>
	<div class="gco-pd-prose">
		<?php echo wp_kses_post( $text ); ?>
		<?php if ( '' !== $holders ) : ?>
			<p><?php echo esc_html( $holders ); ?></p>
		<?php endif; ?>
	</div>
	<?php if ( '' !== trim( wp_strip_all_tags( $applicant_text ) ) || $points ) : ?>
		<h3 class="gco-pd-fsub">General applicant information</h3>
		<div class="gco-pd-prose"><?php echo wp_kses_post( $applicant_text ); ?></div>
		<?php if ( $points ) : ?>
			<ul class="gco-pd-ticks gco-pd-ticks--cols">
				<?php foreach ( $points as $point ) : ?>
					<li><?php echo GCO_PD_Icons::get( 'check-solid' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php echo esc_html( $point ); ?></span></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	<?php endif; ?>
</section>
