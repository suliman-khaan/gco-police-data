<?php
/**
 * FAQPage JSON-LD for the `[gco_police_faq_schema]` wrapper.
 *
 * @package GCO_Police_Data
 *
 * @var array{question:string,answer:string}[] $pairs Question/answer pairs.
 */

defined( 'ABSPATH' ) || exit;

$entities = array();
foreach ( $pairs as $pair ) {
	$entities[] = array(
		'@type'          => 'Question',
		'name'           => $pair['question'],
		'acceptedAnswer' => array(
			'@type' => 'Answer',
			'text'  => $pair['answer'],
		),
	);
}

$schema = array(
	'@context'   => 'https://schema.org',
	'@type'      => 'FAQPage',
	'mainEntity' => $entities,
);
?>
<script type="application/ld+json"><?php echo wp_json_encode( $schema, JSON_UNESCAPED_SLASHES ); ?></script>
