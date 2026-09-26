<?php
/**
 * Template-facing helpers used by the Aflanex Community theme. Kept as
 * plain functions so the theme can `function_exists()`-guard them.
 */

use Aflanex\Community\Identity\Sso;
use Aflanex\Community\Pages\Pages;
use Aflanex\Community\Projects\Projects;

defined( 'ABSPATH' ) || exit;

/**
 * Primary entry links for the public page.
 *
 * Join depends on how accounts are created:
 *  - Erudify SSO configured → Erudify sign-up (one account for both)
 *  - Open registration      → FluentCommunity sign-up form
 *  - Neither                → no Join button (never a dead link)
 */
function aflanex_community_links(): array {
	$join = '';
	if ( Sso::is_enabled() ) {
		$join = Sso::signup_url();
	} elseif ( get_option( 'users_can_register' ) && class_exists( '\FluentCommunity\App\Services\Helper' ) ) {
		$join = add_query_arg( 'form', 'register', \FluentCommunity\App\Services\Helper::getAuthUrl() );
	}

	return [
		'sign_in'    => Pages::sign_in_url(),
		'join'       => $join,
		'sso'        => Sso::is_enabled(),
		'guidelines' => Pages::guidelines_url(),
		'erudify'    => defined( 'AFLANEX_ERUDIFY_URL' ) ? (string) AFLANEX_ERUDIFY_URL : '',
	];
}

/**
 * Real, published spaces to show guests what they'll find inside.
 * Titles and descriptions only; no member data or post content.
 */
function aflanex_community_public_spaces( int $limit = 6 ): array {
	if ( ! class_exists( '\FluentCommunity\App\Models\Space' ) ) {
		return [];
	}

	$spaces = \FluentCommunity\App\Models\Space::where( 'status', 'published' )
		->where( 'privacy', 'public' )
		->orderBy( 'parent_id', 'ASC' )
		->orderBy( 'serial', 'ASC' )
		->limit( $limit )
		->get();

	$out = [];
	foreach ( $spaces as $space ) {
		$settings = is_array( $space->settings ) ? $space->settings : [];
		$out[]    = [
			'title'       => (string) $space->title,
			'description' => wp_strip_all_tags( (string) $space->description ),
			'emoji'       => (string) ( $settings['emoji'] ?? '' ),
		];
	}
	return $out;
}

/**
 * Community size, shown publicly only once it's meaningful (avoids
 * advertising "1 member" and never inflates anything).
 */
function aflanex_community_public_stats(): array {
	$members = 0;
	if ( class_exists( '\FluentCommunity\App\Models\XProfile' ) ) {
		$members = (int) \FluentCommunity\App\Models\XProfile::where( 'status', 'active' )->count();
	}

	$threshold = (int) apply_filters( 'aflanex/public_stats_threshold', 50 );

	return [
		'members'  => $members,
		'projects' => Projects::count_published(),
		'show'     => $members >= $threshold,
	];
}

/**
 * Called by the theme's template-parts/single.php inside the
 * FluentCommunity Frame. Returns true when an Aflanex screen was rendered.
 */
function aflanex_community_render_frame_content(): bool {
	return Pages::render_frame_content();
}
