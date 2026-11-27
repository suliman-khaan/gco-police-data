<?php
/**
 * Force page hero card (blog style): breadcrumb, tag and date, title, intro; photo and crest on the right.
 *
 * @var string  $title   Page title.
 * @var string  $tagline Tagline (shown when there is no intro).
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

$gco_pd_media = '' !== $image || '' !== $crest;
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- crest is wp_get_attachment_image().
?>
<header class="gco-pd-fhero<?php echo $gco_pd_media ? '' : ' gco-pd-fhero--plain'; ?>">
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
		<h1 class="gco-pd-fhero__title"><?php echo esc_html( $title ); ?></h1>
		<?php if ( '' !== $intro || '' !== $tagline ) : ?>
			<p class="gco-pd-fhero__intro"><?php echo esc_html( '' !== $intro ? $intro : $tagline ); ?></p>
		<?php endif; ?>
	</div>
	<?php if ( $gco_pd_media ) : ?>
		<div class="gco-pd-fhero__media<?php echo '' === $image ? ' gco-pd-fhero__media--crest' : ''; ?>"<?php echo '' !== $image ? ' style="background-image: url(' . esc_url( $image ) . ')"' : ''; ?>>
			<?php if ( '' !== $crest ) : ?>
				<span class="gco-pd-fhero__crestwrap"><?php echo $crest; ?></span>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</header>
<?php
// phpcs:enable
