<?php
/**
 * Stat cards.
 *
 * @var array[] $cards Each: [0] icon, [1] value (null = not available), [2] label, [3] source/help text.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="gco-pd gco-pd-stats">
	<?php foreach ( $cards as $card ) : ?>
		<div class="gco-pd-stat">
			<span class="gco-pd-stat__icon"><?php echo GCO_PD_Icons::get( $card[0] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<div class="gco-pd-stat__text">
				<?php if ( null !== $card[1] ) : ?>
					<strong class="gco-pd-stat__value"><?php echo esc_html( $card[1] ); ?></strong>
				<?php else : ?>
					<span class="gco-pd-stat__value gco-pd-na">Not yet available</span>
				<?php endif; ?>
				<span class="gco-pd-stat__label">
					<?php echo esc_html( $card[2] ); ?>
					<?php if ( '' !== (string) $card[3] ) : ?>
						<span class="gco-pd-info" tabindex="0" role="note" aria-label="<?php echo esc_attr( $card[3] ); ?>" data-tip="<?php echo esc_attr( $card[3] ); ?>"><?php echo GCO_PD_Icons::get( 'info' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<?php endif; ?>
				</span>
			</div>
		</div>
	<?php endforeach; ?>
</div>
