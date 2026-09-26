<?php

namespace Aflanex\Community\Privacy;

use Aflanex\Community\Integrations\ExternalIdentity;
use Aflanex\Community\Profiles\MemberProfile;
use Aflanex\Community\Projects\ProjectPostType;
use Aflanex\Community\Projects\Projects;

defined( 'ABSPATH' ) || exit;

/**
 * WordPress privacy integration:
 * - suggested privacy-policy text (Settings → Privacy → Policy guide)
 * - personal data exporter and eraser (Tools → Export / Erase Personal Data)
 *
 * FluentCommunity handles its own data (posts, comments, profile) separately.
 */
final class Privacy {

	private const PROFILE_KEYS = [
		'aflx_headline', 'aflx_location', 'aflx_learning_now', 'aflx_can_help_with',
		'aflx_open_to', 'aflx_links', 'aflx_directory_visible', 'aflx_account_source',
		'aflx_onboarding_dismissed',
	];

	public static function register(): void {
		add_action( 'admin_init', [ self::class, 'policy_content' ] );
		add_filter( 'wp_privacy_personal_data_exporters', [ self::class, 'exporters' ] );
		add_filter( 'wp_privacy_personal_data_erasers', [ self::class, 'erasers' ] );
	}

	public static function policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$content  = '<p class="privacy-policy-tutorial">' . esc_html__( 'Suggested text describing what the Aflanex Community plugin stores. Edit it to match your practices.', 'aflanex-community' ) . '</p>';
		$content .= '<strong class="privacy-policy-tutorial">' . esc_html__( 'Suggested text:', 'aflanex-community' ) . '</strong> ';
		$content .= '<p>' . esc_html__( 'When you complete your community portfolio we store the details you choose to add: headline, location, what you are learning, what you can help with, skills, interests, what you are open to and links. These are visible only to signed-in members, and you can hide yourself from the member directory at any time.', 'aflanex-community' ) . '</p>';
		$content .= '<p>' . esc_html__( 'Projects you share (including descriptions, links, images, collaborators and progress updates) are visible to signed-in members.', 'aflanex-community' ) . '</p>';
		$content .= '<p>' . esc_html__( 'If you sign in with your Erudify account, we store your Erudify account identifier so we can recognise you, plus the time of your last sign-in. We do not store your Erudify password, and we do not receive your learning records.', 'aflanex-community' ) . '</p>';

		wp_add_privacy_policy_content( __( 'Aflanex Community', 'aflanex-community' ), wp_kses_post( wpautop( $content, false ) ) );
	}

	public static function exporters( array $exporters ): array {
		$exporters['aflanex-community'] = [
			'exporter_friendly_name' => __( 'Aflanex Community', 'aflanex-community' ),
			'callback'               => [ self::class, 'export' ],
		];
		return $exporters;
	}

	public static function erasers( array $erasers ): array {
		$erasers['aflanex-community'] = [
			'eraser_friendly_name' => __( 'Aflanex Community', 'aflanex-community' ),
			'callback'             => [ self::class, 'erase' ],
		];
		return $erasers;
	}

	public static function export( string $email, int $page = 1 ): array {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return [ 'data' => [], 'done' => true ];
		}

		$profile = [];
		foreach ( self::PROFILE_KEYS as $key ) {
			$value = get_user_meta( $user->ID, $key, true );
			if ( '' !== $value && [] !== $value ) {
				$profile[] = [ 'name' => $key, 'value' => is_array( $value ) ? wp_json_encode( $value ) : (string) $value ];
			}
		}
		foreach ( [ ProjectPostType::TAX_SKILL => __( 'Skills', 'aflanex-community' ), ProjectPostType::TAX_AREA => __( 'Interests', 'aflanex-community' ) ] as $tax => $label ) {
			$names = wp_list_pluck( MemberProfile::terms( $user->ID, $tax ), 'name' );
			if ( $names ) {
				$profile[] = [ 'name' => $label, 'value' => implode( ', ', $names ) ];
			}
		}
		$erudify = ExternalIdentity::subject( $user->ID, ExternalIdentity::SOURCE_ERUDIFY );
		if ( $erudify ) {
			$profile[] = [ 'name' => __( 'Erudify account ID', 'aflanex-community' ), 'value' => $erudify ];
		}

		$data = [];
		if ( $profile ) {
			$data[] = [
				'group_id'    => 'aflanex-portfolio',
				'group_label' => __( 'Community portfolio', 'aflanex-community' ),
				'item_id'     => 'aflanex-portfolio-' . $user->ID,
				'data'        => $profile,
			];
		}

		foreach ( Projects::ids_for_member( $user->ID ) as $project_id ) {
			$post   = get_post( $project_id );
			$data[] = [
				'group_id'    => 'aflanex-projects',
				'group_label' => __( 'Community projects', 'aflanex-community' ),
				'item_id'     => 'aflanex-project-' . $project_id,
				'data'        => [
					[ 'name' => __( 'Title', 'aflanex-community' ), 'value' => get_the_title( $post ) ],
					[ 'name' => __( 'Summary', 'aflanex-community' ), 'value' => (string) $post->post_excerpt ],
					[ 'name' => __( 'Role', 'aflanex-community' ), 'value' => (int) $post->post_author === $user->ID ? __( 'Owner', 'aflanex-community' ) : __( 'Collaborator', 'aflanex-community' ) ],
					[ 'name' => __( 'URL', 'aflanex-community' ), 'value' => get_permalink( $post ) ],
				],
			];
		}

		return [ 'data' => $data, 'done' => true ];
	}

	/**
	 * Removes portfolio details and identity links. Projects are shared
	 * community content (often with collaborators), so they're retained
	 * and reported; an admin can delete them individually if required.
	 */
	public static function erase( string $email, int $page = 1 ): array {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return [ 'items_removed' => false, 'items_retained' => false, 'messages' => [], 'done' => true ];
		}

		$removed = false;
		$keys    = array_merge(
			self::PROFILE_KEYS,
			[ ProjectPostType::TAX_SKILL, ProjectPostType::TAX_AREA, ExternalIdentity::meta_key( ExternalIdentity::SOURCE_ERUDIFY ), ExternalIdentity::meta_key( ExternalIdentity::SOURCE_ERUDIFY ) . '_data' ]
		);
		foreach ( $keys as $key ) {
			if ( metadata_exists( 'user', $user->ID, $key ) ) {
				delete_user_meta( $user->ID, $key );
				$removed = true;
			}
		}

		// Remove them from other people's collaborator lists.
		foreach ( get_posts( [ 'post_type' => ProjectPostType::POST_TYPE, 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => -1, 'meta_query' => [ [ 'key' => 'aflx_collaborator', 'value' => $user->ID, 'type' => 'NUMERIC' ] ] ] ) as $project_id ) { // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			delete_post_meta( $project_id, 'aflx_collaborator', $user->ID );
			$removed = true;
		}

		$owned    = get_posts( [ 'post_type' => ProjectPostType::POST_TYPE, 'post_status' => 'any', 'author' => $user->ID, 'fields' => 'ids', 'posts_per_page' => -1 ] );
		$messages = [];
		if ( $owned ) {
			/* translators: %d: number of projects */
			$messages[] = sprintf( _n( '%d project owned by this member was kept because it is shared community content. Delete it under Projects if required.', '%d projects owned by this member were kept because they are shared community content. Delete them under Projects if required.', count( $owned ), 'aflanex-community' ), count( $owned ) );
		}

		return [
			'items_removed'  => $removed,
			'items_retained' => (bool) $owned,
			'messages'       => $messages,
			'done'           => true,
		];
	}
}
