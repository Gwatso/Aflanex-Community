<?php

namespace Aflanex\Community\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin settings (option `aflx_settings`). Secrets never live here:
 * SSO credentials stay in wp-config.php.
 */
final class Settings {

	public const OPTION = 'aflx_settings';

	public static function defaults(): array {
		return [
			'daily_project_limit'      => 5,
			'public_stats_threshold'   => 50,
			'delete_data_on_uninstall' => false,
		];
	}

	public static function all(): array {
		$saved = get_option( self::OPTION, [] );
		return array_merge( self::defaults(), is_array( $saved ) ? $saved : [] );
	}

	/**
	 * @return mixed
	 */
	public static function get( string $key ) {
		return self::all()[ $key ] ?? null;
	}

	public static function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : [];
		return [
			'daily_project_limit'      => max( 1, min( 50, absint( $input['daily_project_limit'] ?? 5 ) ) ),
			'public_stats_threshold'   => max( 0, min( 100000, absint( $input['public_stats_threshold'] ?? 50 ) ) ),
			'delete_data_on_uninstall' => ! empty( $input['delete_data_on_uninstall'] ),
		];
	}
}
