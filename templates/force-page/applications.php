<?php
/**
 * Force page: applications, backlog and service standards from the latest FOI response.
 * Each part appears only when switched on under Police Data → Settings and when the force gave the figures.
 *
 * @var string     $name     Force name.
 * @var array      $sections volumes | outstanding | backlog | standards.
 * @var string     $round    Round the figures come from, e.g. "December 2026".
 * @var bool       $stale    Force missed the latest round.
 * @var array|null $shared   Shared department notice.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- icons and GCO_PD_Shortcodes helpers return escaped markup.
?>
<section class="gco-pd-card gco-pd-fcard gco-pd-anchor" id="gco-applications">
	<header class="gco-pd-card__head">
		<?php echo GCO_PD_Icons::get( 'document', 'gco-pd-card__icon' ); ?>
		<div class="gco-pd-card__titles">
			<h2 class="gco-pd-card__title">Applications, Backlog &amp; Service Standards</h2>
			<p class="gco-pd-card__sub"><?php echo esc_html( sprintf( 'From the Freedom of Information response from %1$s, %2$s.', $name, $round ) ); ?></p>
		</div>
	</header>

	<?php if ( ! empty( $shared ) ) : ?>
		<p class="gco-pd-fnote gco-pd-fshared"><?php echo GCO_PD_Icons::get( 'users' ); ?> <?php echo esc_html( $shared['text'] ); ?></p>
	<?php endif; ?>
	<?php if ( $stale ) : ?>
		<p class="gco-pd-fnote"><?php echo esc_html( sprintf( '%s has not yet answered the latest FOI round, so these are its previous figures.', $name ) ); ?></p>
	<?php endif; ?>

	<div class="gco-pd-fapps">
		<?php foreach ( array( 'volumes', 'outstanding', 'standards' ) as $gco_key ) : ?>
			<?php
			if ( empty( $sections[ $gco_key ] ) ) {
				continue;
			}
			$gco_section = $sections[ $gco_key ];
			?>
			<section class="gco-pd-fapps__block gco-pd-fapps__block--<?php echo esc_attr( $gco_key ); ?>">
				<h3 class="gco-pd-fsub"><?php echo esc_html( $gco_section['title'] ); ?></h3>
				<?php if ( ! empty( $gco_section['period'] ) ) : ?>
					<p class="gco-pd-note"><?php echo esc_html( $gco_section['period'] ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $gco_section['text'] ) ) : ?>
					<p class="gco-pd-fapps__text"><?php echo esc_html( $gco_section['text'] ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $gco_section['rows'] ) ) : ?>
					<dl class="gco-pd-fapps__list">
						<?php foreach ( $gco_section['rows'] as $gco_row ) : ?>
							<div>
								<dt><?php echo esc_html( $gco_row['label'] ); ?></dt>
								<dd>
									<strong><?php echo esc_html( $gco_row['figure']['text'] ); ?></strong>
									<?php if ( 'Estimate' === $gco_row['figure']['basis'] ) : ?>
										<span class="gco-pd-badge gco-pd-badge--estimate">Estimate</span>
									<?php endif; ?>
									<?php if ( $gco_row['change'] ) : ?>
										<span class="gco-pd-fchange"><?php echo GCO_PD_Shortcodes::change_html( $gco_row['change'] ); ?> <span class="gco-pd-note">since the previous round</span></span>
									<?php endif; ?>
								</dd>
							</div>
						<?php endforeach; ?>
					</dl>
				<?php endif; ?>
			</section>
		<?php endforeach; ?>
	</div>

	<?php if ( ! empty( $sections['backlog'] ) ) : ?>
		<?php $gco_backlog = $sections['backlog']; ?>
		<section class="gco-pd-fapps__backlog">
			<h3 class="gco-pd-fsub"><?php echo GCO_PD_Icons::get( 'chart' ); ?> <?php echo esc_html( $gco_backlog['title'] ); ?>
				<?php if ( $gco_backlog['estimate'] ) : ?>
					<span class="gco-pd-badge gco-pd-badge--estimate">Estimate</span>
				<?php endif; ?>
			</h3>
			<?php if ( '' !== $gco_backlog['note'] ) : ?>
				<p class="gco-pd-note"><?php echo esc_html( $gco_backlog['note'] ); ?></p>
			<?php endif; ?>
			<?php if ( $gco_backlog['chart'] ) : ?>
				<div class="gco-pd-fchart__plot"><?php echo $gco_backlog['chart']; ?></div>
			<?php endif; ?>
			<details class="gco-pd-fhistory">
				<summary>Show these figures as a table<?php echo GCO_PD_Icons::get( 'chevron', 'gco-pd-faq__chevron' ); ?></summary>
				<div class="gco-pd-scroll">
					<table class="gco-pd-table">
						<caption class="gco-pd-sr"><?php echo esc_html( $name . ' outstanding applications by how long they have waited' ); ?></caption>
						<thead><tr><th scope="col">Waiting time</th><th scope="col" class="gco-pd-num"><?php echo $gco_backlog['percent'] ? 'Share of outstanding applications' : 'Applications'; ?></th></tr></thead>
						<tbody>
						<?php foreach ( $gco_backlog['rows'] as $gco_row ) : ?>
							<tr>
								<th scope="row"><?php echo esc_html( $gco_row['label'] ); ?></th>
								<td class="gco-pd-num"><?php echo null === $gco_row['text'] ? '<span class="gco-pd-na">Not provided</span>' : esc_html( $gco_row['text'] ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</details>
		</section>
	<?php endif; ?>
</section>
<?php
// phpcs:enable
