<?php
/**
 * Force page hero: the same full-width band as the main police page (black and white photo under a green wash),
 * with the breadcrumb, tag, title, tagline and intro, and the force crest on the right when there is one.
 *
 * @var string  $title   Page title.
 * @var string  $tagline Tagline (shown above the intro, or alone when there is no intro).
 * @var string  $intro   Intro.
 * @var string  $image   Photo URL or ''.
 * @var string  $crest   Crest <img> markup or ''.
 * @var array[] $crumbs  [label, url] pairs; the last one is the current page.
 * @var string  $updated "Updated" date or ''.
 * @var string  $portal  Portal page URL or ''.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- crest is wp_get_attachment_image().
?>
<header class="gco-pd gco-pd-hero gco-pd-fhero<?php echo '' !== $image ? ' gco-pd-hero--image' : ''; ?>"<?php echo '' !== $image ? ' style="--gco-hero-image: url(' . esc_url( $image ) . ')"' : ''; ?>>
	<div class="gco-pd-hero__inner gco-pd-fhero__inner">
		<div class="gco-pd-fhero__text">
			<nav class="gco-pd-crumbs" aria-label="Breadcrumb">
				<ol>
					<?php foreach ( $crumbs as $crumb ) : ?>
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
			<div class="gco-pd-fhero__tags">
				<?php if ( '' !== $portal ) : ?>
					<a class="gco-pd-fhero__tag" href="<?php echo esc_url( $portal ); ?>">Police Licensing</a>
				<?php else : ?>
					<span class="gco-pd-fhero__tag">Police Licensing</span>
				<?php endif; ?>
				<?php if ( '' !== $updated ) : ?>
					<span class="gco-pd-fhero__date">Updated <?php echo esc_html( $updated ); ?></span>
				<?php endif; ?>
			</div>
			<h1 class="gco-pd-hero__title"><?php echo esc_html( $title ); ?></h1>
			<?php if ( '' !== $intro && '' !== $tagline ) : ?>
				<p class="gco-pd-fhero__tagline"><?php echo esc_html( $tagline ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $intro || '' !== $tagline ) : ?>
				<p class="gco-pd-hero__intro"><?php echo esc_html( '' !== $intro ? $intro : $tagline ); ?></p>
			<?php endif; ?>
		</div>
		<?php if ( '' !== $crest ) : ?>
			<div class="gco-pd-fhero__crestwrap"><?php echo $crest; ?></div>
		<?php endif; ?>
	</div>
</header>
<?php
// phpcs:enable
