<?php
/**
 * Guide cards: image, title, "Read the guide".
 *
 * @var array[] $cards     url, title, image (markup or '').
 * @var string  $read_text Link text.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="gco-pd gco-pd-guides">
	<?php foreach ( $cards as $card ) : ?>
		<article class="gco-pd-guide">
			<a class="gco-pd-guide__media" href="<?php echo esc_url( $card['url'] ); ?>" tabindex="-1" aria-hidden="true">
				<?php if ( $card['image'] ) : ?>
					<?php echo $card['image']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_the_post_thumbnail(). ?>
				<?php else : ?>
					<span class="gco-pd-guide__placeholder"><?php echo GCO_PD_Icons::get( 'book' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<?php endif; ?>
			</a>
			<div class="gco-pd-guide__body">
				<h3 class="gco-pd-guide__title"><a href="<?php echo esc_url( $card['url'] ); ?>"><?php echo esc_html( $card['title'] ); ?></a></h3>
				<a class="gco-pd-guide__more" href="<?php echo esc_url( $card['url'] ); ?>"><?php echo esc_html( $read_text ); ?><span class="gco-pd-sr">: <?php echo esc_html( $card['title'] ); ?></span><?php echo GCO_PD_Icons::get( 'arrow-right' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
			</div>
		</article>
	<?php endforeach; ?>
</div>
