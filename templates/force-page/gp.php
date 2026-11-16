<?php
/**
 * Force page: GP medical assessments card.
 *
 * @var string   $text      Text (HTML, sanitised on save).
 * @var string[] $points    Checklist.
 * @var array[]  $providers name, phone, website.
 * @var string   $note      Note.
 * @var string   $url       "More about medical requirements" link.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- icons are fixed markup; text is wp_kses()'d on save.
?>
<section class="gco-pd-card gco-pd-fcard gco-pd-anchor" id="gco-gp">
	<header class="gco-pd-card__head">
		<?php echo GCO_PD_Icons::get( 'medical', 'gco-pd-card__icon' ); ?>
		<div class="gco-pd-card__titles"><h2 class="gco-pd-card__title">GP Medical Assessments</h2></div>
	</header>
	<div class="gco-pd-prose"><?php echo wp_kses_post( $text ); ?></div>
	<?php if ( $points ) : ?>
		<ul class="gco-pd-ticks">
			<?php foreach ( $points as $point ) : ?>
				<li><?php echo GCO_PD_Icons::get( 'check-solid' ); ?><span><?php echo esc_html( $point ); ?></span></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
	<?php if ( $providers ) : ?>
		<h3 class="gco-pd-fsub">Medical providers in this area</h3>
		<ul class="gco-pd-providers">
			<?php foreach ( $providers as $provider ) : ?>
				<li>
					<strong><?php echo esc_html( $provider['name'] ); ?></strong>
					<?php if ( '' !== $provider['phone'] ) : ?>
						<span>Tel: <?php echo esc_html( $provider['phone'] ); ?></span>
					<?php endif; ?>
					<?php if ( '' !== $provider['website'] ) : ?>
						<a href="<?php echo esc_url( $provider['website'] ); ?>" target="_blank" rel="noopener nofollow"><?php echo esc_html( preg_replace( '#^https?://(www\.)?#', '', untrailingslashit( $provider['website'] ) ) ); ?></a>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
	<?php if ( '' !== $note ) : ?>
		<p class="gco-pd-fnote"><?php echo nl2br( esc_html( $note ) ); ?></p>
	<?php endif; ?>
	<?php if ( '' !== $url ) : ?>
		<p class="gco-pd-fbtnrow"><a class="gco-pd-outline" href="<?php echo esc_url( $url ); ?>">More about medical requirements<?php echo GCO_PD_Icons::get( 'arrow-right' ); ?></a></p>
	<?php endif; ?>
</section>
<?php
// phpcs:enable
