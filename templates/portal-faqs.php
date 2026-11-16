<?php
/**
 * FAQ accordion (native <details>) plus one FAQPage JSON-LD block.
 *
 * @var string  $title   Title.
 * @var int     $columns 1 or 2.
 * @var array[] $pairs   question, answer (plain text), html.
 * @var bool    $schema  Whether to print the JSON-LD.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;

$gco_pd_entities = array();
foreach ( $pairs as $pair ) {
	$gco_pd_entities[] = array(
		'@type'          => 'Question',
		'name'           => $pair['question'],
		'acceptedAnswer' => array(
			'@type' => 'Answer',
			'text'  => $pair['answer'],
		),
	);
}
?>
<section class="gco-pd gco-pd-card gco-pd-card--faqs">
	<?php if ( '' !== $title ) : ?>
		<header class="gco-pd-card__head">
			<?php echo GCO_PD_Icons::get( 'help', 'gco-pd-card__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<div class="gco-pd-card__titles"><h2 class="gco-pd-card__title"><?php echo esc_html( $title ); ?></h2></div>
		</header>
	<?php endif; ?>
	<div class="gco-pd-faqs gco-pd-faqs--cols-<?php echo esc_attr( (string) $columns ); ?>">
		<?php foreach ( $pairs as $pair ) : ?>
			<details class="gco-pd-faq">
				<summary><?php echo esc_html( $pair['question'] ); ?><?php echo GCO_PD_Icons::get( 'chevron', 'gco-pd-faq__chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></summary>
				<div class="gco-pd-faq__answer"><?php echo $pair['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses_post(). ?></div>
			</details>
		<?php endforeach; ?>
	</div>
	<?php if ( $schema ) : ?>
		<script type="application/ld+json"><?php echo wp_json_encode( array( '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $gco_pd_entities ), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG ); ?></script>
	<?php endif; ?>
</section>
