<?php
/**
 * Runs when the plugin is deleted from wp-admin (not on deactivation).
 *
 * Default: removes caches only. Community content (projects, portfolios)
 * is member data, so it's deleted only if an admin explicitly enabled
 * "Delete all Aflanex Community data when the plugin is deleted" under
 * Settings → Aflanex Community. FluentCommunity data is never touched.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Always: transient caches (release info, OIDC discovery/JWKS, form state).
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_aflx\\_%' OR option_name LIKE '\\_transient\\_timeout\\_aflx\\_%' OR option_name LIKE '\\_site\\_transient\\_aflx\\_%' OR option_name LIKE '\\_site\\_transient\\_timeout\\_aflx\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

$aflx_settings = get_option( 'aflx_settings', [] );
if ( empty( $aflx_settings['delete_data_on_uninstall'] ) ) {
	return;
}

// Projects (and the images uploaded to them).
$aflx_projects = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", 'aflx_project' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
foreach ( $aflx_projects as $aflx_project_id ) {
	foreach ( get_children( [ 'post_parent' => (int) $aflx_project_id, 'post_type' => 'attachment', 'fields' => 'ids' ] ) as $aflx_attachment_id ) {
		wp_delete_attachment( (int) $aflx_attachment_id, true );
	}
	wp_delete_post( (int) $aflx_project_id, true );
}

// Focus areas and skills. The plugin isn't loaded here, so register the taxonomies just long enough to delete terms.
foreach ( [ 'aflx_area', 'aflx_skill' ] as $aflx_taxonomy ) {
	register_taxonomy( $aflx_taxonomy, 'aflx_project' );
	$aflx_terms = get_terms( [ 'taxonomy' => $aflx_taxonomy, 'hide_empty' => false, 'fields' => 'ids' ] );
	if ( ! is_wp_error( $aflx_terms ) ) {
		foreach ( $aflx_terms as $aflx_term_id ) {
			wp_delete_term( (int) $aflx_term_id, $aflx_taxonomy );
		}
	}
}

// The plugin's pages.
$aflx_pages = get_option( 'aflx_pages', [] );
if ( is_array( $aflx_pages ) ) {
	foreach ( $aflx_pages as $aflx_page_id ) {
		wp_delete_post( (int) $aflx_page_id, true );
	}
}

// Portfolio details and identity links.
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'aflx\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

// Plugin options.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'aflx\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

wp_cache_flush();
