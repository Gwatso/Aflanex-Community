<?php

namespace Aflanex\Community\Portal;

defined( 'ABSPATH' ) || exit;

/**
 * Aflanex voice for a handful of FluentCommunity interface strings.
 * Uses WordPress's translation filter, so plugin files stay untouched and
 * FluentCommunity updates keep working. Unknown strings pass through.
 */
final class Copy {

	public static function register(): void {
		add_filter( 'gettext_fluent-community', [ self::class, 'translate' ], 10, 2 );
	}

	public static function map(): array {
		return apply_filters(
			'aflanex/copy/fluent_community',
			[
				'No posts found!'            => __( 'No conversations yet. Start something useful.', 'aflanex-community' ),
				'What\'s happening, %s'      => __( 'What are you working on, %s?', 'aflanex-community' ),
				'Write something here...'    => __( 'Share progress, ask a question or start a discussion…', 'aflanex-community' ),
				'No recent activities found' => __( 'Nothing new yet. This fills up as people join in.', 'aflanex-community' ),
				'No notifications found'     => __( 'You’re all caught up.', 'aflanex-community' ),
				'No members found'           => __( 'No one here yet. Invite someone who should be.', 'aflanex-community' ),
				'Feed'                       => __( 'Home', 'aflanex-community' ),
				// Sign-in / sign-up defaults (admin-saved text in FluentCommunity settings still wins).
				'Join our community and start your journey to success' => __( 'Connect with people, build practical projects and discover what comes next.', 'aflanex-community' ),
				'Login to %s'                => __( 'Sign in to %s', 'aflanex-community' ),
				'Enter your email and password to login' => __( 'Welcome back. Pick up where you left off.', 'aflanex-community' ),
				'Login'                      => __( 'Sign in', 'aflanex-community' ),
				'Sign Up to %s'              => __( 'Join %s', 'aflanex-community' ),
				'Create an account to get started' => __( 'Create your account and start building with others.', 'aflanex-community' ),
			]
		);
	}

	public static function translate( string $translation, string $text ): string {
		static $map = null;
		$map = $map ?? self::map();
		return $map[ $text ] ?? $translation;
	}
}
