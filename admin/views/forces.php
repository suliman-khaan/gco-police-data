<?php
/**
 * Forces list.
 *
 * @var object[] $forces All forces.
 * @var object[] $pages  Published pages for the link dropdown.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;
?>
<h1>Forces</h1>
<p>The 43 territorial forces. <strong>Aliases</strong> (comma separated) are other names used when importing and in the site search, such as <code>GMP, Greater Manchester</code>. <strong>Page</strong> is the force page on this site that shows this force's figures.</p>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="gco_pd_save_forces">
	<?php wp_nonce_field( 'gco_pd_save_forces' ); ?>
	<table class="widefat striped gco-pd-table">
		<thead>
			<tr><th>Name</th><th>Slug</th><th>Aliases</th><th>Page</th><th>Active</th></tr>
		</thead>
		<tbody>
		<?php foreach ( $forces as $force ) : ?>
			<?php $id = (int) $force->id; ?>
			<tr>
				<td><input type="text" class="regular-text" name="force[<?php echo esc_attr( (string) $id ); ?>][name]" value="<?php echo esc_attr( $force->name ); ?>" maxlength="120" required></td>
				<td><code><?php echo esc_html( $force->slug ); ?></code></td>
				<td><input type="text" class="regular-text" name="force[<?php echo esc_attr( (string) $id ); ?>][aliases]" value="<?php echo esc_attr( (string) $force->aliases ); ?>" maxlength="255"></td>
				<td>
					<select name="force[<?php echo esc_attr( (string) $id ); ?>][page_id]">
						<option value="0">&mdash; not linked &mdash;</option>
						<?php foreach ( $pages as $page ) : ?>
							<option value="<?php echo esc_attr( (string) $page->ID ); ?>" <?php selected( (int) $force->page_id, (int) $page->ID ); ?>><?php echo esc_html( $page->post_title ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
				<td><input type="checkbox" name="force[<?php echo esc_attr( (string) $id ); ?>][is_active]" value="1" <?php checked( (int) $force->is_active, 1 ); ?>></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php submit_button( 'Save forces' ); ?>
</form>
