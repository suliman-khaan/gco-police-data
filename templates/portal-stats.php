<?php
/**
 * Stat cards.
 *
 * @var array[] $cards Each: label, value (null = not available), note.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="gco-pd gco-pd-stats">
	<?php foreach ( $cards as $card ) : ?>
		<div class="gco-pd-stat">
			<span class="gco-pd-stat__label"><?php echo esc_html( $card[0] ); ?></span>
			<?php if ( null !== $card[1] ) : ?>
				<strong class="gco-pd-stat__value"><?php echo esc_html( $card[1] ); ?></strong>
			<?php else : ?>
				<span class="gco-pd-na">Not yet available</span>
			<?php endif; ?>
			<?php if ( '' !== (string) $card[2] ) : ?>
				<span class="gco-pd-stat__note"><?php echo esc_html( $card[2] ); ?></span>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
</div>
