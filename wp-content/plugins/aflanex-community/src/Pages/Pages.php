<?php

namespace Aflanex\Community\Pages;

use Aflanex\Community\Profiles\MemberProfile;
use Aflanex\Community\Projects\ProjectPostType;
use Aflanex\Community\Projects\Projects;
use Aflanex\Community\Support\View;

defined( 'ABSPATH' ) || exit;

/**
 * Community screens that live outside the FluentCommunity SPA but inside
 * its chrome (via FluentCommunity's supported "Frame" page template):
 *
 *   /projects/            Project directory
 *   /projects/new/        Start or edit a project (?project=ID)
 *   /project/{slug}/      Project page
 *   /people/              Member discovery
 *   /portfolio/{user}/    Member portfolio
 *   /portfolio/edit/      Edit your portfolio details
 *   /community-guidelines/
 *
 * Page IDs are stored in the `aflx_pages` option (created by the
 * installer), so admins can rename or move pages without breaking routes.
 */
final class Pages {

	public const OPTION = 'aflx_pages';

	private const GATED = [ 'projects', 'project_form', 'people', 'portfolio', 'portfolio_edit' ];

	public static function register(): void {
		add_action( 'init', [ self::class, 'rewrites' ] );
		add_filter( 'query_vars', static fn( $vars ) => array_merge( $vars, [ 'aflx_member' ] ) );
		add_action( 'template_redirect', [ self::class, 'route_guard' ], 5 );
		add_filter( 'fluent_community/template_slug', [ self::class, 'use_frame_template' ] );
		add_filter( 'wp_robots', [ self::class, 'robots' ] );
		add_filter( 'display_post_states', [ self::class, 'post_states' ], 10, 2 );
		add_filter( 'document_title_parts', [ self::class, 'document_title' ] );
		add_filter( 'body_class', [ self::class, 'body_class' ] );
	}

	/**
	 * Lets the portal skin highlight the matching top-level nav item.
	 */
	public static function body_class( array $classes ): array {
		$key = self::current_key();
		if ( $key ) {
			$section   = in_array( $key, [ 'project', 'projects', 'project_form' ], true ) ? 'projects' : ( in_array( $key, [ 'people', 'portfolio', 'portfolio_edit' ], true ) ? 'people' : $key );
			$classes[] = 'aflx-screen';
			$classes[] = 'aflx-section-' . $section;
		}
		return $classes;
	}

	/* ------------------------------------------------------------------ */
	/* Page registry                                                       */
	/* ------------------------------------------------------------------ */

	public static function ids(): array {
		$ids = get_option( self::OPTION, [] );
		return is_array( $ids ) ? array_map( 'intval', $ids ) : [];
	}

	public static function id( string $key ): int {
		return self::ids()[ $key ] ?? 0;
	}

	public static function url( string $key ): string {
		$id = self::id( $key );
		return $id && 'publish' === get_post_status( $id ) ? (string) get_permalink( $id ) : '';
	}

	public static function current_key(): string {
		if ( is_singular( ProjectPostType::POST_TYPE ) ) {
			return 'project';
		}
		if ( ! is_page() ) {
			return '';
		}
		$key = array_search( (int) get_queried_object_id(), self::ids(), true );
		return is_string( $key ) ? $key : '';
	}

	/* ------------------------------------------------------------------ */
	/* URLs                                                                */
	/* ------------------------------------------------------------------ */

	public static function projects_url( array $args = [] ): string {
		$url = self::url( 'projects' ) ?: home_url( '/projects/' );
		return $args ? add_query_arg( $args, $url ) : $url;
	}

	public static function project_form_url( int $project_id = 0 ): string {
		$url = self::url( 'project_form' ) ?: home_url( '/projects/new/' );
		return $project_id ? add_query_arg( 'project', $project_id, $url ) : $url;
	}

	public static function people_url( array $args = [] ): string {
		$url = self::url( 'people' ) ?: home_url( '/people/' );
		return $args ? add_query_arg( $args, $url ) : $url;
	}

	public static function portfolio_url( string $username ): string {
		$base = self::url( 'portfolio' ) ?: home_url( '/portfolio/' );
		return $username ? trailingslashit( $base ) . rawurlencode( $username ) . '/' : $base;
	}

	public static function portfolio_edit_url(): string {
		return self::url( 'portfolio_edit' ) ?: home_url( '/portfolio/edit/' );
	}

	public static function guidelines_url(): string {
		return self::url( 'guidelines' );
	}

	public static function introductions_url(): string {
		if ( class_exists( '\FluentCommunity\App\Models\Space' ) ) {
			$slug  = get_option( 'aflx_introductions_space', 'say-hello' );
			$space = \FluentCommunity\App\Models\Space::where( 'slug', $slug )->first();
			if ( $space ) {
				return $space->getPermalink();
			}
		}
		return class_exists( '\FluentCommunity\App\Services\Helper' ) ? \FluentCommunity\App\Services\Helper::baseUrl( '' ) : home_url( '/' );
	}

	/* ------------------------------------------------------------------ */
	/* Access                                                              */
	/* ------------------------------------------------------------------ */

	public static function can_access_community( ?int $user_id = null ): bool {
		$user_id = $user_id ?? get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}
		if ( class_exists( '\FluentCommunity\App\Services\Helper' ) ) {
			return (bool) \FluentCommunity\App\Services\Helper::canAccessPortal( $user_id );
		}
		return true;
	}

	public static function sign_in_url( string $redirect_to = '' ): string {
		if ( \Aflanex\Community\Identity\Sso::is_enabled() ) {
			return \Aflanex\Community\Identity\Sso::start_url( $redirect_to );
		}
		$url = class_exists( '\FluentCommunity\App\Services\Helper' )
			? \FluentCommunity\App\Services\Helper::getAuthUrl()
			: wp_login_url();
		return $redirect_to ? add_query_arg( 'redirect_to', rawurlencode( $redirect_to ), $url ) : $url;
	}

	/* ------------------------------------------------------------------ */
	/* Routing                                                             */
	/* ------------------------------------------------------------------ */

	public static function rewrites(): void {
		$portfolio = self::id( 'portfolio' );
		if ( $portfolio ) {
			$path = get_page_uri( $portfolio );
			if ( $path ) {
				add_rewrite_rule(
					'^' . preg_quote( $path, '#' ) . '/(?!edit/?$)([^/]+)/?$',
					'index.php?page_id=' . $portfolio . '&aflx_member=$matches[1]',
					'top'
				);
			}
		}
	}

	public static function route_guard(): void {
		// Signed-in members skip the public entry page and land in the community.
		if ( is_front_page() && is_user_logged_in() && self::can_access_community() ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( ! ( current_user_can( 'manage_options' ) && isset( $_GET['preview_entry'] ) ) ) {
				wp_safe_redirect( class_exists( '\FluentCommunity\App\Services\Helper' ) ? \FluentCommunity\App\Services\Helper::baseUrl( '' ) : home_url( '/' ) );
				exit;
			}
		}

		$key = self::current_key();
		if ( ! $key || ( 'project' !== $key && ! in_array( $key, self::GATED, true ) ) ) {
			return;
		}

		if ( ! self::can_access_community() ) {
			$current = ( is_ssl() ? 'https://' : 'http://' ) . sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ?? '' ) ) . sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );
			nocache_headers();
			wp_safe_redirect( self::sign_in_url( esc_url_raw( $current ) ) );
			exit;
		}

		// /portfolio/ with no member → your own portfolio.
		if ( 'portfolio' === $key && ! get_query_var( 'aflx_member' ) ) {
			$card = MemberProfile::card( get_current_user_id() );
			if ( $card ) {
				wp_safe_redirect( self::portfolio_url( $card['username'] ) );
				exit;
			}
		}

		nocache_headers();
	}

	public static function use_frame_template( $slug ) {
		if ( self::current_key() ) {
			return 'fluent-community-frame.php';
		}
		return $slug;
	}

	public static function robots( array $robots ): array {
		$key = self::current_key();
		if ( $key && 'guidelines' !== $key ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
		}
		return $robots;
	}

	/**
	 * @param string[] $parts
	 */
	public static function document_title( array $parts ): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'project_form' === self::current_key() && ! empty( $_GET['project'] ) ) {
			$parts['title'] = __( 'Edit project', 'aflanex-community' );
		}
		if ( 'portfolio' === self::current_key() && get_query_var( 'aflx_member' ) ) {
			$user = MemberProfile::user_by_username( (string) get_query_var( 'aflx_member' ) );
			$card = $user ? MemberProfile::card( $user->ID ) : null;
			if ( $card ) {
				$parts['title'] = $card['name'];
			}
		}
		return $parts;
	}

	/**
	 * Label our pages in wp-admin so nobody deletes them by accident.
	 */
	public static function post_states( array $states, \WP_Post $post ): array {
		$key = array_search( (int) $post->ID, self::ids(), true );
		if ( is_string( $key ) ) {
			$states[ 'aflx_' . $key ] = __( 'Aflanex Community screen', 'aflanex-community' );
		}
		return $states;
	}

	/* ------------------------------------------------------------------ */
	/* Rendering (called from the theme's template-parts/single.php)       */
	/* ------------------------------------------------------------------ */

	public static function render_frame_content(): bool {
		$key = self::current_key();
		if ( ! $key ) {
			return false;
		}

		// Should never happen (route_guard redirects), but never render members-only content to guests.
		if ( 'guidelines' !== $key && ! self::can_access_community() ) {
			return true;
		}

		$user_id = get_current_user_id();

		echo '<main id="content" class="aflx-scope aflx-frame aflx-frame--' . esc_attr( $key ) . '">';

		switch ( $key ) {
			case 'project':
				$post = get_queried_object();
				View::render( 'projects/single', [ 'project' => Projects::detail( $post ) ] );
				break;

			case 'projects':
				// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
				$filters = [
					'status' => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
					'area'   => isset( $_GET['area'] ) ? absint( $_GET['area'] ) : 0,
					'skill'  => isset( $_GET['skill'] ) ? sanitize_title( wp_unslash( $_GET['skill'] ) ) : '',
					'search' => isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '',
					'member' => ! empty( $_GET['mine'] ) ? $user_id : 0,
					'paged'  => isset( $_GET['pg'] ) ? max( 1, absint( $_GET['pg'] ) ) : 1,
				];
				// phpcs:enable
				View::render(
					'projects/directory',
					[
						'filters'  => $filters,
						'result'   => Projects::query( $filters ),
						'areas'    => get_terms( [ 'taxonomy' => ProjectPostType::TAX_AREA, 'hide_empty' => false ] ),
						'statuses' => ProjectPostType::statuses(),
						'has_any'  => Projects::count_published() > 0,
					]
				);
				break;

			case 'project_form':
				$project_id = isset( $_GET['project'] ) ? absint( $_GET['project'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$project    = $project_id ? get_post( $project_id ) : null;
				if ( $project && ( ProjectPostType::POST_TYPE !== $project->post_type || ! Projects::can_edit( $project_id ) ) ) {
					View::render( 'partials/notice', [ 'type' => 'danger', 'message' => __( 'You can only edit projects you own or collaborate on.', 'aflanex-community' ) ] );
					break;
				}
				View::render(
					'projects/form',
					[
						'project'  => $project ? Projects::detail( $project ) : null,
						'areas'    => get_terms( [ 'taxonomy' => ProjectPostType::TAX_AREA, 'hide_empty' => false ] ),
						'statuses' => ProjectPostType::statuses(),
					]
				);
				break;

			case 'people':
				// phpcs:disable WordPress.Security.NonceVerification.Recommended
				$filters = [
					'search'  => isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '',
					'area'    => isset( $_GET['area'] ) ? absint( $_GET['area'] ) : 0,
					'skill'   => isset( $_GET['skill'] ) ? sanitize_title( wp_unslash( $_GET['skill'] ) ) : '',
					'open_to' => isset( $_GET['open_to'] ) ? sanitize_key( wp_unslash( $_GET['open_to'] ) ) : '',
					'paged'   => isset( $_GET['pg'] ) ? max( 1, absint( $_GET['pg'] ) ) : 1,
				];
				// phpcs:enable
				View::render(
					'people/directory',
					[
						'filters' => $filters,
						'result'  => MemberProfile::directory( $filters ),
						'areas'   => get_terms( [ 'taxonomy' => ProjectPostType::TAX_AREA, 'hide_empty' => false ] ),
						'open_to' => MemberProfile::open_to_options(),
						'me'      => MemberProfile::card( $user_id ),
					]
				);
				break;

			case 'portfolio':
				$user      = MemberProfile::user_by_username( (string) get_query_var( 'aflx_member' ) );
				$portfolio = $user ? MemberProfile::portfolio( $user->ID ) : null;
				if ( ! $portfolio || ( ! $portfolio['active'] && ! current_user_can( 'edit_users' ) ) ) {
					View::render( 'partials/empty', [ 'icon' => 'people', 'title' => __( 'We couldn’t find that member.', 'aflanex-community' ), 'text' => __( 'They may have changed their username or left the community.', 'aflanex-community' ), 'action_url' => self::people_url(), 'action_label' => __( 'Find people', 'aflanex-community' ) ] );
					break;
				}
				View::render(
					'portfolio/view',
					[
						'member'   => $portfolio,
						'learning' => \Aflanex\Community\Integrations\IntegrationRegistry::learning_summary( $user->ID ),
					]
				);
				break;

			case 'portfolio_edit':
				$portfolio = MemberProfile::portfolio( $user_id );
				View::render(
					'portfolio/edit',
					[
						'member' => $portfolio,
						'areas'  => get_terms( [ 'taxonomy' => ProjectPostType::TAX_AREA, 'hide_empty' => false ] ),
					]
				);
				break;

			case 'guidelines':
				View::render( 'pages/guidelines', [ 'post' => get_queried_object() ] );
				break;
		}

		echo '</main>';

		return true;
	}
}
