<?php

namespace Aflanex\Community\Projects;

use Aflanex\Community\Pages\Pages;
use Aflanex\Community\Profiles\MemberProfile;

defined( 'ABSPATH' ) || exit;

/**
 * Read model + permissions for projects.
 */
final class Projects {

	public const PER_PAGE = 12;

	/**
	 * Owner, listed collaborators and moderators can edit.
	 */
	public static function can_edit( int $project_id, ?int $user_id = null ): bool {
		$user_id = $user_id ?? get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}
		$post = get_post( $project_id );
		if ( ! $post || ProjectPostType::POST_TYPE !== $post->post_type ) {
			return false;
		}
		if ( (int) $post->post_author === $user_id || user_can( $user_id, 'edit_others_posts' ) ) {
			return true;
		}
		return in_array( $user_id, self::collaborator_ids( $project_id ), true );
	}

	public static function can_create( ?int $user_id = null ): bool {
		$user_id = $user_id ?? get_current_user_id();
		return $user_id > 0 && Pages::can_access_community( $user_id );
	}

	/**
	 * @return int[]
	 */
	public static function collaborator_ids( int $project_id ): array {
		// One meta row per collaborator, so membership is an exact, indexed match.
		$ids = get_post_meta( $project_id, 'aflx_collaborator', false );
		return array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
	}

	public static function status_label( string $status ): string {
		$statuses = ProjectPostType::statuses();
		return $statuses[ $status ] ?? $statuses['idea'];
	}

	/**
	 * Card-level data (directory, profiles).
	 */
	public static function summary( \WP_Post $post ): array {
		$status = (string) get_post_meta( $post->ID, 'aflx_status', true ) ?: 'idea';

		return [
			'id'            => $post->ID,
			'title'         => get_the_title( $post ),
			'url'           => get_permalink( $post ),
			'summary'       => $post->post_excerpt,
			'status'        => $status,
			'status_label'  => self::status_label( $status ),
			'owner'         => MemberProfile::card( (int) $post->post_author ),
			'collaborators' => array_values( array_filter( array_map( [ MemberProfile::class, 'card' ], self::collaborator_ids( $post->ID ) ) ) ),
			'areas'         => self::term_names( $post->ID, ProjectPostType::TAX_AREA ),
			'skills'        => self::term_names( $post->ID, ProjectPostType::TAX_SKILL ),
			'cover'         => get_the_post_thumbnail_url( $post, 'medium_large' ) ?: '',
			'updated'       => get_post_modified_time( 'U', true, $post ),
			'looking_for'   => (string) get_post_meta( $post->ID, 'aflx_looking_for', true ),
		];
	}

	/**
	 * Full data for the project page.
	 */
	public static function detail( \WP_Post $post ): array {
		$data = self::summary( $post );

		$links = get_post_meta( $post->ID, 'aflx_links', true );

		$data += [
			'content'            => apply_filters( 'the_content', $post->post_content ),
			'problem'            => (string) get_post_meta( $post->ID, 'aflx_problem', true ),
			'approach'           => (string) get_post_meta( $post->ID, 'aflx_approach', true ),
			'links'              => is_array( $links ) ? $links : [],
			'feedback_requested' => (bool) get_post_meta( $post->ID, 'aflx_feedback_requested', true ),
			'created'            => get_post_time( 'U', true, $post ),
			'can_edit'           => self::can_edit( $post->ID ),
			'edit_url'           => Pages::project_form_url( $post->ID ),
			'discussion'         => CommunityBridge::discussion( $post->ID ),
			'updates'            => CommunityBridge::updates( $post->ID ),
			'area_ids'           => wp_get_object_terms( $post->ID, ProjectPostType::TAX_AREA, [ 'fields' => 'ids' ] ),
			'skill_names'        => self::term_names( $post->ID, ProjectPostType::TAX_SKILL ),
			'data_source'        => (string) get_post_meta( $post->ID, 'aflx_data_source', true ) ?: 'community',
		];

		return $data;
	}

	/**
	 * @return array{items: array, total: int, pages: int}
	 */
	public static function query( array $args = [] ): array {
		$args = wp_parse_args(
			$args,
			[
				'status'       => '',
				'area'         => 0,
				'skill'        => '',
				'search'       => '',
				'member'       => 0,     // owner OR collaborator
				'paged'        => 1,
				'per_page'     => self::PER_PAGE,
			]
		);

		$query_args = [
			'post_type'           => ProjectPostType::POST_TYPE,
			'post_status'         => 'publish',
			'posts_per_page'      => (int) $args['per_page'],
			'paged'               => max( 1, (int) $args['paged'] ),
			'orderby'             => 'modified',
			'order'               => 'DESC',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => false,
		];

		$meta_query = [];
		if ( $args['status'] && isset( ProjectPostType::statuses()[ $args['status'] ] ) ) {
			$meta_query[] = [ 'key' => 'aflx_status', 'value' => $args['status'] ];
		}

		$tax_query = [];
		if ( $args['area'] ) {
			$tax_query[] = [ 'taxonomy' => ProjectPostType::TAX_AREA, 'field' => 'term_id', 'terms' => (int) $args['area'] ];
		}
		if ( $args['skill'] ) {
			$tax_query[] = [ 'taxonomy' => ProjectPostType::TAX_SKILL, 'field' => 'slug', 'terms' => sanitize_title( $args['skill'] ) ];
		}

		if ( $args['member'] ) {
			$ids = self::ids_for_member( (int) $args['member'] );
			$query_args['post__in'] = $ids ?: [ 0 ];
		}

		if ( $args['search'] ) {
			$query_args['s'] = sanitize_text_field( $args['search'] );
		}
		if ( $meta_query ) {
			$query_args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}
		if ( $tax_query ) {
			$query_args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}

		$query = new \WP_Query( $query_args );

		return [
			'items' => array_map( [ self::class, 'summary' ], $query->posts ),
			'total' => (int) $query->found_posts,
			'pages' => (int) $query->max_num_pages,
		];
	}

	/**
	 * Projects a member owns or collaborates on.
	 *
	 * @return int[]
	 */
	public static function ids_for_member( int $user_id ): array {
		$base = [
			'post_type'      => ProjectPostType::POST_TYPE,
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'posts_per_page' => 200,
			'no_found_rows'  => true,
		];

		$owned         = get_posts( $base + [ 'author' => $user_id ] );
		$collaborating = get_posts(
			$base + [
				'meta_query' => [ [ 'key' => 'aflx_collaborator', 'value' => $user_id, 'type' => 'NUMERIC' ] ], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			]
		);

		return array_values( array_unique( array_map( 'intval', array_merge( $owned, $collaborating ) ) ) );
	}

	public static function count_published(): int {
		$counts = wp_count_posts( ProjectPostType::POST_TYPE );
		return isset( $counts->publish ) ? (int) $counts->publish : 0;
	}

	/**
	 * @return string[]
	 */
	private static function term_names( int $post_id, string $taxonomy ): array {
		$terms = get_the_terms( $post_id, $taxonomy );
		return ( $terms && ! is_wp_error( $terms ) ) ? wp_list_pluck( $terms, 'name' ) : [];
	}
}
