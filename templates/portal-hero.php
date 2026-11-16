<?php
/**
 * Portal hero: title, intro and the force search over a background photo.
 *
 * @var string $title  Title.
 * @var string $intro  Intro text (plain).
 * @var string $image  Background image URL, or ''.
 * @var string $search Search block markup.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="gco-pd gco-pd-hero<?php echo $image ? ' gco-pd-hero--image' : ''; ?>"<?php echo $image ? ' style="--gco-hero-image: url(' . esc_url( $image ) . ')"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="gco-pd-hero__inner">
		<h1 class="gco-pd-hero__title"><?php echo esc_html( $title ); ?></h1>
		<?php if ( '' !== $intro ) : ?>
			<p class="gco-pd-hero__intro"><?php echo esc_html( $intro ); ?></p>
		<?php endif; ?>
		<?php echo $search; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered template. ?>
	</div>
</section>
