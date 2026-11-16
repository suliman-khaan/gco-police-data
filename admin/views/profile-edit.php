<?php
/**
 * Edit one force's page content, in tabs. One form, one save.
 *
 * @var object $force   Force row.
 * @var array  $profile Profile values.
 * @var array  $fields  Field definitions.
 * @var array  $tabs    Tab label => field keys.
 * @var array  $shared  Shared defaults (shown as hints).
 *
 * @package GCO_Police_Data
 */

defined( 'ABSPATH' ) || exit;

$gco_page_id  = (int) $force->page_id;
$gco_active   = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$gco_tab_keys = array_map( 'sanitize_title', array_keys( $tabs ) );
if ( ! in_array( $gco_active, $gco_tab_keys, true ) ) {
	$gco_active = $gco_tab_keys[0];
}
?>
<h1 class="wp-heading-inline"><?php echo esc_html( $force->name ); ?>: force page</h1>
<?php if ( $gco_page_id && get_post( $gco_page_id ) ) : ?>
	<a class="page-title-action" href="<?php echo esc_url( get_permalink( $gco_page_id ) ); ?>" target="_blank" rel="noopener">View page</a>
	<a class="page-title-action" href="<?php echo esc_url( get_edit_post_link( $gco_page_id ) ); ?>">Edit page in WordPress</a>
<?php endif; ?>
<hr class="wp-header-end">
<p><a href="<?php echo esc_url( GCO_PD_Admin::url( GCO_PD_Admin::SLUG_PAGES ) ); ?>">&larr; All force pages</a></p>

<?php if ( ! $gco_page_id ) : ?>
	<div class="notice notice-warning inline"><p>No page is linked to this force yet. You can still fill in its content; link a page under <a href="<?php echo esc_url( GCO_PD_Admin::url( GCO_PD_Admin::SLUG_FORCES ) ); ?>">Forces</a> and add the <code>[gco_force_page]</code> shortcode to it.</p></div>
<?php endif; ?>

<p class="gco-pd-help-inline">Figures (waiting times, rank, holders, charts) fill in automatically from published FOI rounds and NPCC quarters. This screen holds the written content. Empty sections are hidden on the page.</p>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gco-pd-profile" data-gco-tabs-admin>
	<input type="hidden" name="action" value="gco_pd_save_profile">
	<input type="hidden" name="force_id" value="<?php echo esc_attr( (string) (int) $force->id ); ?>">
	<input type="hidden" name="tab" value="<?php echo esc_attr( $gco_active ); ?>" data-gco-tab-input>
	<?php wp_nonce_field( 'gco_pd_save_profile' ); ?>

	<nav class="nav-tab-wrapper" role="tablist">
		<?php foreach ( $tabs as $gco_label => $gco_keys ) : ?>
			<?php $gco_slug = sanitize_title( $gco_label ); ?>
			<a href="#gco-tab-<?php echo esc_attr( $gco_slug ); ?>" class="nav-tab<?php echo $gco_slug === $gco_active ? ' nav-tab-active' : ''; ?>" role="tab" data-gco-tab="<?php echo esc_attr( $gco_slug ); ?>"><?php echo esc_html( $gco_label ); ?></a>
		<?php endforeach; ?>
	</nav>

	<?php foreach ( $tabs as $gco_label => $gco_keys ) : ?>
		<?php $gco_slug = sanitize_title( $gco_label ); ?>
		<div class="gco-pd-tabpanel" id="gco-tab-<?php echo esc_attr( $gco_slug ); ?>" data-gco-panel="<?php echo esc_attr( $gco_slug ); ?>"<?php echo $gco_slug === $gco_active ? '' : ' hidden'; ?>>
			<?php if ( 'gp-medical' === $gco_slug ) : ?>
				<p class="description">The shared GP checklist and "More about medical requirements" link are set under Force Pages &rarr; Shared defaults and appear on every force page.</p>
			<?php endif; ?>
			<?php foreach ( $gco_keys as $gco_key ) : ?>
				<?php
				$gco_field_name  = 'profile[' . $gco_key . ']';
				$gco_field_id    = 'gco-profile-' . $gco_key;
				$gco_field       = $fields[ $gco_key ];
				$gco_field_value = $profile[ $gco_key ];
				$gco_field_rich  = true;
				include __DIR__ . '/part-field.php';
				?>
			<?php endforeach; ?>
		</div>
	<?php endforeach; ?>

	<div class="gco-pd-savebar"><?php submit_button( 'Save force page', 'primary', 'submit', false ); ?></div>
</form>
