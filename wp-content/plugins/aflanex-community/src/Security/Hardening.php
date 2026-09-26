<?php

namespace Aflanex\Community\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps member information private to members. The community is
 * members-only, so WordPress's public user listings are closed to guests.
 */
final class Hardening {

	public static function register(): void {
		add_filter( 'rest_pre_dispatch', [ self::class, 'block_guest_user_endpoints' ], 10, 3 );
		add_action( 'template_redirect', [ self::class, 'block_author_archives' ], 1 );
		add_filter( 'wp_sitemaps_add_provider', [ self::class, 'remove_user_sitemap' ], 10, 2 );
		add_filter( 'oembed_response_data', [ self::class, 'strip_oembed_author' ] );
		add_filter( 'show_admin_bar', [ self::class, 'admin_bar_for_staff_only' ] );
		add_action( 'admin_init', [ self::class, 'keep_members_out_of_wp_admin' ], 1 );
	}

	/**
	 * Members live in the community, not the WordPress dashboard.
	 */
	public static function admin_bar_for_staff_only( bool $show ): bool {
		return $show && current_user_can( 'edit_posts' );
	}

	public static function keep_members_out_of_wp_admin(): void {
		if ( wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || current_user_can( 'edit_posts' ) || ! is_user_logged_in() ) {
			return;
		}
		// admin-post.php powers our front-end forms and must stay reachable.
		$script = isset( $_SERVER['SCRIPT_NAME'] ) ? basename( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) ) : '';
		if ( 'admin-post.php' === $script ) {
			return;
		}
		wp_safe_redirect( class_exists( '\FluentCommunity\App\Services\Helper' ) ? \FluentCommunity\App\Services\Helper::baseUrl( '' ) : home_url( '/' ) );
		exit;
	}

	/**
	 * /wp/v2/users exposes usernames to anyone. Members-only community:
	 * guests get a 401 instead.
	 *
	 * @param mixed            $result
	 * @param \WP_REST_Server  $server
	 * @param \WP_REST_Request $request
	 * @return mixed
	 */
	public static function block_guest_user_endpoints( $result, $server, $request ) {
		if ( is_user_logged_in() ) {
			return $result;
		}

		if ( preg_match( '#^/wp/v2/users(/|$)#', $request->get_route() ) ) {
			return new \WP_Error( 'rest_forbidden', __( 'Sign in to view members.', 'aflanex-community' ), [ 'status' => 401 ] );
		}

		return $result;
	}

	public static function block_author_archives(): void {
		if ( is_user_logged_in() ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( is_author() || isset( $_GET['author'] ) ) {
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	}

	/**
	 * @param \WP_Sitemaps_Provider|false $provider
	 */
	public static function remove_user_sitemap( $provider, string $name ) {
		return 'users' === $name ? false : $provider;
	}

	public static function strip_oembed_author( array $data ): array {
		unset( $data['author_name'], $data['author_url'] );
		return $data;
	}
}
