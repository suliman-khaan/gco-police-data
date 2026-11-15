<?php
/**
 * Round editor: one row per force, value + status per metric.
 *
 * @var object   $round     Round.
 * @var object[] $forces    Forces to list.
 * @var array    $responses Force id => response row.
 * @var array    $failed    Rows from a failed save: errors and the values as typed.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;

$is_published = 'published' === $round->status;
$labels       = array(
	'sg_grant'    => 'Shotgun grant (days)',
	'fac_grant'   => 'Firearm grant (days)',
	'sg_renew'    => 'Shotgun renewal (days)',
	'fac_renew'   => 'Firearm renewal (days)',
	'variation'   => 'Variation (days)',
	'sg_holders'  => 'Shotgun holders',
	'fac_holders' => 'Firearm holders',
);
$status_options = array(
	'actual'       => 'Actual',
	'estimate'     => 'Estimate',
	'not_provided' => 'Not provided',
);
$disabled = $is_published ? ' disabled' : '';
?>
<h1>
	Round <?php echo esc_html( $round->label ); ?>
	<span class="gco-pd-badge gco-pd-badge--<?php echo esc_attr( $round->status ); ?>"><?php echo esc_html( ucfirst( $round->status ) ); ?></span>
</h1>
<p><a href="<?php echo esc_url( GCO_PD_Admin::url( GCO_PD_Admin::SLUG_ROUNDS ) ); ?>">&larr; All rounds</a></p>

<?php if ( $is_published ) : ?>
	<div class="notice notice-info inline"><p>This round is published and read-only. Unpublish it from the rounds list to change figures.</p></div>
<?php else : ?>
	<div class="gco-pd-help">
		<ul>
			<li>Enter <strong>whole days</strong>. If a force replied in weeks, multiply by 7. For a range, enter the upper figure, choose <em>Estimate</em> and put their wording in Notes.</li>
			<li><strong>Never type 0 for missing data.</strong> Leave the number blank and keep <em>Not provided</em>; that force is then left out of the rankings for that measure.</li>
			<li>A completely blank row means the force has not responded this round; the site keeps showing its last published figures.</li>
			<li>Red cells: the number and its status don't match. Fix these before saving.</li>
		</ul>
	</div>
<?php endif; ?>

<form id="gco-pd-grid-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="gco_pd_save_round">
	<input type="hidden" name="round" value="<?php echo esc_attr( (string) $round->id ); ?>">
	<input type="hidden" id="gco-pd-payload" name="payload" value="">
	<?php wp_nonce_field( 'gco_pd_save_round' ); ?>

	<?php if ( ! $is_published ) : ?>
		<p class="submit gco-pd-savebar"><button type="submit" class="button button-primary button-large">Save figures</button> <span class="description" id="gco-pd-problems"></span></p>
	<?php endif; ?>

	<div class="gco-pd-scroll">
	<table class="widefat gco-pd-grid">
		<thead>
			<tr>
				<th class="gco-pd-sticky">Force</th>
				<th>FOI reference</th>
				<th>Response date</th>
				<?php foreach ( $labels as $label ) : ?>
					<th><?php echo esc_html( $label ); ?></th>
				<?php endforeach; ?>
				<th>Notes (private)</th>
				<th>FOI letter (PDF)</th>
				<th>Problems</th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $forces as $force ) : ?>
			<?php
			$id       = (int) $force->id;
			$existing = $responses[ $id ] ?? null;
			$typed    = $failed['rows'][ $id ] ?? null;
			$errors   = $failed['errors'][ $id ] ?? array();
			$get      = static function ( $field, $default = '' ) use ( $existing, $typed ) {
				if ( null !== $typed ) {
					return (string) ( $typed[ $field ] ?? $default );
				}
				if ( $existing && isset( $existing->$field ) && null !== $existing->$field ) {
					return (string) $existing->$field;
				}
				return $default;
			};
			$pdf_id   = (int) $get( 'pdf_id', '0' );
			$pdf_name = $pdf_id ? wp_basename( (string) get_attached_file( $pdf_id ) ) : '';
			?>
			<tr class="gco-pd-row<?php echo $errors ? ' has-server-error' : ''; ?>" data-force="<?php echo esc_attr( (string) $id ); ?>">
				<th class="gco-pd-sticky"><?php echo esc_html( $force->name ); ?><?php echo $force->is_active ? '' : ' <em>(inactive)</em>'; ?></th>
				<td><input type="text" data-f="foi_ref" value="<?php echo esc_attr( $get( 'foi_ref' ) ); ?>" maxlength="80"<?php echo $disabled; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>></td>
				<td><input type="date" data-f="response_date" value="<?php echo esc_attr( $get( 'response_date' ) ); ?>"<?php echo $disabled; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>></td>
				<?php foreach ( GCO_PD_Validator::FOI_METRICS as $key => $meta ) : ?>
					<?php $status = $get( $meta['status'], 'not_provided' ); ?>
					<td class="gco-pd-metric<?php echo isset( $errors[ $meta['column'] ] ) ? ' is-bad' : ''; ?>">
						<input type="number" min="0" step="1" data-f="<?php echo esc_attr( $meta['column'] ); ?>" value="<?php echo esc_attr( $get( $meta['column'] ) ); ?>" aria-label="<?php echo esc_attr( $force->name . ' ' . $labels[ $key ] ); ?>"<?php echo $disabled; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<select data-f="<?php echo esc_attr( $meta['status'] ); ?>" aria-label="<?php echo esc_attr( $labels[ $key ] . ' status' ); ?>"<?php echo $disabled; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
							<?php foreach ( $status_options as $value => $text ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>><?php echo esc_html( $text ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				<?php endforeach; ?>
				<td><input type="text" data-f="notes" value="<?php echo esc_attr( wp_strip_all_tags( $get( 'notes' ) ) ); ?>"<?php echo $disabled; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>></td>
				<td class="gco-pd-pdf">
					<input type="hidden" data-f="pdf_id" value="<?php echo esc_attr( (string) $pdf_id ); ?>">
					<span class="gco-pd-pdf-name"><?php echo $pdf_name ? esc_html( $pdf_name ) : '&mdash;'; ?></span>
					<?php if ( ! $is_published ) : ?>
						<button type="button" class="button button-small gco-pd-pick-pdf">Choose</button>
						<button type="button" class="button-link gco-pd-clear-pdf"<?php echo $pdf_id ? '' : ' hidden'; ?>>Remove</button>
					<?php endif; ?>
				</td>
				<td class="gco-pd-msg">
					<?php foreach ( $errors as $message ) : ?>
						<div class="gco-pd-error"><?php echo esc_html( $message ); ?></div>
					<?php endforeach; ?>
					<div class="gco-pd-live-error" hidden></div>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	</div>

	<?php if ( ! $is_published ) : ?>
		<p class="submit"><button type="submit" class="button button-primary button-large">Save figures</button></p>
	<?php endif; ?>
</form>
