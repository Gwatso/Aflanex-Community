<?php

namespace Aflanex\Community\Projects;

use Aflanex\Community\Profiles\MemberProfile;

defined( 'ABSPATH' ) || exit;

/**
 * Connects projects to FluentCommunity so we don't rebuild discussion:
 *
 * - When a project is first published, an announcement post is created
 *   in the Projects space. That post IS the project's discussion thread:
 *   comments, reactions, notifications, moderation and search all come
 *   from FluentCommunity.
 * - Progress updates are posted to the same space and appear in members'
 *   Home feed as meaningful activity.
 * - Collaborators are @mentioned, which triggers FluentCommunity's native
 *   mention notifications.
 */
final class CommunityBridge {

	public const SPACE_OPTION = 'aflx_projects_space_id';

	public static function register(): void {
		// Reserved for future: e.g. surfacing project context on feed items.
	}

	public static function space_id(): int {
		return (int) get_option( self::SPACE_OPTION, 0 );
	}

	/**
	 * @return \FluentCommunity\App\Models\Space|null
	 */
	public static function space() {
		$id = self::space_id();
		return $id ? \FluentCommunity\App\Models\Space::find( $id ) : null;
	}

	/**
	 * Create the announcement / discussion post for a project (once).
	 */
	public static function announce( int $project_id ): int {
		$existing = (int) get_post_meta( $project_id, 'aflx_feed_id', true );
		if ( $existing ) {
			return $existing;
		}

		$post  = get_post( $project_id );
		$space = self::space();
		if ( ! $post || ! $space || 'publish' !== $post->post_status ) {
			return 0;
		}

		$user_id = (int) $post->post_author;
		// Author and collaborators join the Projects space so they can post and be @mentioned there.
		foreach ( array_merge( [ $user_id ], Projects::collaborator_ids( $project_id ) ) as $member_id ) {
			\FluentCommunity\App\Services\Helper::addToSpace( $space, $member_id, 'member', 'by_automation' );
		}

		$lines   = [];
		$lines[] = $post->post_excerpt ? wp_strip_all_tags( $post->post_excerpt ) : '';
		$problem = (string) get_post_meta( $project_id, 'aflx_problem', true );
		if ( $problem ) {
			/* translators: %s: the problem a project addresses */
			$lines[] = sprintf( __( '**The problem:** %s', 'aflanex-community' ), wp_strip_all_tags( $problem ) );
		}

		$status  = (string) get_post_meta( $project_id, 'aflx_status', true ) ?: 'idea';
		$meta    = [ sprintf( '**%s:** %s', __( 'Status', 'aflanex-community' ), Projects::status_label( $status ) ) ];
		$looking = (string) get_post_meta( $project_id, 'aflx_looking_for', true );
		if ( $looking ) {
			$meta[] = sprintf( '**%s:** %s', __( 'Looking for', 'aflanex-community' ), wp_strip_all_tags( $looking ) );
		}
		$lines[] = implode( ' · ', $meta );

		$mentions = self::mentions( Projects::collaborator_ids( $project_id ) );
		if ( $mentions ) {
			/* translators: %s: list of @mentions */
			$lines[] = sprintf( __( 'Building with %s', 'aflanex-community' ), $mentions );
		}

		if ( get_post_meta( $project_id, 'aflx_feedback_requested', true ) ) {
			$lines[] = __( 'Feedback is welcome. Reply below with what works, what doesn’t and what you’d try next.', 'aflanex-community' );
		}

		/* translators: %s: project URL */
		$lines[] = sprintf( __( '[View the project →](%s)', 'aflanex-community' ), get_permalink( $post ) );

		$feed = \FluentCommunity\App\Services\FeedsHelper::createFeed(
			[
				'title'    => get_the_title( $post ),
				'message'  => implode( "\n\n", array_filter( $lines ) ),
				'user_id'  => $user_id,
				'space_id' => $space->id,
			]
		);

		if ( is_wp_error( $feed ) || ! $feed ) {
			return 0;
		}

		$feed->updateCustomMeta( 'aflx_project_id', $project_id );
		update_post_meta( $project_id, 'aflx_feed_id', (int) $feed->id );

		do_action( 'aflanex/project/announced', $project_id, (int) $feed->id );

		return (int) $feed->id;
	}

	/**
	 * @return int|\WP_Error Feed ID.
	 */
	public static function post_update( int $project_id, int $user_id, string $text ) {
		$space = self::space();
		$post  = get_post( $project_id );
		if ( ! $space || ! $post ) {
			return new \WP_Error( 'aflx_no_space', 'Projects space is not configured.' );
		}

		\FluentCommunity\App\Services\Helper::addToSpace( $space, $user_id, 'member', 'by_automation' );

		$message = $text . "\n\n" . sprintf(
			/* translators: 1: project title, 2: project URL */
			__( 'Update on [%1$s](%2$s)', 'aflanex-community' ),
			get_the_title( $post ),
			get_permalink( $post )
		);

		$feed = \FluentCommunity\App\Services\FeedsHelper::createFeed(
			[
				'message'  => $message,
				'user_id'  => $user_id,
				'space_id' => $space->id,
			]
		);

		if ( is_wp_error( $feed ) || ! $feed ) {
			return is_wp_error( $feed ) ? $feed : new \WP_Error( 'aflx_feed_failed', 'Could not post update.' );
		}

		$feed->updateCustomMeta( 'aflx_project_id', $project_id );

		$ids   = get_post_meta( $project_id, 'aflx_update_feed_ids', true );
		$ids   = is_array( $ids ) ? $ids : [];
		$ids[] = (int) $feed->id;
		update_post_meta( $project_id, 'aflx_update_feed_ids', array_values( array_unique( array_map( 'intval', $ids ) ) ) );

		// Touch the project so "recently active" ordering reflects progress.
		wp_update_post( [ 'ID' => $project_id, 'post_modified' => current_time( 'mysql' ), 'post_modified_gmt' => current_time( 'mysql', true ) ] );

		do_action( 'aflanex/project/updated', $project_id, (int) $feed->id, $user_id );

		return (int) $feed->id;
	}

	public static function discussion( int $project_id ): ?array {
		$feed_id = (int) get_post_meta( $project_id, 'aflx_feed_id', true );
		if ( ! $feed_id || ! class_exists( '\FluentCommunity\App\Models\Feed' ) ) {
			return null;
		}
		$feed = \FluentCommunity\App\Models\Feed::where( 'id', $feed_id )->where( 'status', 'published' )->first();
		if ( ! $feed ) {
			return null;
		}
		return [
			'url'      => $feed->getPermalink(),
			'comments' => (int) $feed->comments_count,
		];
	}

	public static function updates( int $project_id, int $limit = 10 ): array {
		$ids = get_post_meta( $project_id, 'aflx_update_feed_ids', true );
		if ( ! is_array( $ids ) || ! $ids || ! class_exists( '\FluentCommunity\App\Models\Feed' ) ) {
			return [];
		}

		$feeds = \FluentCommunity\App\Models\Feed::whereIn( 'id', array_map( 'intval', $ids ) )
			->where( 'status', 'published' )
			->orderBy( 'created_at', 'DESC' )
			->limit( $limit )
			->get();

		$out = [];
		foreach ( $feeds as $feed ) {
			$out[] = [
				'author'   => MemberProfile::card( (int) $feed->user_id ),
				// Already sanitised by FluentCommunity (wp_kses_post) at creation.
				'html'     => (string) $feed->message_rendered,
				// FluentCommunity stores site-local time; convert to a real timestamp.
				'time'     => strtotime( get_gmt_from_date( (string) $feed->created_at ) . ' UTC' ),
				'url'      => $feed->getPermalink(),
				'comments' => (int) $feed->comments_count,
			];
		}
		return $out;
	}

	/**
	 * @param int[] $user_ids
	 */
	private static function mentions( array $user_ids ): string {
		$handles = [];
		foreach ( $user_ids as $id ) {
			$card = MemberProfile::card( $id );
			if ( $card ) {
				$handles[] = '@' . $card['username'];
			}
		}
		return implode( ' ', $handles );
	}
}
