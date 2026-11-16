<?php
/**
 * Live force search. The force list is printed once as JSON and filtered in the browser, so there is
 * no request per keystroke. Copy to {theme}/gco-police-data/portal-search.php to customise.
 *
 * @var array  $atts Attributes (placeholder, browse_url, browse_text).
 * @var string $id   Unique element id.
 * @var string $json JSON, already hex-escaped for use inside a script tag.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="gco-pd gco-pd-search" data-gco-search>
	<label class="gco-pd-search__label" for="<?php echo esc_attr( $id ); ?>">Find your police force</label>
	<div class="gco-pd-search__box">
		<input type="search" id="<?php echo esc_attr( $id ); ?>" class="gco-pd-search__input" autocomplete="off" spellcheck="false"
			placeholder="<?php echo esc_attr( $atts['placeholder'] ); ?>"
			role="combobox" aria-expanded="false" aria-controls="<?php echo esc_attr( $id ); ?>-list" aria-autocomplete="list">
		<ul class="gco-pd-search__list" id="<?php echo esc_attr( $id ); ?>-list" role="listbox" aria-label="Matching police forces" hidden></ul>
	</div>
	<p class="gco-pd-search__status" role="status" aria-live="polite"></p>
	<?php if ( '' !== $atts['browse_url'] ) : ?>
		<p class="gco-pd-search__browse"><a href="<?php echo esc_url( $atts['browse_url'] ); ?>"><?php echo esc_html( $atts['browse_text'] ); ?></a></p>
	<?php endif; ?>
	<script type="application/json" class="gco-pd-search__data"><?php echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode with JSON_HEX_* flags. ?></script>
</div>
