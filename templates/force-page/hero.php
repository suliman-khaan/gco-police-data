<?php
/**
 * Force page hero: breadcrumb, title, intro, background photo and optional crest.
 *
 * @var string  $title   Page title.
 * @var string  $tagline Tagline (shown when there is no intro).
 * @var string  $intro   Intro.
 * @var string  $image   Background image URL or ''.
 * @var string  $crest   Crest <img> markup or ''.
 * @var array[] $crumbs  [label, url] pairs; the last one is the current page.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="gco-pd gco-pd-hero gco-pd-fhero<?php echo $image ? ' gco-pd-hero--image' : ''; ?>"<?php echo $image ? ' style="--gco-hero-image: url(' . esc_url( $image ) . ')"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="gco-pd-hero__inner gco-pd-fhero__inner">
		<div class="gco-pd-fhero__text">
			<nav class="gco-pd-crumbs" aria-label="Breadcrumb">
				<ol>
					<?php foreach ( $crumbs as $i => $crumb ) : ?>
						<li>
							<?php if ( '' !== $crumb[1] ) : ?>
								<a href="<?php echo esc_url( $crumb[1] ); ?>"><?php echo esc_html( $crumb[0] ); ?></a>
							<?php else : ?>
								<span aria-current="page"><?php echo esc_html( $crumb[0] ); ?></span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ol>
			</nav>
			<h1 class="gco-pd-hero__title"><?php echo esc_html( $title ); ?></h1>
			<?php if ( '' !== $intro || '' !== $tagline ) : ?>
				<p class="gco-pd-hero__intro"><?php echo esc_html( '' !== $intro ? $intro : $tagline ); ?></p>
			<?php endif; ?>
		</div>
		<?php if ( $crest ) : ?>
			<div class="gco-pd-fhero__crestwrap"><?php echo $crest; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image(). ?></div>
		<?php endif; ?>
	</div>
</section>
