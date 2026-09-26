<?php

namespace Aflanex\Community\Projects;

defined( 'ABSPATH' ) || exit;

/**
 * Data model for Projects.
 *
 * - aflx_project  (post type): the project itself; post_content is the
 *   longer description, post_excerpt the one-line summary.
 * - aflx_area     (taxonomy):  admin-managed focus areas, shared with
 *   member interests so people and projects can be matched.
 * - aflx_skill    (taxonomy):  skills used, created by members, curated
 *   by admins. Member skills reference the same terms.
 *
 * Projects are community data (source of truth: this site). The
 * integration fields below are reserved for future, controlled sync with
 * Erudify (course projects → community projects) and stay empty until a
 * real integration writes them.
 */
final class ProjectPostType {

	public const POST_TYPE = 'aflx_project';
	public const TAX_AREA  = 'aflx_area';
	public const TAX_SKILL = 'aflx_skill';

	public static function statuses(): array {
		return [
			'idea'                  => __( 'Idea', 'aflanex-community' ),
			'in_progress'           => __( 'In progress', 'aflanex-community' ),
			'seeking_collaborators' => __( 'Seeking collaborators', 'aflanex-community' ),
			'ready_for_feedback'    => __( 'Ready for feedback', 'aflanex-community' ),
			'completed'             => __( 'Completed', 'aflanex-community' ),
		];
	}

	/**
	 * Meta keys. Integration keys are namespaced and never edited by members.
	 */
	public static function meta_schema(): array {
		return [
			'aflx_status'              => [ 'type' => 'string', 'default' => 'idea' ],
			'aflx_problem'             => [ 'type' => 'string', 'default' => '' ],
			'aflx_approach'            => [ 'type' => 'string', 'default' => '' ],
			'aflx_looking_for'         => [ 'type' => 'string', 'default' => '' ],
			'aflx_links'               => [ 'type' => 'array', 'default' => [] ],
			'aflx_feedback_requested'  => [ 'type' => 'boolean', 'default' => false ],
			'aflx_feed_id'             => [ 'type' => 'integer', 'default' => 0 ],
			'aflx_update_feed_ids'     => [ 'type' => 'array', 'default' => [] ],
			// Future integration (Erudify). Written only by a real sync.
			'aflx_data_source'         => [ 'type' => 'string', 'default' => 'community' ],
			'aflx_external_project_id' => [ 'type' => 'string', 'default' => '' ],
			'aflx_external_course_id'  => [ 'type' => 'string', 'default' => '' ],
			'aflx_sync_status'         => [ 'type' => 'string', 'default' => '' ],
			'aflx_last_synced_at'      => [ 'type' => 'string', 'default' => '' ],
		];
	}

	public static function register(): void {
		add_action( 'init', [ self::class, 'register_types' ] );
	}

	public static function register_types(): void {
		register_post_type(
			self::POST_TYPE,
			[
				'labels'              => [
					'name'               => __( 'Projects', 'aflanex-community' ),
					'singular_name'      => __( 'Project', 'aflanex-community' ),
					'add_new_item'       => __( 'Add project', 'aflanex-community' ),
					'edit_item'          => __( 'Edit project', 'aflanex-community' ),
					'search_items'       => __( 'Search projects', 'aflanex-community' ),
					'not_found'          => __( 'No projects yet.', 'aflanex-community' ),
					'menu_name'          => __( 'Projects', 'aflanex-community' ),
				],
				'public'              => true,
				'publicly_queryable'  => true,
				'exclude_from_search' => true,  // Members-only content stays out of WP search/SEO.
				'show_in_rest'        => false, // No public REST exposure.
				'show_ui'             => true,  // Admins/editors moderate in wp-admin.
				'show_in_menu'        => true,
				'menu_icon'           => 'dashicons-portfolio',
				'menu_position'       => 26,
				'has_archive'         => false, // The /projects/ page is the directory.
				'rewrite'             => [ 'slug' => 'project', 'with_front' => false ],
				'supports'            => [ 'title', 'editor', 'excerpt', 'author', 'thumbnail', 'revisions' ],
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
			]
		);

		register_taxonomy(
			self::TAX_AREA,
			[ self::POST_TYPE ],
			[
				'labels'            => [
					'name'          => __( 'Focus areas', 'aflanex-community' ),
					'singular_name' => __( 'Focus area', 'aflanex-community' ),
					'add_new_item'  => __( 'Add focus area', 'aflanex-community' ),
				],
				'description'       => __( 'Areas used to group projects and match members by interest.', 'aflanex-community' ),
				'public'            => false,
				'show_ui'           => true,
				'show_admin_column' => true,
				'hierarchical'      => true,
				'show_in_rest'      => false,
				'rewrite'           => false,
			]
		);

		register_taxonomy(
			self::TAX_SKILL,
			[ self::POST_TYPE ],
			[
				'labels'            => [
					'name'          => __( 'Skills', 'aflanex-community' ),
					'singular_name' => __( 'Skill', 'aflanex-community' ),
					'add_new_item'  => __( 'Add skill', 'aflanex-community' ),
				],
				'public'            => false,
				'show_ui'           => true,
				'show_admin_column' => true,
				'hierarchical'      => false,
				'show_in_rest'      => false,
				'rewrite'           => false,
			]
		);

		foreach ( self::meta_schema() as $key => $schema ) {
			register_post_meta(
				self::POST_TYPE,
				$key,
				[
					'type'          => $schema['type'],
					'single'        => true,
					'default'       => $schema['default'],
					'show_in_rest'  => false,
					'auth_callback' => static fn() => current_user_can( 'edit_others_posts' ),
				]
			);
		}

		// One row per collaborator (user ID) for exact lookups.
		register_post_meta(
			self::POST_TYPE,
			'aflx_collaborator',
			[
				'type'          => 'integer',
				'single'        => false,
				'show_in_rest'  => false,
				'auth_callback' => static fn() => current_user_can( 'edit_others_posts' ),
			]
		);
	}
}
