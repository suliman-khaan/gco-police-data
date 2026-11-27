<?php
/**
 * Sidebar box: the force's latest FOI waiting times and NPCC figures at a glance.
 *
 * @var string      $name     Force name.
 * @var array[]     $rows     label, figure (from GCO_PD_Shortcodes::figure()).
 * @var array|null  $change   days, pct, direction: shotgun grant, latest vs previous FOI.
 * @var array[]     $stats    [value, label].
 * @var string|null $ref      FOI reference.
 * @var string      $date     Response date (or round).
 * @var bool        $stale    The force missed the latest round.
 * @var string      $round    Round shown.
 * @var string      $btn_text Shop button text.
 * @var string      $btn_url  Shop button URL.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- icons are fixed markup.
?>
<section class="gco-pd-fbox gco-pd-glance-box" aria-labelledby="gco-glance-title">
	<h2 class="gco-pd-fbox__title" id="gco-glance-title">FOI at a glance</h2>
	<?php if ( $rows ) : ?>
		<?php if ( $stale ) : ?>
			<p class="gco-pd-glance-box__sub"><?php echo esc_html( sprintf( 'Figures from the %2$s response, the latest %1$s has sent.', $name, $round ) ); ?></p>
		<?php endif; ?>
		<dl class="gco-pd-glance-box__list">
			<?php foreach ( $rows as $row ) : ?>
				<div>
					<dt><?php echo esc_html( $row['label'] ); ?></dt>
					<dd>
						<?php if ( null === $row['figure']['text'] ) : ?>
							<span class="gco-pd-na">Not provided</span>
						<?php else : ?>
							<strong><?php echo esc_html( $row['figure']['text'] ); ?></strong>
							<?php if ( 'Estimate' === $row['figure']['basis'] ) : ?>
								<abbr class="gco-pd-glance-box__est" title="Estimate given by the force">est.</abbr>
							<?php endif; ?>
						<?php endif; ?>
					</dd>
				</div>
			<?php endforeach; ?>
		</dl>
		<?php if ( $change && 0 !== $change['days'] ) : ?>
			<p class="gco-pd-glance-box__change gco-pd-glance-box__change--<?php echo esc_attr( (string) $change['direction'] ); ?>">
				<?php echo GCO_PD_Icons::get( $change['days'] > 0 ? 'arrow-up' : 'arrow-down' ); ?>
				<span>Shotgun grants <?php echo esc_html( abs( $change['days'] ) . ' ' . ( 1 === abs( $change['days'] ) ? 'day' : 'days' ) . ( $change['days'] > 0 ? ' slower' : ' faster' ) ); ?> than the previous FOI</span>
			</p>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( $stats ) : ?>
		<ul class="gco-pd-glance-box__stats">
			<?php foreach ( $stats as $stat ) : ?>
				<li><strong><?php echo esc_html( $stat[0] ); ?></strong><span><?php echo esc_html( $stat[1] ); ?></span></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<?php if ( '' !== (string) $ref || '' !== $date ) : ?>
		<p class="gco-pd-glance-box__ref">
			<?php echo GCO_PD_Icons::get( 'document' ); ?>
			<span>
				FOI response<?php echo '' !== $date ? ' ' . esc_html( $date ) : ''; ?>
				<?php if ( '' !== (string) $ref ) : ?>
					<br>Ref: <?php echo esc_html( $ref ); ?>
				<?php endif; ?>
			</span>
		</p>
	<?php endif; ?>

	<div class="gco-pd-glance-box__btns">
		<?php if ( $rows ) : ?>
			<a class="gco-pd-outline" href="#gco-waits">See full waiting times<?php echo GCO_PD_Icons::get( 'arrow-down' ); ?></a>
		<?php endif; ?>
		<?php if ( '' !== $btn_text && '' !== $btn_url ) : ?>
			<a class="gco-pd-button" href="<?php echo esc_url( $btn_url ); ?>"><?php echo esc_html( $btn_text ); ?><?php echo GCO_PD_Icons::get( 'arrow-right' ); ?></a>
		<?php endif; ?>
	</div>
</section>
<?php
// phpcs:enable
