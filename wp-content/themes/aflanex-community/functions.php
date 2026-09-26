<?php
/**
 * Aflanex Community child theme.
 *
 * Presentation only: design tokens, component styles, the portal skin,
 * the brand mark and the public entry page. Community logic (projects,
 * member profiles, integrations, identity) lives in the Aflanex Community
 * plugin, and this theme degrades gracefully when the plugin is inactive.
 */

defined( 'ABSPATH' ) || exit;

const AFLANEX_THEME_VERSION = '1.0.0';

/**
 * Brand data for templates. The plugin owns the canonical values (and the
 * logo-file detection); this fallback keeps the theme renderable alone.
 */
function aflanex_theme_brand(): array {
	if ( class_exists( '\Aflanex\Community\Brand\Brand' ) ) {
		return \Aflanex\Community\Brand\Brand::get();
	}

	return [
		'name'    => 'Aflanex',
		'product' => 'Community',
		'tagline' => 'Build what’s next together.',
		'logo'    => '',
	];
}

/**
 * Render the brand mark component. Always call this; never inline a logo.
 */
function aflanex_brand_mark( array $args = [] ): void {
	get_template_part( 'template-parts/brand-mark', null, $args );
}

function aflanex_theme_asset( string $path ): string {
	return get_stylesheet_directory_uri() . '/assets/' . ltrim( $path, '/' );
}

function aflanex_theme_asset_version( string $path ): string {
	$file = get_stylesheet_directory() . '/assets/' . ltrim( $path, '/' );
	return file_exists( $file ) ? AFLANEX_THEME_VERSION . '.' . filemtime( $file ) : AFLANEX_THEME_VERSION;
}

/**
 * Front-end styles for WordPress-rendered pages (entry page, frame pages).
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_style( 'aflanex-tokens', aflanex_theme_asset( 'css/tokens.css' ), [], aflanex_theme_asset_version( 'css/tokens.css' ) );
		wp_enqueue_style( 'aflanex-components', aflanex_theme_asset( 'css/components.css' ), [ 'aflanex-tokens' ], aflanex_theme_asset_version( 'css/components.css' ) );

		if ( is_front_page() ) {
			wp_enqueue_style( 'aflanex-landing', aflanex_theme_asset( 'css/landing.css' ), [ 'aflanex-components' ], aflanex_theme_asset_version( 'css/landing.css' ) );
		}
	},
	20
);

/**
 * The public entry page is fully custom and needs none of the site-wide
 * plugin assets (FluentCart, Elementor + Google Fonts, Fluent Forms, block
 * library). Allow-list our own styles there: faster first paint and no
 * third-party font requests for first-time visitors.
 */
function aflanex_entry_page_asset_allowlist(): void {
	if ( ! is_front_page() ) {
		return;
	}
	foreach ( wp_styles()->queue as $handle ) {
		if ( ! str_starts_with( $handle, 'aflanex-' ) && ! in_array( $handle, [ 'admin-bar', 'dashicons' ], true ) ) {
			wp_dequeue_style( $handle );
		}
	}
	foreach ( wp_scripts()->queue as $handle ) {
		if ( 'admin-bar' !== $handle ) {
			wp_dequeue_script( $handle );
		}
	}
	wp_dequeue_style( 'global-styles' );
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
}
add_action( 'wp_enqueue_scripts', 'aflanex_entry_page_asset_allowlist', 9999 );
// Some plugins enqueue late (footer); catch those right before late styles print.
add_action( 'wp_print_footer_scripts', 'aflanex_entry_page_asset_allowlist', 1 );
// Elementor prints Google Fonts through its own path; it offers a filter for that.
add_filter( 'elementor/frontend/print_google_fonts', static fn( $print ) => is_front_page() ? false : $print );

/**
 * Frame pages (FluentCommunity header + sidebar around WordPress content)
 * also need the portal skin so both surfaces look identical.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( in_array( 'fluent_com_wp_pages', get_body_class(), true ) ) {
			wp_enqueue_style( 'aflanex-portal', aflanex_theme_asset( 'css/portal.css' ), [ 'aflanex-components' ], aflanex_theme_asset_version( 'css/portal.css' ) );
		}
	},
	30
);

/**
 * The FluentCommunity portal (Vue SPA) prints its own <head>. Inject the
 * same token + skin stylesheets there. Works for classic and headless modes.
 */
function aflanex_print_portal_styles(): void {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;

	foreach ( [ 'tokens', 'components', 'portal' ] as $sheet ) {
		printf(
			'<link rel="stylesheet" id="aflanex-%1$s-css" href="%2$s" media="all" />' . "\n",
			esc_attr( $sheet ),
			esc_url( add_query_arg( 'ver', aflanex_theme_asset_version( "css/{$sheet}.css" ), aflanex_theme_asset( "css/{$sheet}.css" ) ) )
		);
	}
}
add_action( 'fluent_community/portal_head', 'aflanex_print_portal_styles', 99 );
add_action( 'fluent_community/headless/head', 'aflanex_print_portal_styles', 99 );

/**
 * The portal header uses the same brand-mark component as every other
 * surface (FluentCommunity's own logo slot is hidden in portal.css).
 * `fcom_route` lets the SPA handle the click without a full reload.
 */
add_action(
	'fluent_community/before_header_logo',
	function () {
		$portal = class_exists( '\FluentCommunity\App\Services\Helper' ) ? \FluentCommunity\App\Services\Helper::baseUrl( '' ) : home_url( '/' );
		echo '<div class="aflx-portal-brand">';
		aflanex_brand_mark( [ 'href' => $portal, 'class' => 'fcom_route' ] );
		echo '</div>';
	}
);

/**
 * Hello Elementor prints a page title we don't want inside frame pages
 * (our templates render their own headers).
 */
add_filter(
	'hello_elementor_page_title',
	function ( $show ) {
		return in_array( 'fluent_com_wp_pages', get_body_class(), true ) ? false : $show;
	}
);

add_action(
	'after_setup_theme',
	function () {
		load_child_theme_textdomain( 'aflanex-community', get_stylesheet_directory() . '/languages' );
	}
);
