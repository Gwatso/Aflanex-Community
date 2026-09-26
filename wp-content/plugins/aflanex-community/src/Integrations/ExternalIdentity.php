<?php

namespace Aflanex\Community\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Maps a WordPress user to identities in other Aflanex systems.
 *
 * WordPress user IDs are local implementation details and are never sent
 * to, or assumed by, other systems. Each external system is a "source"
 * with its own stable subject identifier:
 *
 *   source   : 'erudify' (OIDC `sub` from Erudify/Supabase), later e.g. 'aflanex_id'
 *   subject  : the external user ID, stored verbatim
 *
 * Storage (user meta):
 *   aflx_ext_{source}           → subject (indexed lookup)
 *   aflx_ext_{source}_data      → [ issuer, linked_at, last_login_at, data_source, sync_status, last_synced_at ]
 */
final class ExternalIdentity {

	public const SOURCE_ERUDIFY = 'erudify';

	public static function meta_key( string $source ): string {
		return 'aflx_ext_' . sanitize_key( $source );
	}

	public static function find_user( string $source, string $subject ): ?\WP_User {
		if ( '' === $subject ) {
			return null;
		}
		$users = get_users(
			[
				'meta_key'   => self::meta_key( $source ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => $subject, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'number'     => 2,
			]
		);
		// A subject must map to exactly one account.
		return 1 === count( $users ) ? $users[0] : null;
	}

	public static function subject( int $user_id, string $source ): string {
		return (string) get_user_meta( $user_id, self::meta_key( $source ), true );
	}

	public static function link( int $user_id, string $source, string $subject, array $data = [] ): void {
		update_user_meta( $user_id, self::meta_key( $source ), $subject );

		$existing = get_user_meta( $user_id, self::meta_key( $source ) . '_data', true );
		$existing = is_array( $existing ) ? $existing : [];
		if ( empty( $existing['linked_at'] ) ) {
			$existing['linked_at'] = gmdate( 'c' );
		}

		update_user_meta( $user_id, self::meta_key( $source ) . '_data', array_merge( $existing, $data, [ 'data_source' => $source ] ) );

		do_action( 'aflanex/identity/linked', $user_id, $source, $subject );
	}

	public static function data( int $user_id, string $source ): array {
		$data = get_user_meta( $user_id, self::meta_key( $source ) . '_data', true );
		return is_array( $data ) ? $data : [];
	}

	public static function is_linked( int $user_id, string $source ): bool {
		return '' !== self::subject( $user_id, $source );
	}
}
