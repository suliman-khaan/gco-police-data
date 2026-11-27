<?php
/**
 * Setup: put the portal layout on a page, move force pages to the template, create missing force pages.
 * Everything here keeps the old content and can be restored.
 *
 * @var array[]  $rows       From GCO_PD_Setup::force_rows().
 * @var WP_Post[] $pages     Pages the portal can go on.
 * @var int      $portal_id  Current portal page.
 * @var array[]  $preview    Migration previews (nothing written).
 * @var string   $preview_of 'all', a force id, or ''.
 * @var bool     $has_layout The bundled portal layout file exists.
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;

$gco_count = array(
	'total'    => count( $rows ),
	'template' => 0,
	'old'      => 0,
	'none'     => 0,
	'blank'    => 0,
	'refresh'  => 0,
);
foreach ( $rows as $gco_row ) {
	if ( ! empty( $gco_row['refreshable'] ) ) {
		++$gco_count['refresh'];
	}
	if ( 'none' === $gco_row['state'] ) {
		++$gco_count['none'];
	} elseif ( $gco_row['blank'] ) {
		++$gco_count['blank'];
	} elseif ( $gco_row['needs'] ) {
		++$gco_count['old'];
	} else {
		++$gco_count['template'];
	}
}
$gco_action = esc_url( admin_url( 'admin-post.php' ) );
$gco_back   = esc_url( GCO_PD_Admin::url( GCO_PD_Admin::SLUG_SETUP ) );
?>
<h1>Setup</h1>
<p class="description">Put the new pages on the site without any command line. Nothing is deleted: the old content of every page is kept and each step has a Restore.</p>

<h2>1. Portal page</h2>
<div class="gco-pd-card-box">
	<?php if ( ! $has_layout ) : ?>
		<div class="notice notice-error inline"><p>The portal layout file is missing from the plugin (<code>includes/data/portal-page.txt</code>), so this cannot run. Re-upload the plugin.</p></div>
	<?php endif; ?>
	<p>The portal is the police licensing landing page (search, national figures, leaderboard, waiting times, guides, A&ndash;Z, FAQs). Choose the page it should live on. Its current content is stored first, and the page becomes the <em>Portal page</em> in <a href="<?php echo esc_url( GCO_PD_Admin::url( GCO_PD_Admin::SLUG_SETTINGS ) ); ?>">Settings</a>.</p>
	<form method="post" action="<?php echo $gco_action; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" onsubmit="return confirm('Put the portal layout on this page? Its current content is kept and can be restored.');">
		<?php wp_nonce_field( 'gco_pd_setup_portal' ); ?>
		<input type="hidden" name="action" value="gco_pd_setup_portal">
		<label for="gco-pd-setup-page" class="screen-reader-text">Page</label>
		<select name="page_id" id="gco-pd-setup-page">
			<option value="">Choose a page&hellip;</option>
			<?php foreach ( $pages as $gco_page ) : ?>
				<option value="<?php echo esc_attr( (string) $gco_page->ID ); ?>" <?php selected( $portal_id, (int) $gco_page->ID ); ?>>
					<?php echo esc_html( $gco_page->post_title . ( 'publish' === $gco_page->post_status ? '' : ' (' . $gco_page->post_status . ')' ) . ( '' === trim( $gco_page->post_content ) ? ' - empty' : '' ) ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<button type="submit" name="op" value="apply" class="button button-primary"<?php disabled( ! $has_layout ); ?>>Put the portal layout on this page</button>
		<button type="submit" name="op" value="restore" class="button" onclick="return confirm('Put the earlier content of this page back?');">Restore its earlier content</button>
	</form>
	<?php if ( $portal_id ) : ?>
		<p class="description">Current portal page: <a href="<?php echo esc_url( (string) get_permalink( $portal_id ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( get_the_title( $portal_id ) ); ?></a> &middot; <a href="<?php echo esc_url( (string) get_edit_post_link( $portal_id ) ); ?>">edit</a></p>
	<?php endif; ?>
	<form method="post" action="<?php echo $gco_action; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" style="margin-top:12px">
		<?php wp_nonce_field( 'gco_pd_setup_portal' ); ?>
		<input type="hidden" name="action" value="gco_pd_setup_portal">
		<input type="hidden" name="op" value="create">
		<input type="text" name="title" class="regular-text" placeholder="Police Firearms Licensing (new)" aria-label="Title of the new draft page">
		<button type="submit" class="button"<?php disabled( ! $has_layout ); ?>>Or create a new draft page with the layout</button>
		<span class="description">Leaves every existing page alone, so you can check it first.</span>
	</form>
	<p class="description">After applying: the four guide cards use page/post IDs. Open the page in WPBakery and check the &ldquo;Police guide cards&rdquo; IDs are guides that exist on this site (the ID is the number in a page's edit address, <code>post=1234</code>).</p>
</div>

<h2>2. Force pages</h2>
<div class="gco-pd-card-box">
	<p>
		<strong><?php echo esc_html( (string) $gco_count['template'] ); ?></strong> of <?php echo esc_html( (string) $gco_count['total'] ); ?> forces already use the template.
		<?php if ( $gco_count['old'] ) : ?>&middot; <strong><?php echo esc_html( (string) $gco_count['old'] ); ?></strong> have an older page that can be moved.<?php endif; ?>
		<?php if ( $gco_count['blank'] ) : ?>&middot; <strong><?php echo esc_html( (string) $gco_count['blank'] ); ?></strong> have an empty page.<?php endif; ?>
		<?php if ( $gco_count['none'] ) : ?>&middot; <strong><?php echo esc_html( (string) $gco_count['none'] ); ?></strong> have no page.<?php endif; ?>
	</p>
	<p class="description">Moving a page reads its written text (contact details, area text, GP providers, quotes, FAQs) into the force's content under <a href="<?php echo esc_url( GCO_PD_Admin::url( GCO_PD_Admin::SLUG_PAGES ) ); ?>">Force Pages</a> (only fields that are still empty), keeps the old page content, and replaces the page with the template. Preview first: it shows what would be read and changes nothing.</p>
	<p>
		<?php if ( $gco_count['old'] ) : ?>
			<a class="button" href="<?php echo esc_url( GCO_PD_Admin::url( GCO_PD_Admin::SLUG_SETUP, array( 'preview' => 'all' ) ) ); ?>">Preview moving all (<?php echo esc_html( (string) $gco_count['old'] ); ?>)</a>
			<form method="post" action="<?php echo $gco_action; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" style="display:inline" onsubmit="return confirm('Move every older force page to the template? Each page keeps its old content and can be restored.');">
				<?php wp_nonce_field( 'gco_pd_setup_migrate' ); ?>
				<input type="hidden" name="action" value="gco_pd_setup_migrate">
				<input type="hidden" name="scope" value="all">
				<button type="submit" class="button button-primary">Move all to the template</button>
			</form>
		<?php endif; ?>
		<?php if ( $gco_count['refresh'] ) : ?>
			<form method="post" action="<?php echo $gco_action; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" style="display:inline" onsubmit="return confirm('Read each moved page&#39;s stored original again and fill only the fields that are still empty?');">
				<?php wp_nonce_field( 'gco_pd_setup_migrate' ); ?>
				<input type="hidden" name="action" value="gco_pd_setup_migrate">
				<input type="hidden" name="scope" value="all">
				<input type="hidden" name="mode" value="refresh">
				<button type="submit" class="button">Refresh moved pages from their originals (<?php echo esc_html( (string) $gco_count['refresh'] ); ?>)</button>
			</form>
		<?php endif; ?>
		<?php if ( $gco_count['none'] || $gco_count['blank'] ) : ?>
			<form method="post" action="<?php echo $gco_action; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" style="display:inline" onsubmit="return confirm('Create a draft page for each force that has none, and put the template on empty pages?');">
				<?php wp_nonce_field( 'gco_pd_setup_pages' ); ?>
				<input type="hidden" name="action" value="gco_pd_setup_pages">
				<button type="submit" class="button">Create missing pages / fill empty pages</button>
			</form>
		<?php endif; ?>
	</p>

	<?php if ( '' !== $preview_of ) : ?>
		<div class="notice notice-info inline">
			<p><strong>Preview: nothing has been changed.</strong> <a href="<?php echo $gco_back; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">Close preview</a></p>
			<?php if ( ! $preview ) : ?>
				<p>Nothing to move for this selection.</p>
			<?php endif; ?>
			<?php foreach ( $preview as $gco_report ) : ?>
				<p>
					<strong><?php echo esc_html( $gco_report['force']->name ); ?></strong>
					&mdash; <?php echo 'skipped' === $gco_report['status'] ? esc_html( implode( ' ', $gco_report['messages'] ) ) : ''; ?>
					<?php if ( 'skipped' !== $gco_report['status'] ) : ?>
						<br>Found: <?php echo esc_html( $gco_report['found'] ? implode( ', ', $gco_report['found'] ) : 'nothing' ); ?>
						<br>Would fill: <?php echo esc_html( $gco_report['filling'] ? implode( ', ', $gco_report['filling'] ) : 'nothing new' ); ?>
						<?php if ( $gco_report['replaced'] ) : ?><br>Replaced by data: <?php echo esc_html( implode( '; ', $gco_report['replaced'] ) ); ?><?php endif; ?>
						<?php if ( $gco_report['unknown'] ) : ?><br><em>Not recognised, check by hand: <?php echo esc_html( implode( '; ', $gco_report['unknown'] ) ); ?></em><?php endif; ?>
						<?php foreach ( $gco_report['messages'] as $gco_message ) : ?><br><?php echo esc_html( $gco_message ); ?><?php endforeach; ?>
					<?php endif; ?>
				</p>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<table class="widefat striped gco-pd-table">
		<thead><tr><th>Force</th><th>Page</th><th>Status</th><th>Content</th><th></th></tr></thead>
		<tbody>
		<?php foreach ( $rows as $gco_row ) : ?>
			<?php $gco_force = $gco_row['force']; ?>
			<tr>
				<td><strong><?php echo esc_html( $gco_force->name ); ?></strong></td>
				<td>
					<?php if ( $gco_row['page_id'] ) : ?>
						<a href="<?php echo esc_url( (string) get_edit_post_link( $gco_row['page_id'] ) ); ?>"><?php echo esc_html( get_the_title( $gco_row['page_id'] ) ); ?></a>
						<?php if ( 'publish' !== $gco_row['status'] ) : ?><span class="gco-pd-badge gco-pd-badge--draft"><?php echo esc_html( $gco_row['status'] ); ?></span><?php endif; ?>
					<?php else : ?>
						<span class="gco-pd-no">No page</span>
					<?php endif; ?>
				</td>
				<td>
					<?php if ( 'none' === $gco_row['state'] ) : ?>
						&ndash;
					<?php elseif ( $gco_row['blank'] ) : ?>
						<span class="gco-pd-badge">Empty page</span>
					<?php elseif ( $gco_row['needs'] ) : ?>
						<span class="gco-pd-badge">Older page</span>
					<?php else : ?>
						<span class="gco-pd-badge gco-pd-badge--published">Template</span>
					<?php endif; ?>
				</td>
				<td>
					<?php
					$gco_filled = array_filter(
						array(
							$gco_row['filled']['contact'] ? 'contact' : '',
							$gco_row['filled']['area'] ? 'area' : '',
							$gco_row['filled']['gp'] ? 'GP' : '',
							$gco_row['filled']['quotes'] ? $gco_row['filled']['quotes'] . ' quotes' : '',
							$gco_row['filled']['faqs'] ? $gco_row['filled']['faqs'] . ' FAQs' : '',
						)
					);
					echo $gco_filled ? esc_html( implode( ', ', $gco_filled ) ) : '<span class="gco-pd-no">&ndash;</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
				</td>
				<td>
					<?php if ( $gco_row['needs'] ) : ?>
						<a href="<?php echo esc_url( GCO_PD_Admin::url( GCO_PD_Admin::SLUG_SETUP, array( 'preview' => (int) $gco_force->id ) ) ); ?>">Preview</a>
						<form method="post" action="<?php echo $gco_action; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" style="display:inline">
							<?php wp_nonce_field( 'gco_pd_setup_migrate' ); ?>
							<input type="hidden" name="action" value="gco_pd_setup_migrate">
							<input type="hidden" name="scope" value="<?php echo esc_attr( (string) $gco_force->id ); ?>">
							&middot; <button type="submit" class="button-link">Move to template</button>
						</form>
					<?php endif; ?>
					<?php if ( ! empty( $gco_row['refreshable'] ) ) : ?>
						<a href="<?php echo esc_url( GCO_PD_Admin::url( GCO_PD_Admin::SLUG_SETUP, array( 'preview' => (int) $gco_force->id ) ) ); ?>">Preview refresh</a>
						<form method="post" action="<?php echo $gco_action; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" style="display:inline">
							<?php wp_nonce_field( 'gco_pd_setup_migrate' ); ?>
							<input type="hidden" name="action" value="gco_pd_setup_migrate">
							<input type="hidden" name="scope" value="<?php echo esc_attr( (string) $gco_force->id ); ?>">
							<input type="hidden" name="mode" value="refresh">
							&middot; <button type="submit" class="button-link">Refresh from original</button>
						</form>
					<?php endif; ?>
					<?php if ( $gco_row['backup'] ) : ?>
						<form method="post" action="<?php echo $gco_action; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" style="display:inline" onsubmit="return confirm('Put this page\'s original content back?');">
							<?php wp_nonce_field( 'gco_pd_setup_restore' ); ?>
							<input type="hidden" name="action" value="gco_pd_setup_restore">
							<input type="hidden" name="force_id" value="<?php echo esc_attr( (string) $gco_force->id ); ?>">
							<button type="submit" class="button-link">Restore original</button>
						</form>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
