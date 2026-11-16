<?php
/**
 * Rank of one force against the others, per measure. Forces without a figure are not ranked.
 *
 * @var array   $force Snapshot force.
 * @var array[] $rows  label, rank ("5th of 43" or null), note.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="gco-pd gco-pd-rank">
	<h3 class="gco-pd-rank__title"><?php echo esc_html( 'How ' . $force['name'] . ' compares' ); ?></h3>
	<ul class="gco-pd-rank__list">
		<?php foreach ( $rows as $row ) : ?>
			<li>
				<span class="gco-pd-rank__label"><?php echo esc_html( $row['label'] ); ?></span>
				<?php if ( $row['rank'] ) : ?>
					<strong class="gco-pd-rank__value"><?php echo esc_html( $row['rank'] ); ?></strong>
					<?php if ( $row['note'] ) : ?>
						<span class="gco-pd-note">(<?php echo esc_html( $row['note'] ); ?>)</span>
					<?php endif; ?>
				<?php else : ?>
					<span class="gco-pd-na">Not ranked</span>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
	<p class="gco-pd-note">1st is the shortest wait, or the highest share of applications completed within 4 months. Forces that did not provide a figure are not ranked.</p>
</div>
