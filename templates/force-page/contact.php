<?php
/**
 * Force page: contact details card.
 *
 * @var string   $unit    Unit name.
 * @var string[] $address Address lines.
 * @var string   $phone   Phone text.
 * @var string   $tel     Dialable number or ''.
 * @var string   $email   Email.
 * @var string   $hours   Office hours.
 * @var string   $url     Force licensing webpage.
 * @var string   $note    Note.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- icons are fixed markup.
?>
<section class="gco-pd-card gco-pd-fcard gco-pd-anchor" id="gco-contact">
	<header class="gco-pd-card__head">
		<?php echo GCO_PD_Icons::get( 'phone', 'gco-pd-card__icon' ); ?>
		<div class="gco-pd-card__titles"><h2 class="gco-pd-card__title">Contact Details &amp; General Information</h2></div>
	</header>
	<?php if ( '' !== $unit || $address ) : ?>
		<address class="gco-pd-contact__address">
			<?php if ( '' !== $unit ) : ?>
				<strong><?php echo esc_html( $unit ); ?></strong><br>
			<?php endif; ?>
			<?php echo implode( '<br>', array_map( 'esc_html', $address ) ); ?>
		</address>
	<?php endif; ?>
	<ul class="gco-pd-contact__list">
		<?php if ( '' !== $phone ) : ?>
			<li><?php echo GCO_PD_Icons::get( 'phone' ); ?>
				<?php if ( '' !== $tel ) : ?>
					<a href="tel:<?php echo esc_attr( $tel ); ?>"><?php echo esc_html( $phone ); ?></a>
				<?php else : ?>
					<span><?php echo esc_html( $phone ); ?></span>
				<?php endif; ?>
			</li>
		<?php endif; ?>
		<?php if ( '' !== $email ) : ?>
			<li><?php echo GCO_PD_Icons::get( 'mail' ); ?><a href="mailto:<?php echo esc_attr( antispambot( $email ) ); ?>"><?php echo esc_html( antispambot( $email ) ); ?></a></li>
		<?php endif; ?>
		<?php if ( '' !== $hours ) : ?>
			<li><?php echo GCO_PD_Icons::get( 'clock' ); ?><span><?php echo esc_html( $hours ); ?></span></li>
		<?php endif; ?>
		<?php if ( '' !== $url ) : ?>
			<li><?php echo GCO_PD_Icons::get( 'external' ); ?><a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener nofollow">Firearms licensing webpage<span class="gco-pd-sr"> (opens in a new tab)</span></a></li>
		<?php endif; ?>
	</ul>
	<?php if ( '' !== $note ) : ?>
		<p class="gco-pd-fnote"><?php echo nl2br( esc_html( $note ) ); ?></p>
	<?php endif; ?>
</section>
<?php
// phpcs:enable
