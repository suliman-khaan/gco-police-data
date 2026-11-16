<?php
/**
 * "Last updated / source" line for a force page.
 *
 * @var array  $force   Snapshot force.
 * @var string $updated Round the FOI figures come from, e.g. "June 2026" (empty when none).
 * @var bool   $stale   True when this force did not answer the latest round.
 * @var string $latest  Latest round label.
 * @var string $npcc    NPCC source text (empty when none).
 * @var string $pdf     Public PDF URL (empty unless the setting is on).
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;
?>
<p class="gco-pd gco-pd-updated">
	<?php if ( '' !== $updated ) : ?>
		<span>Last updated <strong><?php echo esc_html( $updated ); ?></strong> &middot; Source: FOI response from <?php echo esc_html( $force['name'] ); ?></span>
		<?php if ( $pdf ) : ?>
			&middot; <a href="<?php echo esc_url( $pdf ); ?>" rel="noopener">Read the original response (PDF)</a>
		<?php endif; ?>
		<?php if ( $stale && '' !== $latest ) : ?>
			<span class="gco-pd-note">(no response yet for <?php echo esc_html( $latest ); ?>)</span>
		<?php endif; ?>
	<?php endif; ?>
	<?php if ( '' !== $npcc ) : ?>
		<br><span>Application statistics: <?php echo esc_html( $npcc ); ?></span>
	<?php endif; ?>
</p>
