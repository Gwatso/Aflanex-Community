<?php

namespace Aflanex\Community\Profiles;

use Aflanex\Community\Pages\Pages;
use Aflanex\Community\Projects\ProjectPostType;
use Aflanex\Community\Projects\Projects;
use Aflanex\Community\Support\View;

defined( 'ABSPATH' ) || exit;

/**
 * Member portfolio fields layered on top of the FluentCommunity profile.
 *
 * FluentCommunity keeps owning name, photo, cover, bio and username. We add
 * what makes a profile useful in a talent community: headline, skills,
 * focus areas, what someone is learning, how they can help and what they're
 * open to. Stored as user meta and never exposed to guests.
 */
final class MemberProfile {

	public const FIELDS = [
		'aflx_headline'          => 120,
		'aflx_location'          => 80,
		'aflx_learning_now'      => 240,
		'aflx_can_help_with'     => 240,
	];

	public const MAX_SKILLS = 15;

	public static function open_to_options(): array {
		return [
			'collaborate' => __( 'Collaborating on projects', 'aflanex-community' ),
			'feedback'    => __( 'Giving feedback', 'aflanex-community' ),
			'mentor'      => __( 'Mentoring others', 'aflanex-community' ),
			'mentee'      => __( 'Finding a mentor', 'aflanex-community' ),
		];
	}

	public static function link_fields(): array {
		return [
			'website'  => __( 'Website or portfolio', 'aflanex-community' ),
			'linkedin' => __( 'LinkedIn', 'aflanex-community' ),
			'github'   => __( 'GitHub', 'aflanex-community' ),
		];
	}

	public static function register(): void {
		add_action( 'admin_post_aflx_save_profile', [ self::class, 'handle_save' ] );
		add_action(
			'admin_post_nopriv_aflx_save_profile',
			static function () {
				wp_safe_redirect( Pages::sign_in_url( Pages::portfolio_edit_url() ) );
				exit;
			}
		);
	}

	/* ------------------------------------------------------------------ */
	/* Read                                                                */
	/* ------------------------------------------------------------------ */

	private static function xprofile( int $user_id ) {
		if ( ! class_exists( '\FluentCommunity\App\Models\XProfile' ) ) {
			return null;
		}
		static $cache = [];
		if ( ! array_key_exists( $user_id, $cache ) ) {
			$cache[ $user_id ] = \FluentCommunity\App\Models\XProfile::where( 'user_id', $user_id )->first();
		}
		return $cache[ $user_id ];
	}

	/**
	 * Compact identity used on cards. Null for unknown/inactive members.
	 */
	public static function card( int $user_id ): ?array {
		$user = $user_id ? get_userdata( $user_id ) : false;
		if ( ! $user ) {
			return null;
		}

		$x        = self::xprofile( $user_id );
		$username = $x ? $x->username : $user->user_nicename;

		return [
			'id'            => $user_id,
			'name'          => $x && $x->display_name ? $x->display_name : $user->display_name,
			'username'      => $username,
			'avatar'        => $x && $x->avatar ? $x->avatar : get_avatar_url( $user_id, [ 'size' => 128 ] ),
			'headline'      => (string) get_user_meta( $user_id, 'aflx_headline', true ),
			'location'      => (string) get_user_meta( $user_id, 'aflx_location', true ),
			'portfolio_url' => Pages::portfolio_url( $username ),
			'profile_url'   => self::community_profile_url( $username ),
			'active'        => ! $x || 'active' === $x->status,
		];
	}

	public static function community_profile_url( string $username ): string {
		if ( class_exists( '\FluentCommunity\App\Services\Helper' ) ) {
			return \FluentCommunity\App\Services\Helper::baseUrl( 'u/' . rawurlencode( $username ) . '/' );
		}
		return home_url( '/' );
	}

	public static function user_by_username( string $username ): ?\WP_User {
		$username = sanitize_user( $username, true );
		if ( '' === $username ) {
			return null;
		}
		$x = class_exists( '\FluentCommunity\App\Models\XProfile' )
			? \FluentCommunity\App\Models\XProfile::where( 'username', $username )->first()
			: null;
		if ( $x ) {
			return get_userdata( (int) $x->user_id ) ?: null;
		}
		return get_user_by( 'slug', $username ) ?: null;
	}

	/**
	 * @return \WP_Term[]
	 */
	public static function terms( int $user_id, string $taxonomy ): array {
		$ids = array_filter( array_map( 'intval', get_user_meta( $user_id, $taxonomy, false ) ) );
		if ( ! $ids ) {
			return [];
		}
		$terms = get_terms( [ 'taxonomy' => $taxonomy, 'include' => $ids, 'hide_empty' => false ] );
		return is_wp_error( $terms ) ? [] : $terms;
	}

	public static function portfolio( int $user_id ): ?array {
		$card = self::card( $user_id );
		if ( ! $card ) {
			return null;
		}

		$x     = self::xprofile( $user_id );
		$links = get_user_meta( $user_id, 'aflx_links', true );
		$open  = get_user_meta( $user_id, 'aflx_open_to', true );

		$project_ids = Projects::ids_for_member( $user_id );
		$projects    = $project_ids ? array_map(
			[ Projects::class, 'summary' ],
			get_posts( [ 'post_type' => ProjectPostType::POST_TYPE, 'post__in' => $project_ids, 'orderby' => 'modified', 'posts_per_page' => 50 ] )
		) : [];

		return $card + [
			'bio'           => $x ? (string) $x->short_description : '',
			'learning_now'  => (string) get_user_meta( $user_id, 'aflx_learning_now', true ),
			'can_help_with' => (string) get_user_meta( $user_id, 'aflx_can_help_with', true ),
			'open_to'       => array_values( array_intersect_key( self::open_to_options(), array_flip( is_array( $open ) ? $open : [] ) ) ),
			'open_to_keys'  => is_array( $open ) ? $open : [],
			'links'         => is_array( $links ) ? array_filter( $links ) : [],
			'skills'        => self::terms( $user_id, ProjectPostType::TAX_SKILL ),
			'areas'         => self::terms( $user_id, ProjectPostType::TAX_AREA ),
			'projects'      => $projects,
			'contributions' => self::contributions( $user_id ),
			'visible'       => 'no' !== get_user_meta( $user_id, 'aflx_directory_visible', true ),
			// FluentCommunity stores site-local time; user_registered is UTC.
			'joined'        => $x && $x->created_at ? strtotime( get_gmt_from_date( (string) $x->created_at ) . ' UTC' ) : strtotime( get_userdata( $user_id )->user_registered . ' UTC' ),
			'is_own'        => get_current_user_id() === $user_id,
		];
	}

	/**
	 * Meaningful contribution counts from FluentCommunity: posts started
	 * and replies given. Deliberately no follower or popularity metrics.
	 */
	public static function contributions( int $user_id ): array {
		$out = [ 'posts' => 0, 'replies' => 0, 'projects' => count( Projects::ids_for_member( $user_id ) ) ];
		if ( class_exists( '\FluentCommunity\App\Models\Feed' ) ) {
			$out['posts']   = (int) \FluentCommunity\App\Models\Feed::where( 'user_id', $user_id )->where( 'status', 'published' )->count();
			$out['replies'] = (int) \FluentCommunity\App\Models\Comment::where( 'user_id', $user_id )->where( 'status', 'published' )->count();
		}
		return $out;
	}

	/**
	 * Lightweight onboarding: real signals only, no invented progress.
	 *
	 * @return array{steps: array, done: int, total: int}
	 */
	public static function next_steps( int $user_id ): array {
		$x        = self::xprofile( $user_id );
		$username = $x ? $x->username : '';
		$contrib  = self::contributions( $user_id );

		$has_photo_or_bio = $x && ( ! empty( $x->short_description ) || ( ! empty( $x->avatar ) && ! str_contains( (string) $x->avatar, 'gravatar.com' ) ) );
		$has_details      = (bool) get_user_meta( $user_id, 'aflx_headline', true ) && (bool) get_user_meta( $user_id, ProjectPostType::TAX_SKILL, false );
		$space_count      = 0;
		if ( class_exists( '\FluentCommunity\App\Services\Helper' ) ) {
			$space_count = count( (array) \FluentCommunity\App\Services\Helper::getUserSpaceIds( $user_id ) );
		}

		$base  = class_exists( '\FluentCommunity\App\Services\Helper' ) ? \FluentCommunity\App\Services\Helper::baseUrl( '' ) : home_url( '/' );
		$steps = [
			[
				'key'   => 'profile',
				'label' => __( 'Add a photo and a short bio', 'aflanex-community' ),
				'url'   => $username ? self::community_profile_url( $username ) : $base,
				'done'  => $has_photo_or_bio,
			],
			[
				'key'   => 'details',
				'label' => __( 'Add your headline and skills', 'aflanex-community' ),
				'url'   => Pages::portfolio_edit_url(),
				'done'  => $has_details,
			],
			[
				'key'   => 'spaces',
				'label' => __( 'Join a space that fits your goals', 'aflanex-community' ),
				'url'   => $base . 'discover/spaces',
				'done'  => $space_count > 0,
			],
			[
				'key'   => 'hello',
				'label' => __( 'Introduce yourself', 'aflanex-community' ),
				'url'   => Pages::introductions_url(),
				'done'  => $contrib['posts'] > 0,
			],
			[
				'key'   => 'contribute',
				'label' => __( 'Reply to someone’s post', 'aflanex-community' ),
				'url'   => $base,
				'done'  => $contrib['replies'] > 0,
			],
			[
				'key'   => 'project',
				'label' => __( 'Share what you’re building', 'aflanex-community' ),
				'url'   => Pages::project_form_url(),
				'done'  => $contrib['projects'] > 0,
			],
		];

		$done = count( array_filter( wp_list_pluck( $steps, 'done' ) ) );

		return [ 'steps' => $steps, 'done' => $done, 'total' => count( $steps ) ];
	}

	/* ------------------------------------------------------------------ */
	/* Directory                                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * @return array{items: array, total: int, pages: int}
	 */
	public static function directory( array $args ): array {
		$args = wp_parse_args( $args, [ 'search' => '', 'area' => 0, 'skill' => '', 'open_to' => '', 'paged' => 1, 'per_page' => 24 ] );

		$include = null;
		if ( class_exists( '\FluentCommunity\App\Models\XProfile' ) ) {
			$include = array_map( 'intval', \FluentCommunity\App\Models\XProfile::where( 'status', 'active' )->pluck( 'user_id' )->toArray() );
			if ( ! $include ) {
				return [ 'items' => [], 'total' => 0, 'pages' => 0 ];
			}
		}

		$meta_query = [
			'relation' => 'AND',
			[
				'relation' => 'OR',
				[ 'key' => 'aflx_directory_visible', 'compare' => 'NOT EXISTS' ],
				[ 'key' => 'aflx_directory_visible', 'value' => 'no', 'compare' => '!=' ],
			],
		];
		if ( $args['area'] ) {
			$meta_query[] = [ 'key' => ProjectPostType::TAX_AREA, 'value' => (int) $args['area'], 'type' => 'NUMERIC' ];
		}
		if ( $args['skill'] ) {
			$term         = get_term_by( 'slug', sanitize_title( $args['skill'] ), ProjectPostType::TAX_SKILL );
			$meta_query[] = [ 'key' => ProjectPostType::TAX_SKILL, 'value' => $term ? (int) $term->term_id : -1, 'type' => 'NUMERIC' ];
		}
		if ( $args['open_to'] && isset( self::open_to_options()[ $args['open_to'] ] ) ) {
			$meta_query[] = [ 'key' => 'aflx_open_to', 'value' => '"' . $args['open_to'] . '"', 'compare' => 'LIKE' ];
		}

		$query_args = [
			'number'      => (int) $args['per_page'],
			'paged'       => max( 1, (int) $args['paged'] ),
			'orderby'     => 'registered',
			'order'       => 'DESC',
			'meta_query'  => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'count_total' => true,
			'fields'      => 'ID',
		];
		if ( null !== $include ) {
			$query_args['include'] = $include;
		}
		if ( $args['search'] ) {
			$query_args['search']         = '*' . sanitize_text_field( $args['search'] ) . '*';
			$query_args['search_columns'] = [ 'display_name', 'user_nicename' ];
		}

		$query = new \WP_User_Query( $query_args );
		$ids   = array_map( 'intval', (array) $query->get_results() );

		$items = [];
		foreach ( $ids as $id ) {
			$card = self::card( $id );
			if ( ! $card ) {
				continue;
			}
			$card['skills'] = wp_list_pluck( array_slice( self::terms( $id, ProjectPostType::TAX_SKILL ), 0, 4 ), 'name' );
			$card['can_help_with'] = (string) get_user_meta( $id, 'aflx_can_help_with', true );
			$items[] = $card;
		}

		$total = (int) $query->get_total();

		return [ 'items' => $items, 'total' => $total, 'pages' => (int) ceil( $total / max( 1, (int) $args['per_page'] ) ) ];
	}

	/* ------------------------------------------------------------------ */
	/* Write                                                               */
	/* ------------------------------------------------------------------ */

	public static function handle_save(): void {
		$user_id  = get_current_user_id();
		$redirect = Pages::portfolio_edit_url();

		if ( ! $user_id || ! Pages::can_access_community( $user_id ) ) {
			wp_safe_redirect( add_query_arg( 'aflx_notice', 'not_allowed', $redirect ) );
			exit;
		}
		if ( ! isset( $_POST['_aflx_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_aflx_nonce'] ) ), 'aflx_save_profile_' . $user_id ) ) {
			wp_safe_redirect( add_query_arg( 'aflx_notice', 'expired', $redirect ) );
			exit;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified above.
		foreach ( self::FIELDS as $key => $max ) {
			$value = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
			update_user_meta( $user_id, $key, mb_substr( $value, 0, $max ) );
		}

		$open = isset( $_POST['aflx_open_to'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['aflx_open_to'] ) ) : [];
		update_user_meta( $user_id, 'aflx_open_to', array_values( array_intersect( $open, array_keys( self::open_to_options() ) ) ) );

		$links = [];
		foreach ( array_keys( self::link_fields() ) as $key ) {
			$url = isset( $_POST['aflx_links'][ $key ] ) ? esc_url_raw( wp_unslash( $_POST['aflx_links'][ $key ] ), [ 'http', 'https' ] ) : '';
			if ( $url ) {
				$links[ $key ] = $url;
			}
		}
		update_user_meta( $user_id, 'aflx_links', $links );

		update_user_meta( $user_id, 'aflx_directory_visible', empty( $_POST['aflx_directory_visible'] ) ? 'no' : 'yes' );

		// Focus areas: only existing, admin-defined terms.
		$area_ids = isset( $_POST['aflx_area'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['aflx_area'] ) ) : [];
		self::replace_term_meta( $user_id, ProjectPostType::TAX_AREA, self::existing_term_ids( $area_ids, ProjectPostType::TAX_AREA ) );

		// Skills: comma separated, created on demand, capped.
		$skills_raw = isset( $_POST['aflx_skills'] ) ? sanitize_text_field( wp_unslash( $_POST['aflx_skills'] ) ) : '';
		self::replace_term_meta( $user_id, ProjectPostType::TAX_SKILL, self::skill_term_ids( $skills_raw ) );
		// phpcs:enable

		do_action( 'aflanex/profile/saved', $user_id );

		wp_safe_redirect( add_query_arg( 'aflx_notice', 'profile_saved', Pages::portfolio_url( self::card( $user_id )['username'] ?? '' ) ) );
		exit;
	}

	/**
	 * Turn "Figma, Research, AI prompting" into skill term IDs.
	 *
	 * @return int[]
	 */
	public static function skill_term_ids( string $raw ): array {
		$ids   = [];
		$names = array_filter( array_map( 'trim', explode( ',', $raw ) ) );

		foreach ( array_slice( array_unique( $names ), 0, self::MAX_SKILLS ) as $name ) {
			$name = mb_substr( wp_strip_all_tags( $name ), 0, 40 );
			if ( '' === $name ) {
				continue;
			}
			$existing = term_exists( $name, ProjectPostType::TAX_SKILL );
			if ( ! $existing ) {
				$existing = get_term_by( 'slug', sanitize_title( $name ), ProjectPostType::TAX_SKILL );
				$existing = $existing ? [ 'term_id' => $existing->term_id ] : wp_insert_term( $name, ProjectPostType::TAX_SKILL );
			}
			if ( ! is_wp_error( $existing ) && ! empty( $existing['term_id'] ) ) {
				$ids[] = (int) $existing['term_id'];
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * @param int[] $ids
	 * @return int[]
	 */
	public static function existing_term_ids( array $ids, string $taxonomy ): array {
		$ids = array_filter( $ids );
		if ( ! $ids ) {
			return [];
		}
		$terms = get_terms( [ 'taxonomy' => $taxonomy, 'include' => $ids, 'hide_empty' => false, 'fields' => 'ids' ] );
		return is_wp_error( $terms ) ? [] : array_map( 'intval', $terms );
	}

	/**
	 * @param int[] $term_ids
	 */
	private static function replace_term_meta( int $user_id, string $key, array $term_ids ): void {
		delete_user_meta( $user_id, $key );
		foreach ( $term_ids as $term_id ) {
			add_user_meta( $user_id, $key, (int) $term_id );
		}
	}

	public static function render_notice(): void {
		$notice = View::notice_from_request();
		if ( $notice ) {
			View::render( 'partials/notice', $notice );
		}
	}
}
