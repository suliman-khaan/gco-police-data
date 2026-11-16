<?php
/**
 * "At a glance": shortest and longest waits side by side.
 *
 * @var string  $label    Metric label.
 * @var string  $round    Latest round, e.g. "June 2026".
 * @var array[] $shortest rank, name, url, days, basis.
 * @var array[] $longest  rank, name, url, days, basis.
 * @var int     $ranked   Forces ranked on this metric.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;

$gco_pd_lists = array(
	'Shortest waits' => $shortest,
	'Longest waits'  => $longest,
);
?>
<div class="gco-pd gco-pd-glance">
	<?php foreach ( $gco_pd_lists as $heading => $entries ) : ?>
		<section class="gco-pd-glance__col" aria-labelledby="gco-pd-glance-<?php echo esc_attr( sanitize_title( $heading . $label ) ); ?>">
			<h3 id="gco-pd-glance-<?php echo esc_attr( sanitize_title( $heading . $label ) ); ?>"><?php echo esc_html( $heading ); ?></h3>
			<p class="gco-pd-note"><?php echo esc_html( $label ); ?><?php echo $round ? esc_html( ', ' . $round ) : ''; ?></p>
			<ol class="gco-pd-glance__list">
				<?php foreach ( $entries as $entry ) : ?>
					<li value="<?php echo esc_attr( (string) $entry['rank'] ); ?>">
						<span class="gco-pd-glance__name">
							<?php if ( $entry['url'] ) : ?>
								<a href="<?php echo esc_url( $entry['url'] ); ?>"><?php echo esc_html( $entry['name'] ); ?></a>
							<?php else : ?>
								<?php echo esc_html( $entry['name'] ); ?>
							<?php endif; ?>
						</span>
						<strong><?php echo esc_html( $entry['days'] ); ?></strong>
						<?php if ( 'Estimate' === $entry['basis'] ) : ?>
							<span class="gco-pd-badge gco-pd-badge--estimate">Estimate</span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ol>
		</section>
	<?php endforeach; ?>
	<p class="gco-pd-note gco-pd-glance__foot"><?php echo esc_html( sprintf( 'Out of %d forces that gave a figure. Forces that did not provide one are not ranked.', $ranked ) ); ?></p>
</div>
