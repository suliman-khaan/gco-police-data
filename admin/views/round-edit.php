<?php
/**
 * Round editor: one row per force. Two tabs share one form and one Save button:
 *  1. Waiting times and holders (value + status per metric)
 *  2. Applications, backlog, service standards and how to apply (the extra figures)
 *  3. Figures by application type (received, completed, outstanding for each of the five types)
 *
 * @var object   $round     Round.
 * @var object[] $forces    Forces to list.
 * @var array    $responses Force id => response row.
 * @var array    $types     Force id => app type => type row.
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
	'std_grant_days'     => 'New grants: target (days)',
	'std_grant_pct'      => 'New grants: within target (%)',
	'std_renew_days'     => 'Renewals: target (days)',
	'std_renew_pct'      => 'Renewals: within target (%)',
	'std_variation_days' => 'Variations: target (days)',
	'std_variation_pct'  => 'Variations: within target (%)',
);
$yesno_labels = array(
	'online_grant'     => 'Online: new grants',
	'online_renew'     => 'Online: renewals',
	'online_variation' => 'Online: variations',
	'post_required'    => 'Post still required',
);
$yesno_options = array(
	''    => '—',
	'yes' => 'Yes',
	'no'  => 'No',
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
$extra_cols = array( 'backlog_unit', 'backlog_status', 'volumes_period' );
foreach ( GCO_PD_Validator::FOI_EXTRA_METRICS as $gco_meta ) {
	$extra_cols[] = $gco_meta['column'];
}
$extra_cols    = array_merge( $extra_cols, array_keys( GCO_PD_Validator::BACKLOG_BANDS ), GCO_PD_Validator::YESNO_COLUMNS );
$errors_waits  = 0;
$errors_extras = 0;
$errors_types  = 0;
foreach ( (array) ( $failed['errors'] ?? array() ) as $gco_row_errors ) {
	foreach ( array_keys( (array) $gco_row_errors ) as $gco_col ) {
		if ( 0 === strpos( (string) $gco_col, 'types.' ) ) {
			++$errors_types;
		} elseif ( in_array( $gco_col, $extra_cols, true ) ) {
			++$errors_extras;
		} else {
			++$errors_waits;
		}
	}
}
$tab_open = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'waits'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$tab_open = in_array( $tab_open, array( 'waits', 'extras', 'types' ), true ) ? $tab_open : 'waits';
if ( ! $errors_waits ) {
	if ( $errors_extras ) {
		$tab_open = 'extras';
	} elseif ( $errors_types ) {
		$tab_open = 'types';
	}
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
	if ( ! empty( $meta['implicit'] ) ) {
		// A number means Actual, blank means Not provided: no status column.
		?>
		<td class="gco-pd-metric<?php echo isset( $ctx['errors'][ $meta['column'] ] ) ? ' is-bad' : ''; ?>">
			<input type="number" min="0" <?php echo 'percent' === $kind ? 'max="100" step="0.01"' : 'step="1"'; ?> data-f="<?php echo esc_attr( $meta['column'] ); ?>" data-kind="<?php echo esc_attr( $kind ); ?>" value="<?php echo esc_attr( $ctx['get']( $meta['column'] ) ); ?>" aria-label="<?php echo esc_attr( $force->name . ' ' . $ctx['labels'][ $key ] ); ?>"<?php echo $ctx['disabled']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		</td>
		<?php
		return;
	}
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
		<a href="#gco-pd-grid-types" class="nav-tab<?php echo 'types' === $tab_open ? ' nav-tab-active' : ''; ?>" role="tab" data-gco-gridtab="types">By application type<?php echo $errors_types ? ' <span class="gco-pd-tabflag" title="Rows with problems">!</span>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
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
						<?php if ( ! in_array( $gco_col, $extra_cols, true ) && 0 !== strpos( (string) $gco_col, 'types.' ) ) : ?>
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
				<?php foreach ( array( 'std_grant_days', 'std_grant_pct', 'std_renew_days', 'std_renew_pct', 'std_variation_days', 'std_variation_pct' ) as $gco_std ) : ?>
					<th><?php echo esc_html( $extra_labels[ $gco_std ] ); ?></th>
				<?php endforeach; ?>
				<?php foreach ( $yesno_labels as $gco_yn_label ) : ?>
					<th><?php echo esc_html( $gco_yn_label ); ?></th>
				<?php endforeach; ?>
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
				<?php foreach ( array( 'std_grant_days', 'std_grant_pct', 'std_renew_days', 'std_renew_pct', 'std_variation_days', 'std_variation_pct' ) as $gco_std ) : ?>
					<?php $gco_extra_cell( $gco_std, $force, $ctx ); ?>
				<?php endforeach; ?>
				<?php foreach ( $yesno_labels as $gco_yn_col => $gco_yn_label ) : ?>
					<td class="gco-pd-metric<?php echo isset( $errors[ $gco_yn_col ] ) ? ' is-bad' : ''; ?>">
						<select data-f="<?php echo esc_attr( $gco_yn_col ); ?>" aria-label="<?php echo esc_attr( $force->name . ' ' . $gco_yn_label ); ?>"<?php echo $disabled; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
							<?php foreach ( $yesno_options as $gco_value => $gco_text ) : ?>
								<option value="<?php echo esc_attr( $gco_value ); ?>" <?php selected( $get( $gco_yn_col, '' ), $gco_value ); ?>><?php echo esc_html( $gco_text ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				<?php endforeach; ?>
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

	<div class="gco-pd-scroll" id="gco-pd-grid-types" data-gco-grid="types"<?php echo 'types' === $tab_open ? '' : ' hidden'; ?>>
	<table class="widefat gco-pd-grid gco-pd-grid--types">
		<thead>
			<tr>
				<th class="gco-pd-sticky">Force</th>
				<th>Application type</th>
				<th>Received</th>
				<th>Completed</th>
				<th>Outstanding</th>
				<th>Problems</th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $forces as $force ) : ?>
			<?php
			$id      = (int) $force->id;
			$errors  = $failed['errors'][ $id ] ?? array();
			$typed   = $failed['rows'][ $id ]['types'] ?? null;
			$stored  = $types[ $id ] ?? array();
			$first   = true;
			?>
			<?php foreach ( GCO_PD_Validator::APP_TYPES as $gco_type => $gco_type_label ) : ?>
				<?php
				$gco_get = static function ( $field, $default = '' ) use ( $typed, $stored, $gco_type ) {
					if ( null !== $typed ) {
						return (string) ( $typed[ $gco_type ][ $field ] ?? $default );
					}
					if ( isset( $stored[ $gco_type ]->$field ) && null !== $stored[ $gco_type ]->$field ) {
						return (string) $stored[ $gco_type ]->$field;
					}
					return $default;
				};
				$gco_prefix = 'types.' . $gco_type . '.';
				?>
				<tr class="gco-pd-row gco-pd-typerow<?php echo $first ? ' gco-pd-typerow--first' : ''; ?>" data-force="<?php echo esc_attr( (string) $id ); ?>" data-type="<?php echo esc_attr( $gco_type ); ?>">
					<th class="gco-pd-sticky">
						<?php if ( $first ) : ?>
							<?php echo esc_html( $force->name ); ?><?php echo $force->is_active ? '' : ' <em>(inactive)</em>'; ?>
							<?php if ( ! empty( $force->shared_group ) ) : ?>
								<span class="gco-pd-shared" title="Shares a licensing department: <?php echo esc_attr( $force->shared_group ); ?>">shared</span>
							<?php endif; ?>
						<?php else : ?>
							<span class="gco-pd-sr"><?php echo esc_html( $force->name ); ?></span>
						<?php endif; ?>
					</th>
					<td><?php echo esc_html( $gco_type_label ); ?></td>
					<?php foreach ( array( 'received', 'completed', 'outstanding' ) as $gco_col ) : ?>
						<td class="gco-pd-metric<?php echo isset( $errors[ $gco_prefix . $gco_col ] ) ? ' is-bad' : ''; ?>">
							<input type="number" min="0" step="1" data-f="<?php echo esc_attr( $gco_col ); ?>" data-status="<?php echo esc_attr( $gco_col . '_status' ); ?>" data-kind="count" value="<?php echo esc_attr( $gco_get( $gco_col ) ); ?>" aria-label="<?php echo esc_attr( $force->name . ' ' . $gco_type_label . ' ' . $gco_col ); ?>"<?php echo $disabled; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
							<select data-f="<?php echo esc_attr( $gco_col . '_status' ); ?>" aria-label="<?php echo esc_attr( $gco_type_label . ' ' . $gco_col . ' status' ); ?>"<?php echo $disabled; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
								<?php foreach ( $status_options as $value => $text ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $gco_get( $gco_col . '_status', 'not_provided' ), $value ); ?>><?php echo esc_html( $text ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					<?php endforeach; ?>
					<td class="gco-pd-msg">
						<?php foreach ( $errors as $gco_key => $message ) : ?>
							<?php if ( 0 === strpos( (string) $gco_key, $gco_prefix ) ) : ?>
								<div class="gco-pd-error"><?php echo esc_html( $message ); ?></div>
							<?php endif; ?>
						<?php endforeach; ?>
						<div class="gco-pd-live-error" hidden></div>
					</td>
				</tr>
				<?php $first = false; ?>
			<?php endforeach; ?>
		<?php endforeach; ?>
		</tbody>
	</table>
	</div>

	<?php if ( ! $is_published ) : ?>
		<p class="submit"><button type="submit" class="button button-primary button-large">Save figures</button></p>
	<?php endif; ?>
</form>
