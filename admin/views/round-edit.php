<?php
/**
 * Round editor: one row per force. Two tabs share one form and one Save button:
 *  1. Waiting times and holders (value + status per metric)
 *  2. Applications, backlog and service standards (the extra figures)
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
$extra_labels   = array(
	'apps_received'  => 'Applications received',
	'apps_completed' => 'Applications completed',
	'outstanding'    => 'Outstanding applications',
	'oldest'         => 'Oldest outstanding (days)',
	'standard_days'  => 'Service standard (days)',
	'standard_met'   => 'Met the standard (%)',
);
$status_options = array(
	'actual'       => 'Actual',
	'estimate'     => 'Estimate',
	'not_provided' => 'Not provided',
);
$unit_options   = array(
	'count'   => 'Count',
	'percent' => 'Percentage',
);
$disabled = $is_published ? ' disabled' : '';

// Error keys that belong to the second tab.
$extra_cols = array( 'backlog_unit', 'backlog_status', 'volumes_period', 'standard_text' );
foreach ( GCO_PD_Validator::FOI_EXTRA_METRICS as $gco_meta ) {
	$extra_cols[] = $gco_meta['column'];
}
$extra_cols = array_merge( $extra_cols, array_keys( GCO_PD_Validator::BACKLOG_BANDS ) );
$errors_waits  = 0;
$errors_extras = 0;
foreach ( (array) ( $failed['errors'] ?? array() ) as $gco_row_errors ) {
	foreach ( array_keys( (array) $gco_row_errors ) as $gco_col ) {
		in_array( $gco_col, $extra_cols, true ) ? ++$errors_extras : ++$errors_waits;
	}
}
$tab_open = isset( $_GET['tab'] ) && 'extras' === sanitize_key( wp_unslash( $_GET['tab'] ) ) ? 'extras' : 'waits'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
if ( $errors_extras && ! $errors_waits ) {
	$tab_open = 'extras';
}

/**
 * One value + status cell of the extras grid.
 *
 * @param string $key   Extra metric key.
 * @param object $force Force.
 * @param array  $ctx   get closure, errors, labels, status options, disabled attribute.
 */
$gco_extra_cell = static function ( $key, $force, array $ctx ) {
	$meta   = GCO_PD_Validator::FOI_EXTRA_METRICS[ $key ];
	$status = $ctx['get']( $meta['status'], 'not_provided' );
	$kind   = $meta['type'];
	?>
	<td class="gco-pd-metric<?php echo isset( $ctx['errors'][ $meta['column'] ] ) ? ' is-bad' : ''; ?>">
		<input type="number" min="0" <?php echo 'percent' === $kind ? 'max="100" step="0.01"' : 'step="1"'; ?> data-f="<?php echo esc_attr( $meta['column'] ); ?>" data-status="<?php echo esc_attr( $meta['status'] ); ?>" data-kind="<?php echo esc_attr( $kind ); ?>" value="<?php echo esc_attr( $ctx['get']( $meta['column'] ) ); ?>" aria-label="<?php echo esc_attr( $force->name . ' ' . $ctx['labels'][ $key ] ); ?>"<?php echo $ctx['disabled']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<select data-f="<?php echo esc_attr( $meta['status'] ); ?>" aria-label="<?php echo esc_attr( $ctx['labels'][ $key ] . ' status' ); ?>"<?php echo $ctx['disabled']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<?php foreach ( $ctx['status_options'] as $value => $text ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>><?php echo esc_html( $text ); ?></option>
			<?php endforeach; ?>
		</select>
	</td>
	<?php
};
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
			<li><strong>Forces that share a department</strong> (set on the Forces screen) can each carry the same combined figures. National totals and averages count that shared figure once.</li>
			<li>On the second tab, if a force gave the <strong>backlog as percentages</strong>, set the unit to <em>Percentage</em> and type the percentages (for example 55). They are shown as given and never added into counts.</li>
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

	<nav class="nav-tab-wrapper gco-pd-gridtabs" role="tablist" aria-label="Figures">
		<a href="#gco-pd-grid-waits" class="nav-tab<?php echo 'waits' === $tab_open ? ' nav-tab-active' : ''; ?>" role="tab" data-gco-gridtab="waits">Waiting times &amp; holders<?php echo $errors_waits ? ' <span class="gco-pd-tabflag" title="Rows with problems">!</span>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
		<a href="#gco-pd-grid-extras" class="nav-tab<?php echo 'extras' === $tab_open ? ' nav-tab-active' : ''; ?>" role="tab" data-gco-gridtab="extras">Applications, backlog &amp; standards<?php echo $errors_extras ? ' <span class="gco-pd-tabflag" title="Rows with problems">!</span>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
	</nav>

	<div class="gco-pd-scroll" id="gco-pd-grid-waits" data-gco-grid="waits"<?php echo 'waits' === $tab_open ? '' : ' hidden'; ?>>
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
				<th class="gco-pd-sticky">
					<?php echo esc_html( $force->name ); ?><?php echo $force->is_active ? '' : ' <em>(inactive)</em>'; ?>
					<?php if ( ! empty( $force->shared_group ) ) : ?>
						<span class="gco-pd-shared" title="Shares a licensing department: <?php echo esc_attr( $force->shared_group ); ?>">shared</span>
					<?php endif; ?>
				</th>
				<td><input type="text" data-f="foi_ref" value="<?php echo esc_attr( $get( 'foi_ref' ) ); ?>" maxlength="80"<?php echo $disabled; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>></td>
				<td><input type="date" data-f="response_date" value="<?php echo esc_attr( $get( 'response_date' ) ); ?>"<?php echo $disabled; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>></td>
				<?php foreach ( GCO_PD_Validator::FOI_METRICS as $key => $meta ) : ?>
					<?php $status = $get( $meta['status'], 'not_provided' ); ?>
					<td class="gco-pd-metric<?php echo isset( $errors[ $meta['column'] ] ) ? ' is-bad' : ''; ?>">
						<input type="number" min="0" step="1" data-f="<?php echo esc_attr( $meta['column'] ); ?>" data-status="<?php echo esc_attr( $meta['status'] ); ?>" data-kind="<?php echo esc_attr( $meta['type'] ); ?>" value="<?php echo esc_attr( $get( $meta['column'] ) ); ?>" aria-label="<?php echo esc_attr( $force->name . ' ' . $labels[ $key ] ); ?>"<?php echo $disabled; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
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
					<?php foreach ( $errors as $gco_col => $message ) : ?>
						<?php if ( ! in_array( $gco_col, $extra_cols, true ) ) : ?>
							<div class="gco-pd-error"><?php echo esc_html( $message ); ?></div>
						<?php endif; ?>
					<?php endforeach; ?>
					<div class="gco-pd-live-error" hidden></div>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	</div>

	<div class="gco-pd-scroll" id="gco-pd-grid-extras" data-gco-grid="extras"<?php echo 'extras' === $tab_open ? '' : ' hidden'; ?>>
	<table class="widefat gco-pd-grid gco-pd-grid--extras">
		<thead>
			<tr>
				<th class="gco-pd-sticky">Force</th>
				<th><?php echo esc_html( $extra_labels['apps_received'] ); ?></th>
				<th><?php echo esc_html( $extra_labels['apps_completed'] ); ?></th>
				<th>Period covered <span class="description">(e.g. 12 months to 31/03/2026)</span></th>
				<th><?php echo esc_html( $extra_labels['outstanding'] ); ?></th>
				<th><?php echo esc_html( $extra_labels['oldest'] ); ?></th>
				<th>Backlog unit</th>
				<?php foreach ( GCO_PD_Validator::BACKLOG_BANDS as $band_label ) : ?>
					<th>Backlog: <?php echo esc_html( strtolower( $band_label ) ); ?></th>
				<?php endforeach; ?>
				<th>Backlog status</th>
				<th>Service standard (wording)</th>
				<th><?php echo esc_html( $extra_labels['standard_days'] ); ?></th>
				<th><?php echo esc_html( $extra_labels['standard_met'] ); ?></th>
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
			$ctx      = array(
				'get'            => $get,
				'errors'         => $errors,
				'labels'         => $extra_labels,
				'status_options' => $status_options,
				'disabled'       => $disabled,
			);
			$unit     = $get( 'backlog_unit', 'count' );
			$bstatus  = $get( 'backlog_status', 'not_provided' );
			$decimal  = GCO_PD_Validator::UNIT_PERCENT === $unit;
			$band_bad = false;
			foreach ( array_keys( GCO_PD_Validator::BACKLOG_BANDS ) as $band_column ) {
				$band_bad = $band_bad || isset( $errors[ $band_column ] );
			}
			?>
			<tr class="gco-pd-row<?php echo $errors ? ' has-server-error' : ''; ?>" data-force="<?php echo esc_attr( (string) $id ); ?>">
				<th class="gco-pd-sticky">
					<?php echo esc_html( $force->name ); ?>
					<?php if ( ! empty( $force->shared_group ) ) : ?>
						<span class="gco-pd-shared" title="Shares a licensing department: <?php echo esc_attr( $force->shared_group ); ?>">shared</span>
					<?php endif; ?>
				</th>
				<?php $gco_extra_cell( 'apps_received', $force, $ctx ); ?>
				<?php $gco_extra_cell( 'apps_completed', $force, $ctx ); ?>
				<td><input type="text" data-f="volumes_period" value="<?php echo esc_attr( $get( 'volumes_period' ) ); ?>" maxlength="120" aria-label="<?php echo esc_attr( $force->name . ' applications period' ); ?>"<?php echo $disabled; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>></td>
				<?php $gco_extra_cell( 'outstanding', $force, $ctx ); ?>
				<?php $gco_extra_cell( 'oldest', $force, $ctx ); ?>
				<td class="gco-pd-metric<?php echo isset( $errors['backlog_unit'] ) ? ' is-bad' : ''; ?>">
					<select data-f="backlog_unit" data-gco-unit aria-label="<?php echo esc_attr( $force->name . ' backlog unit' ); ?>"<?php echo $disabled; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<?php foreach ( $unit_options as $value => $text ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $unit, $value ); ?>><?php echo esc_html( $text ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
				<?php foreach ( GCO_PD_Validator::BACKLOG_BANDS as $band_column => $band_label ) : ?>
					<td class="gco-pd-metric<?php echo isset( $errors[ $band_column ] ) ? ' is-bad' : ''; ?>">
						<input type="number" min="0" step="<?php echo $decimal ? '0.01' : '1'; ?>" data-f="<?php echo esc_attr( $band_column ); ?>" data-group="backlog" data-kind="band" value="<?php echo esc_attr( $get( $band_column ) ); ?>" aria-label="<?php echo esc_attr( $force->name . ' backlog ' . strtolower( $band_label ) ); ?>"<?php echo $disabled; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					</td>
				<?php endforeach; ?>
				<td class="gco-pd-metric<?php echo isset( $errors['backlog_status'] ) ? ' is-bad' : ''; ?>">
					<select data-f="backlog_status" data-group-status="backlog" aria-label="<?php echo esc_attr( $force->name . ' backlog status' ); ?>"<?php echo $disabled; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<?php foreach ( $status_options as $value => $text ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $bstatus, $value ); ?>><?php echo esc_html( $text ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
				<td><input type="text" data-f="standard_text" value="<?php echo esc_attr( $get( 'standard_text' ) ); ?>" maxlength="255" aria-label="<?php echo esc_attr( $force->name . ' service standard wording' ); ?>"<?php echo $disabled; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>></td>
				<?php $gco_extra_cell( 'standard_days', $force, $ctx ); ?>
				<?php $gco_extra_cell( 'standard_met', $force, $ctx ); ?>
				<td class="gco-pd-msg">
					<?php foreach ( $errors as $gco_col => $message ) : ?>
						<?php if ( in_array( $gco_col, $extra_cols, true ) ) : ?>
							<div class="gco-pd-error"><?php echo esc_html( $message ); ?></div>
						<?php endif; ?>
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
