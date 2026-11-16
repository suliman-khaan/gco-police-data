<?php
/**
 * "At a glance": shortest and longest waits side by side.
 *
 * @var string  $phrase   Plural metric phrase, e.g. "shotgun certificate grants".
 * @var string  $round    Latest round, e.g. "June 2026".
 * @var array[] $shortest rank, name, full, url, days, basis.
 * @var array[] $longest  rank, name, full, url, days, basis.
 * @var int     $ranked   Forces ranked on this metric.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;

$gco_pd_lists = array(
	array( 'short', 'arrow-down', 'Shortest Reported Times', $shortest ),
	array( 'long', 'arrow-up', 'Longest Reported Times', $longest ),
);
?>
<div class="gco-pd gco-pd-glance">
	<?php foreach ( $gco_pd_lists as $list ) : ?>
		<section class="gco-pd-glance__col gco-pd-glance__col--<?php echo esc_attr( $list[0] ); ?>">
			<header class="gco-pd-glance__head">
				<?php echo GCO_PD_Icons::get( $list[1] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<div>
					<h3><?php echo esc_html( $list[2] ); ?></h3>
					<p><?php echo esc_html( sprintf( 'Top %1$d forces for %2$s', count( $list[3] ), $phrase ) ); ?></p>
				</div>
			</header>
			<ol class="gco-pd-glance__list">
				<?php foreach ( $list[3] as $entry ) : ?>
					<li>
						<span class="gco-pd-glance__rank"><?php echo esc_html( (string) $entry['rank'] ); ?></span>
						<span class="gco-pd-glance__name">
							<?php if ( $entry['url'] ) : ?>
								<a href="<?php echo esc_url( $entry['url'] ); ?>" title="<?php echo esc_attr( $entry['full'] ); ?>"><?php echo esc_html( $entry['name'] ); ?></a>
							<?php else : ?>
								<span title="<?php echo esc_attr( $entry['full'] ); ?>"><?php echo esc_html( $entry['name'] ); ?></span>
							<?php endif; ?>
						</span>
						<strong class="gco-pd-glance__days"><?php echo esc_html( $entry['days'] ); ?><?php echo 'Estimate' === $entry['basis'] ? '<abbr class="gco-pd-est" title="Estimate">*</abbr>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong>
					</li>
				<?php endforeach; ?>
			</ol>
		</section>
	<?php endforeach; ?>
</div>
<p class="gco-pd-foot gco-pd-glance__foot"><?php echo esc_html( sprintf( 'Out of %1$d forces that gave a figure%2$s. * Estimate.', $ranked, $round ? ', FOI round ' . $round : '' ) ); ?></p>
