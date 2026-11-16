<?php
/**
 * One form field for the force page editors. Included in a loop with:
 *
 * @var string $gco_field_name  Input name, e.g. profile[contact_phone].
 * @var string $gco_field_id    Element id.
 * @var array  $gco_field       Definition: [type, label, help, (sub-fields for lists)].
 * @var mixed  $gco_field_value Current value.
 * @var bool   $gco_field_rich  Use the WordPress editor for html fields (top level only).
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;

$gco_type  = $gco_field[0];
$gco_label = $gco_field[1];
$gco_help  = $gco_field[2] ?? '';
?>
<div class="gco-pd-field gco-pd-field--<?php echo esc_attr( $gco_type ); ?>">
	<?php if ( 'list' === $gco_type ) : ?>
		<?php
		$gco_subs = $gco_field[3];
		$gco_rows = is_array( $gco_field_value ) ? $gco_field_value : array();
		?>
		<fieldset class="gco-pd-repeater" data-gco-repeater>
			<legend class="gco-pd-field__label"><?php echo esc_html( $gco_label ); ?></legend>
			<?php if ( '' !== $gco_help ) : ?>
				<p class="description"><?php echo esc_html( $gco_help ); ?></p>
			<?php endif; ?>
			<ol class="gco-pd-repeater__rows" data-gco-rows>
				<?php foreach ( array_merge( $gco_rows, array( '__template__' ) ) as $gco_index => $gco_row ) : ?>
					<?php
					$gco_is_template = '__template__' === $gco_row;
					$gco_key         = $gco_is_template ? '__i__' : (string) $gco_index;
					?>
					<?php if ( $gco_is_template ) : ?>
						<template data-gco-template>
					<?php endif; ?>
					<li class="gco-pd-repeater__row">
						<div class="gco-pd-repeater__fields">
							<?php foreach ( $gco_subs as $gco_sub_key => $gco_sub ) : ?>
								<?php
								$gco_sub_name  = $gco_field_name . '[' . $gco_key . '][' . $gco_sub_key . ']';
								$gco_sub_value = $gco_is_template ? '' : (string) ( $gco_row[ $gco_sub_key ] ?? '' );
								$gco_multiline = in_array( $gco_sub[0], array( 'textarea', 'html' ), true );
								?>
								<label class="gco-pd-repeater__field<?php echo $gco_multiline ? ' is-wide' : ''; ?>">
									<span><?php echo esc_html( $gco_sub[1] ); ?><?php echo 'html' === $gco_sub[0] ? ' <em>(simple HTML allowed)</em>' : ''; ?></span>
									<?php if ( $gco_multiline ) : ?>
										<textarea name="<?php echo esc_attr( $gco_sub_name ); ?>" rows="<?php echo 'html' === $gco_sub[0] ? 4 : 3; ?>"><?php echo esc_textarea( $gco_sub_value ); ?></textarea>
									<?php else : ?>
										<input type="<?php echo 'url' === $gco_sub[0] ? 'url' : 'text'; ?>" name="<?php echo esc_attr( $gco_sub_name ); ?>" value="<?php echo esc_attr( $gco_sub_value ); ?>"<?php echo isset( $gco_sub[2] ) ? ' placeholder="' . esc_attr( $gco_sub[2] ) . '"' : ''; ?>>
									<?php endif; ?>
								</label>
							<?php endforeach; ?>
						</div>
						<div class="gco-pd-repeater__tools">
							<button type="button" class="button-link" data-gco-move="-1" aria-label="Move up">&uarr;</button>
							<button type="button" class="button-link" data-gco-move="1" aria-label="Move down">&darr;</button>
							<button type="button" class="button-link button-link-delete" data-gco-remove>Remove</button>
						</div>
					</li>
					<?php if ( $gco_is_template ) : ?>
						</template>
					<?php endif; ?>
				<?php endforeach; ?>
			</ol>
			<p><button type="button" class="button" data-gco-add>Add <?php echo esc_html( strtolower( rtrim( $gco_label, 's' ) ) ); ?></button></p>
		</fieldset>

	<?php elseif ( 'image' === $gco_type ) : ?>
		<?php $gco_src = $gco_field_value ? wp_get_attachment_image_url( (int) $gco_field_value, 'medium' ) : ''; ?>
		<span class="gco-pd-field__label"><?php echo esc_html( $gco_label ); ?></span>
		<div class="gco-pd-media" data-gco-media>
			<input type="hidden" name="<?php echo esc_attr( $gco_field_name ); ?>" value="<?php echo esc_attr( (string) (int) $gco_field_value ); ?>" data-gco-media-id>
			<div class="gco-pd-media__preview" data-gco-media-preview>
				<?php if ( $gco_src ) : ?>
					<img src="<?php echo esc_url( $gco_src ); ?>" alt="">
				<?php elseif ( $gco_field_value ) : ?>
					<span class="description">Image #<?php echo esc_html( (string) (int) $gco_field_value ); ?> (file not found on this site)</span>
				<?php endif; ?>
			</div>
			<button type="button" class="button" data-gco-media-choose>Choose image</button>
			<button type="button" class="button-link button-link-delete" data-gco-media-clear<?php echo $gco_field_value ? '' : ' hidden'; ?>>Remove</button>
		</div>
		<?php if ( '' !== $gco_help ) : ?>
			<p class="description"><?php echo esc_html( $gco_help ); ?></p>
		<?php endif; ?>

	<?php else : ?>
		<label class="gco-pd-field__label" for="<?php echo esc_attr( $gco_field_id ); ?>"><?php echo esc_html( $gco_label ); ?></label>
		<?php if ( 'html' === $gco_type && ! empty( $gco_field_rich ) ) : ?>
			<?php
			wp_editor(
				(string) $gco_field_value,
				$gco_field_id,
				array(
					'textarea_name' => $gco_field_name,
					'textarea_rows' => 6,
					'media_buttons' => false,
					'teeny'         => true,
					'quicktags'     => array( 'buttons' => 'strong,em,link,ul,ol,li' ),
				)
			);
			?>
		<?php elseif ( in_array( $gco_type, array( 'textarea', 'lines', 'html' ), true ) ) : ?>
			<textarea class="large-text" id="<?php echo esc_attr( $gco_field_id ); ?>" name="<?php echo esc_attr( $gco_field_name ); ?>" rows="<?php echo 'lines' === $gco_type ? 5 : 4; ?>"><?php echo esc_textarea( (string) $gco_field_value ); ?></textarea>
		<?php else : ?>
			<?php
			$gco_input = array(
				'url'   => 'url',
				'email' => 'email',
				'date'  => 'date',
			)[ $gco_type ] ?? 'text';
			?>
			<input class="regular-text<?php echo 'url' === $gco_type ? ' code' : ''; ?>" type="<?php echo esc_attr( $gco_input ); ?>" id="<?php echo esc_attr( $gco_field_id ); ?>" name="<?php echo esc_attr( $gco_field_name ); ?>" value="<?php echo esc_attr( (string) $gco_field_value ); ?>">
		<?php endif; ?>
		<?php if ( '' !== $gco_help ) : ?>
			<p class="description"><?php echo esc_html( $gco_help ); ?></p>
		<?php endif; ?>
	<?php endif; ?>
</div>
