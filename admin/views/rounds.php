<?php
/**
 * FOI rounds list.
 *
 * @var object[] $rounds Rounds, newest first.
 * @var array    $stats  Round id => coverage summary.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;
?>
<h1 class="wp-heading-inline">FOI Rounds</h1>
<hr class="wp-header-end">
<p>One round is one batch of FOI responses (about every six months). Work on a <strong>draft</strong>; nothing appears on the site until you <strong>publish</strong> it.</p>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gco-pd-inline-form">
	<input type="hidden" name="action" value="gco_pd_create_round">
	<?php wp_nonce_field( 'gco_pd_create_round' ); ?>
	<label for="gco-pd-round-label"><strong>New round</strong> (year-month, e.g. 2026-11)</label>
	<input type="text" id="gco-pd-round-label" name="label" placeholder="2026-11" pattern="\d{4}-(0[1-9]|1[0-2])" maxlength="7" size="9" required>
	<button class="button button-primary">Create draft round</button>
</form>

<table class="widefat striped gco-pd-table">
	<thead>
		<tr>
			<th>Round</th><th>Status</th><th>Forces with data</th><th>Figures not provided</th><th>Published</th><th>Actions</th>
		</tr>
	</thead>
	<tbody>
	<?php if ( ! $rounds ) : ?>
		<tr><td colspan="6">No rounds yet. Create one above, or use <a href="<?php echo esc_url( GCO_PD_Admin::url( GCO_PD_Admin::SLUG_IMPORT ) ); ?>">Import</a>.</td></tr>
	<?php endif; ?>
	<?php foreach ( $rounds as $round ) : ?>
		<?php
		$summary      = $stats[ (int) $round->id ];
		$is_published = 'published' === $round->status;
		?>
		<tr>
			<td><strong><a href="<?php echo esc_url( GCO_PD_Admin::url( GCO_PD_Admin::SLUG_ROUND, array( 'round' => (int) $round->id ) ) ); ?>"><?php echo esc_html( $round->label ); ?></a></strong></td>
			<td><span class="gco-pd-badge gco-pd-badge--<?php echo esc_attr( $round->status ); ?>"><?php echo esc_html( ucfirst( $round->status ) ); ?></span></td>
			<td><?php echo esc_html( $summary['forces_with_data'] . ' of ' . $summary['total_forces'] ); ?></td>
			<td><?php echo esc_html( (string) $summary['not_provided'] ); ?></td>
			<td><?php echo $round->published_at ? esc_html( get_date_from_gmt( $round->published_at, 'j M Y' ) ) : '&mdash;'; ?></td>
			<td class="gco-pd-actions">
				<a class="button button-small" href="<?php echo esc_url( GCO_PD_Admin::url( GCO_PD_Admin::SLUG_ROUND, array( 'round' => (int) $round->id ) ) ); ?>"><?php echo $is_published ? 'View' : 'Edit figures'; ?></a>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="gco_pd_round_status">
					<input type="hidden" name="id" value="<?php echo esc_attr( (string) $round->id ); ?>">
					<input type="hidden" name="to" value="<?php echo $is_published ? 'unpublish' : 'publish'; ?>">
					<?php wp_nonce_field( 'gco_pd_round_status' ); ?>
					<button class="button button-small <?php echo $is_published ? '' : 'button-primary'; ?>"><?php echo $is_published ? 'Unpublish' : 'Publish'; ?></button>
				</form>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="gco_pd_export_foi">
					<input type="hidden" name="id" value="<?php echo esc_attr( (string) $round->id ); ?>">
					<?php wp_nonce_field( 'gco_pd_export_foi' ); ?>
					<button class="button button-small">Download CSV</button>
				</form>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="gco_pd_export_types">
					<input type="hidden" name="id" value="<?php echo esc_attr( (string) $round->id ); ?>">
					<?php wp_nonce_field( 'gco_pd_export_types' ); ?>
					<button class="button button-small" title="One row per force per application type">Download types CSV</button>
				</form>

				<?php if ( ! $is_published ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Delete draft round <?php echo esc_js( $round->label ); ?> and all its figures? This cannot be undone.');">
					<input type="hidden" name="action" value="gco_pd_delete_round">
					<input type="hidden" name="id" value="<?php echo esc_attr( (string) $round->id ); ?>">
					<?php wp_nonce_field( 'gco_pd_delete_round' ); ?>
					<button class="button button-small button-link-delete">Delete</button>
				</form>
				<?php endif; ?>
			</td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>

<?php if ( $rounds ) : ?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gco-pd-inline-form">
	<input type="hidden" name="action" value="gco_pd_export_foi">
	<input type="hidden" name="id" value="0">
	<?php wp_nonce_field( 'gco_pd_export_foi' ); ?>
	<button class="button">Download all rounds (CSV backup)</button>
	<span class="description">Same columns as the upload template, so it can be imported again.</span>
</form>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gco-pd-inline-form">
	<input type="hidden" name="action" value="gco_pd_export_types">
	<input type="hidden" name="id" value="0">
	<?php wp_nonce_field( 'gco_pd_export_types' ); ?>
	<button class="button">Download all application types (CSV backup)</button>
	<span class="description">The "FOI Application Types" sheet: one row per force per application type.</span>
</form>
<?php endif; ?>
