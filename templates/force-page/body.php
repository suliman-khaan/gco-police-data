<?php
/**
 * Force page body: jump navigation, sidebar + main column, then full-width sections.
 * Each section is its own template and is skipped when it has nothing to show.
 *
 * @var string     $name     Force name.
 * @var array      $nav      Jump links: [id, [line1, line2], icon].
 * @var array|null $contact  Contact card.
 * @var array|null $gp       GP medical card.
 * @var array|null $area     Area card.
 * @var array|null $reports  Applicant reports.
 * @var array      $waits    Waiting times.
 * @var array|null $overview National overview.
 * @var array|null $inspect  Security inspection.
 * @var array|null $faqs     FAQs.
 * @var string     $guides   Related guides markup.
 * @var array|null $review   Sources, disclaimer, review dates.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;

$gco_pd_part = static function ( $name, $vars ) {
	return $vars ? GCO_PD_Shortcodes::template( 'force-page/' . $name, (array) $vars ) : '';
};
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered templates.
?>
<div class="gco-pd gco-pd-fpage">
	<?php if ( count( $nav ) > 1 ) : ?>
		<nav class="gco-pd-fnav" aria-label="On this page" data-gco-fnav>
			<?php foreach ( $nav as $item ) : ?>
				<a class="gco-pd-fnav__item" href="#<?php echo esc_attr( $item[0] ); ?>">
					<?php echo GCO_PD_Icons::get( $item[2] ); ?>
					<span><?php echo esc_html( $item[1][0] ); ?><?php echo '' !== $item[1][1] ? '<br>' . esc_html( $item[1][1] ) : ''; ?></span>
				</a>
			<?php endforeach; ?>
		</nav>
	<?php endif; ?>

	<div class="gco-pd-fgrid<?php echo ( $contact || $gp ) ? '' : ' gco-pd-fgrid--single'; ?>">
		<?php if ( $contact || $gp ) : ?>
			<aside class="gco-pd-fside">
				<?php echo $gco_pd_part( 'contact', $contact ); ?>
				<?php echo $gco_pd_part( 'gp', $gp ); ?>
			</aside>
		<?php endif; ?>
		<div class="gco-pd-fmain">
			<?php echo $gco_pd_part( 'area', $area ); ?>
			<?php echo $gco_pd_part( 'reports', $reports ); ?>
			<?php echo $gco_pd_part( 'waits', array_merge( $waits, array( 'name' => $name, 'shared' => $shared ) ) ); ?>
			<?php echo $gco_pd_part( 'applications', $apps ? array_merge( $apps, array( 'name' => $name, 'shared' => $shared ) ) : null ); ?>
		</div>
	</div>

	<?php echo $gco_pd_part( 'overview', $overview ); ?>
	<?php echo $gco_pd_part( 'inspection', $inspect ); ?>
	<?php if ( $faqs ) : ?>
		<div id="gco-faqs" class="gco-pd-anchor"><?php echo GCO_PD_Shortcodes::template( 'portal-faqs', $faqs ); ?></div>
	<?php endif; ?>
	<?php echo $guides; ?>
	<?php echo $gco_pd_part( 'review', $review ); ?>
</div>
<?php
// phpcs:enable
