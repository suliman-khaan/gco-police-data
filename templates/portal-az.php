<?php
/**
 * A-Z browse. Without JavaScript the full list is shown and the letters jump to their group.
 *
 * @var string $id     Unique id prefix.
 * @var array  $groups Letter => list of name/url.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="gco-pd gco-pd-az" data-gco-az>
	<nav class="gco-pd-az__nav" aria-label="Filter forces by first letter">
		<?php foreach ( array_keys( $groups ) as $letter ) : ?>
			<a class="gco-pd-az__letter" href="#<?php echo esc_attr( $id . '-' . $letter ); ?>" data-letter="<?php echo esc_attr( $letter ); ?>"><?php echo esc_html( $letter ); ?></a>
		<?php endforeach; ?>
	</nav>
	<?php foreach ( $groups as $letter => $forces ) : ?>
		<section class="gco-pd-az__group" id="<?php echo esc_attr( $id . '-' . $letter ); ?>" data-letter="<?php echo esc_attr( $letter ); ?>">
			<h3><?php echo esc_html( $letter ); ?></h3>
			<ul>
				<?php foreach ( $forces as $force ) : ?>
					<li>
						<?php if ( $force['url'] ) : ?>
							<a href="<?php echo esc_url( $force['url'] ); ?>"><?php echo esc_html( $force['name'] ); ?></a>
						<?php else : ?>
							<?php echo esc_html( $force['name'] ); ?>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endforeach; ?>
</div>
