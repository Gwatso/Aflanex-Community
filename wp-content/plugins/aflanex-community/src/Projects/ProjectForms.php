<?php

namespace Aflanex\Community\Projects;

use Aflanex\Community\Pages\Pages;
use Aflanex\Community\Profiles\MemberProfile;

defined( 'ABSPATH' ) || exit;

/**
 * Front-end form handlers (admin-post.php). Every action checks: logged-in
 * community member → nonce → object-level permission → validation.
 */
final class ProjectForms {

	private const MAX_UPLOAD      = 5 * MB_IN_BYTES;
	private const MAX_COLLABS     = 10;
	private const MAX_LINKS       = 3;
	private const OLD_INPUT_TTL   = 600;

	public static function register(): void {
		add_action( 'admin_post_aflx_save_project', [ self::class, 'save_project' ] );
		add_action( 'admin_post_aflx_project_update', [ self::class, 'post_update' ] );
		add_action( 'admin_post_nopriv_aflx_save_project', [ self::class, 'deny' ] );
		add_action( 'admin_post_nopriv_aflx_project_update', [ self::class, 'deny' ] );
	}

	private static function daily_limit(): int {
		return (int) \Aflanex\Community\Support\Settings::get( 'daily_project_limit' );
	}

	public static function deny(): void {
		wp_safe_redirect( Pages::sign_in_url( Pages::projects_url() ) );
		exit;
	}

	private static function back( string $url, string $notice ): void {
		wp_safe_redirect( add_query_arg( 'aflx_notice', $notice, $url ) );
		exit;
	}

	/**
	 * Previous input + field errors after a failed submit.
	 */
	public static function old_input(): array {
		$user_id = get_current_user_id();
		$data    = $user_id ? get_transient( 'aflx_project_form_' . $user_id ) : false;
		if ( $data ) {
			delete_transient( 'aflx_project_form_' . $user_id );
		}
		return is_array( $data ) ? $data : [ 'input' => [], 'errors' => [] ];
	}

	/* ------------------------------------------------------------------ */
	/* Create / edit                                                       */
	/* ------------------------------------------------------------------ */

	public static function save_project(): void {
		$user_id    = get_current_user_id();
		$project_id = isset( $_POST['project_id'] ) ? absint( $_POST['project_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked below.
		$form_url   = Pages::project_form_url( $project_id );

		if ( ! Projects::can_create( $user_id ) ) {
			self::back( Pages::projects_url(), 'not_allowed' );
		}
		if ( ! isset( $_POST['_aflx_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_aflx_nonce'] ) ), 'aflx_save_project_' . $project_id ) ) {
			self::back( $form_url, 'expired' );
		}

		$existing = null;
		if ( $project_id ) {
			$existing = get_post( $project_id );
			if ( ! $existing || ProjectPostType::POST_TYPE !== $existing->post_type || ! Projects::can_edit( $project_id, $user_id ) ) {
				self::back( Pages::projects_url(), 'not_allowed' );
			}
		} elseif ( ! current_user_can( 'edit_others_posts' ) && self::created_today( $user_id ) >= self::daily_limit() ) {
			self::back( Pages::projects_url(), 'not_allowed' );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified above.
		$in = [
			'title'       => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
			'summary'     => isset( $_POST['summary'] ) ? sanitize_text_field( wp_unslash( $_POST['summary'] ) ) : '',
			'description' => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
			'problem'     => isset( $_POST['problem'] ) ? sanitize_textarea_field( wp_unslash( $_POST['problem'] ) ) : '',
			'approach'    => isset( $_POST['approach'] ) ? sanitize_textarea_field( wp_unslash( $_POST['approach'] ) ) : '',
			'looking_for' => isset( $_POST['looking_for'] ) ? sanitize_text_field( wp_unslash( $_POST['looking_for'] ) ) : '',
			'status'      => isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'idea',
			'area'        => isset( $_POST['area'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['area'] ) ) : [],
			'skills'      => isset( $_POST['skills'] ) ? sanitize_text_field( wp_unslash( $_POST['skills'] ) ) : '',
			'collabs'     => isset( $_POST['collaborators'] ) ? sanitize_text_field( wp_unslash( $_POST['collaborators'] ) ) : '',
			'feedback'    => ! empty( $_POST['feedback_requested'] ),
			'links'       => [],
		];

		$raw_links = isset( $_POST['links'] ) && is_array( $_POST['links'] ) ? wp_unslash( $_POST['links'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised per field below.
		foreach ( array_slice( $raw_links, 0, self::MAX_LINKS ) as $link ) {
			$url   = isset( $link['url'] ) ? esc_url_raw( trim( (string) $link['url'] ), [ 'http', 'https' ] ) : '';
			$label = isset( $link['label'] ) ? sanitize_text_field( (string) $link['label'] ) : '';
			if ( $url ) {
				$in['links'][] = [ 'label' => mb_substr( $label ?: wp_parse_url( $url, PHP_URL_HOST ), 0, 60 ), 'url' => $url ];
			}
		}
		// phpcs:enable

		$errors = [];
		if ( mb_strlen( $in['title'] ) < 3 || mb_strlen( $in['title'] ) > 120 ) {
			$errors['title'] = __( 'Give your project a name (3–120 characters).', 'aflanex-community' );
		}
		if ( '' === $in['summary'] || mb_strlen( $in['summary'] ) > 160 ) {
			$errors['summary'] = __( 'Describe the project in one line (up to 160 characters).', 'aflanex-community' );
		}
		if ( ! isset( ProjectPostType::statuses()[ $in['status'] ] ) ) {
			$errors['status'] = __( 'Choose a status.', 'aflanex-community' );
		}
		foreach ( [ 'description' => 5000, 'problem' => 1500, 'approach' => 1500 ] as $field => $max ) {
			if ( mb_strlen( $in[ $field ] ) > $max ) {
				/* translators: %d: character limit */
				$errors[ $field ] = sprintf( __( 'Keep this under %d characters.', 'aflanex-community' ), $max );
			}
		}

		$owner_id     = $existing ? (int) $existing->post_author : $user_id;
		$collab_ids   = self::resolve_collaborators( $in['collabs'], $owner_id, $errors );
		$can_manage   = ! $existing || $owner_id === $user_id || current_user_can( 'edit_others_posts' );

		if ( $errors ) {
			set_transient( 'aflx_project_form_' . $user_id, [ 'input' => $in, 'errors' => $errors ], self::OLD_INPUT_TTL );
			self::back( $form_url, 'invalid' );
		}

		$postarr = [
			'post_type'    => ProjectPostType::POST_TYPE,
			'post_title'   => $in['title'],
			'post_excerpt' => $in['summary'],
			'post_content' => $in['description'],
			'post_status'  => 'publish',
		];

		if ( $existing ) {
			$postarr['ID'] = $project_id;
			$result        = wp_update_post( wp_slash( $postarr ), true );
		} else {
			$postarr['post_author'] = $user_id;
			$result                 = wp_insert_post( wp_slash( $postarr ), true );
		}

		if ( is_wp_error( $result ) ) {
			set_transient( 'aflx_project_form_' . $user_id, [ 'input' => $in, 'errors' => [ 'title' => __( 'We couldn’t save the project. Please try again.', 'aflanex-community' ) ] ], self::OLD_INPUT_TTL );
			self::back( $form_url, 'invalid' );
		}

		$project_id = (int) $result;

		update_post_meta( $project_id, 'aflx_status', $in['status'] );
		update_post_meta( $project_id, 'aflx_problem', $in['problem'] );
		update_post_meta( $project_id, 'aflx_approach', $in['approach'] );
		update_post_meta( $project_id, 'aflx_looking_for', $in['looking_for'] );
		update_post_meta( $project_id, 'aflx_links', $in['links'] );
		update_post_meta( $project_id, 'aflx_feedback_requested', $in['feedback'] || 'ready_for_feedback' === $in['status'] );

		if ( ! $existing ) {
			update_post_meta( $project_id, 'aflx_data_source', 'community' );
		}

		if ( $can_manage ) {
			delete_post_meta( $project_id, 'aflx_collaborator' );
			foreach ( $collab_ids as $collab_id ) {
				add_post_meta( $project_id, 'aflx_collaborator', $collab_id );
			}
		}

		wp_set_object_terms( $project_id, MemberProfile::existing_term_ids( $in['area'], ProjectPostType::TAX_AREA ), ProjectPostType::TAX_AREA );
		wp_set_object_terms( $project_id, MemberProfile::skill_term_ids( $in['skills'] ), ProjectPostType::TAX_SKILL );

		$notice = $existing ? 'project_saved' : 'project_created';
		if ( ! empty( $_FILES['cover']['name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( ! self::handle_cover_upload( $project_id, $user_id ) ) {
				$notice = 'upload_failed';
			}
		}

		if ( ! $existing ) {
			CommunityBridge::announce( $project_id );
			do_action( 'aflanex/project/created', $project_id, $user_id );
		} else {
			do_action( 'aflanex/project/saved', $project_id, $user_id );
		}

		self::back( get_permalink( $project_id ), $notice );
	}

	/**
	 * @param array<string,string> $errors
	 * @return int[]
	 */
	private static function resolve_collaborators( string $raw, int $owner_id, array &$errors ): array {
		$ids     = [];
		$missing = [];
		$names   = array_filter( array_map( static fn( $n ) => ltrim( trim( $n ), '@' ), explode( ',', $raw ) ) );

		foreach ( array_slice( array_unique( $names ), 0, self::MAX_COLLABS ) as $username ) {
			$user = MemberProfile::user_by_username( $username );
			$card = $user ? MemberProfile::card( $user->ID ) : null;
			if ( ! $card || ! $card['active'] ) {
				$missing[] = $username;
				continue;
			}
			if ( $user->ID !== $owner_id ) {
				$ids[] = (int) $user->ID;
			}
		}

		if ( $missing ) {
			/* translators: %s: list of usernames */
			$errors['collaborators'] = sprintf( __( 'We couldn’t find: %s. Use community usernames, separated by commas.', 'aflanex-community' ), implode( ', ', $missing ) );
		}

		return array_values( array_unique( $ids ) );
	}

	private static function created_today( int $user_id ): int {
		$q = new \WP_Query(
			[
				'post_type'      => ProjectPostType::POST_TYPE,
				'post_status'    => 'any',
				'author'         => $user_id,
				'date_query'     => [ [ 'after' => '24 hours ago' ] ],
				'fields'         => 'ids',
				'posts_per_page' => self::daily_limit() + 1,
				'no_found_rows'  => true,
			]
		);
		return count( $q->posts );
	}

	private static function handle_cover_upload( int $project_id, int $user_id ): bool {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by caller.
		$file = $_FILES['cover']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( ! empty( $file['error'] ) || empty( $file['tmp_name'] ) || (int) $file['size'] > self::MAX_UPLOAD ) {
			return false;
		}

		$allowed = [ 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' ];
		$check   = wp_check_filetype_and_ext( $file['tmp_name'], sanitize_file_name( $file['name'] ), $allowed );
		if ( empty( $check['ext'] ) || empty( $check['type'] ) || ! @getimagesize( $file['tmp_name'] ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return false;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_id = media_handle_upload(
			'cover',
			$project_id,
			[ 'post_author' => $user_id ],
			[ 'test_form' => false, 'mimes' => $allowed ]
		);
		// phpcs:enable

		if ( is_wp_error( $attachment_id ) ) {
			return false;
		}

		$old = (int) get_post_thumbnail_id( $project_id );
		set_post_thumbnail( $project_id, $attachment_id );
		if ( $old && $old !== (int) $attachment_id && (int) get_post_field( 'post_parent', $old ) === $project_id ) {
			wp_delete_attachment( $old, true );
		}

		return true;
	}

	/* ------------------------------------------------------------------ */
	/* Progress updates                                                    */
	/* ------------------------------------------------------------------ */

	public static function post_update(): void {
		$user_id    = get_current_user_id();
		$project_id = isset( $_POST['project_id'] ) ? absint( $_POST['project_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$url        = $project_id ? get_permalink( $project_id ) : Pages::projects_url();

		if ( ! $project_id || ! Projects::can_edit( $project_id, $user_id ) ) {
			self::back( $url ?: Pages::projects_url(), 'not_allowed' );
		}
		if ( ! isset( $_POST['_aflx_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_aflx_nonce'] ) ), 'aflx_project_update_' . $project_id ) ) {
			self::back( $url, 'expired' );
		}

		$text = isset( $_POST['update'] ) ? trim( sanitize_textarea_field( wp_unslash( $_POST['update'] ) ) ) : '';
		if ( mb_strlen( $text ) < 3 ) {
			self::back( $url, 'update_empty' );
		}
		$text = mb_substr( $text, 0, 3000 );

		$new_status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		if ( $new_status && isset( ProjectPostType::statuses()[ $new_status ] ) ) {
			update_post_meta( $project_id, 'aflx_status', $new_status );
		}

		// Make sure the discussion thread exists (e.g. projects created in wp-admin).
		CommunityBridge::announce( $project_id );

		$result = CommunityBridge::post_update( $project_id, $user_id, $text );
		self::back( $url, is_wp_error( $result ) ? 'invalid' : 'update_posted' );
	}
}
