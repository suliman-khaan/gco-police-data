<?php
/**
 * Force page in the same layout as the main police page: a full-width hero band, a row of key figures
 * ("FOI at a glance"), a jump bar, then full-width section cards (GP medical and contact side by side),
 * with guides and sources underneath. Each section is its own template and is skipped when it has nothing to show.
 *
 * @var string     $name     Force name.
 * @var array      $hero     Hero.
 * @var array      $nav      "On this page" links: [id, label].
 * @var array|null $glance   FOI at a glance.
 * @var array|null $contact  Contact card.
 * @var array|null $gp       GP medical card.
 * @var array|null $area     Area card.
 * @var array|null $reports  Applicant reports.
 * @var array      $waits    Waiting times.
 * @var array|null $apps     Applications, backlog, standards.
 * @var array|null $shared   Shared department notice.
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
	<?php echo $gco_pd_part( 'hero', $hero ); ?>
	<?php echo $gco_pd_part( 'glance', $glance ? array_merge( $glance, array( 'name' => $name ) ) : null ); ?>

	<?php if ( count( $nav ) > 1 ) : ?>
		<nav class="gco-pd-fjump" aria-label="On this page" data-gco-fnav>
			<span class="gco-pd-fjump__label">On this page</span>
			<?php foreach ( $nav as $item ) : ?>
				<a href="#<?php echo esc_attr( $item[0] ); ?>"><?php echo esc_html( $item[1] ); ?></a>
			<?php endforeach; ?>
		</nav>
	<?php endif; ?>

	<?php echo $gco_pd_part( 'area', $area ); ?>
	<?php echo $gco_pd_part( 'waits', array_merge( $waits, array( 'name' => $name, 'shared' => $shared ) ) ); ?>
	<?php echo $gco_pd_part( 'applications', $apps ? array_merge( $apps, array( 'name' => $name, 'shared' => $shared ) ) : null ); ?>
	<?php echo $gco_pd_part( 'reports', $reports ); ?>
	<?php if ( $gp && $contact ) : ?>
		<div class="gco-pd-fpair">
			<?php echo $gco_pd_part( 'gp', $gp ); ?>
			<?php echo $gco_pd_part( 'contact', $contact ); ?>
		</div>
	<?php else : ?>
		<?php echo $gco_pd_part( 'gp', $gp ); ?>
		<?php echo $gco_pd_part( 'contact', $contact ); ?>
	<?php endif; ?>
	<?php echo $gco_pd_part( 'overview', $overview ); ?>
	<?php echo $gco_pd_part( 'inspection', $inspect ); ?>
	<?php if ( $faqs ) : ?>
		<div id="gco-faqs" class="gco-pd-anchor gco-pd-ffaqs"><?php echo GCO_PD_Shortcodes::template( 'portal-faqs', $faqs ); ?></div>
	<?php endif; ?>
	<?php echo $guides; ?>
	<?php echo $gco_pd_part( 'review', $review ); ?>
</div>
<?php
// phpcs:enable
