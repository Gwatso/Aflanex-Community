<?php

namespace Aflanex\Community\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Updates from GitHub releases, using WordPress's own mechanism for
 * plugins that declare an `Update URI` (WP 5.8+). This gives the plugin
 * the same behaviour as directory plugins: update notices, one-click
 * updates, the auto-updates toggle and a "View details" modal.
 *
 * Release convention: tag `vX.Y.Z` with the built asset
 * `aflanex-community-plugin.zip` attached (see dist/build.py). Only assets
 * from this repository's release download URLs are ever installed.
 */
final class Updater {

	public const SLUG      = 'aflanex-community';
	public const ASSET     = 'aflanex-community-plugin.zip';
	private const CACHE    = 'aflx_github_release';
	private const CACHE_TTL = 6 * HOUR_IN_SECONDS;

	public static function register(): void {
		add_filter( 'update_plugins_github.com', [ self::class, 'check' ], 10, 3 );
		add_filter( 'plugins_api', [ self::class, 'plugin_information' ], 20, 3 );
		add_action( 'upgrader_process_complete', [ self::class, 'flush_cache' ], 10, 0 );
	}

	public static function repo(): string {
		return (string) apply_filters( 'aflanex/updater/repository', 'Gwatso/Aflanex-Community' );
	}

	public static function repo_url(): string {
		return 'https://github.com/' . self::repo();
	}

	public static function basename(): string {
		return plugin_basename( AFLANEX_COMMUNITY_FILE );
	}

	public static function flush_cache(): void {
		delete_site_transient( self::CACHE );
	}

	/**
	 * Latest published (non-draft, non-prerelease) release, cached.
	 *
	 * @return array{version: string, package: string, notes: string, published: string}|null
	 */
	public static function latest_release(): ?array {
		$cached = get_site_transient( self::CACHE );
		if ( is_array( $cached ) ) {
			return $cached['release'] ?? null;
		}

		$release  = null;
		$response = wp_remote_get(
			'https://api.github.com/repos/' . self::repo() . '/releases/latest',
			[
				'timeout' => 8,
				'headers' => [ 'Accept' => 'application/vnd.github+json', 'User-Agent' => 'Aflanex-Community-Updater' ],
			]
		);

		if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
			$data    = json_decode( (string) wp_remote_retrieve_body( $response ), true );
			$version = is_array( $data ) ? ltrim( (string) ( $data['tag_name'] ?? '' ), 'vV' ) : '';
			$package = '';
			$prefix  = self::repo_url() . '/releases/download/';

			foreach ( (array) ( $data['assets'] ?? [] ) as $asset ) {
				$url = (string) ( $asset['browser_download_url'] ?? '' );
				if ( self::ASSET === ( $asset['name'] ?? '' ) && str_starts_with( $url, $prefix ) ) {
					$package = $url;
					break;
				}
			}

			if ( preg_match( '/^\d+\.\d+(\.\d+)?$/', $version ) ) {
				$release = [
					'version'   => $version,
					'package'   => $package,
					'notes'     => (string) ( $data['body'] ?? '' ),
					'published' => (string) ( $data['published_at'] ?? '' ),
				];
			}
		}

		// Cache misses too, so a missing release doesn't hit the API on every admin load.
		set_site_transient( self::CACHE, [ 'release' => $release ], $release ? self::CACHE_TTL : HOUR_IN_SECONDS );

		return $release;
	}

	/**
	 * @param array|false $update
	 * @param array       $plugin_data
	 * @param string      $plugin_file
	 * @return array|false
	 */
	public static function check( $update, $plugin_data, $plugin_file ) {
		if ( self::basename() !== $plugin_file ) {
			return $update;
		}

		$release     = self::latest_release();
		$has_update  = $release && $release['package'] && version_compare( $release['version'], AFLANEX_COMMUNITY_VERSION, '>' );
		$readme_meta = Readme::headers();

		// Returning current-version info (no package) lists the plugin as
		// "no update", which is what enables the auto-updates toggle.
		return [
			'id'           => $plugin_data['UpdateURI'] ?? self::repo_url(),
			'slug'         => self::SLUG,
			'plugin'       => $plugin_file,
			'version'      => $has_update ? $release['version'] : AFLANEX_COMMUNITY_VERSION,
			'url'          => self::repo_url(),
			'package'      => $has_update ? $release['package'] : '',
			'tested'       => $readme_meta['tested'] ?? '',
			'requires'     => $readme_meta['requires'] ?? '',
			'requires_php' => $readme_meta['requires_php'] ?? '',
			'icons'        => [],
		];
	}

	/**
	 * Data for the "View details" modal.
	 *
	 * @param false|object|array $result
	 * @param string             $action
	 * @param object             $args
	 * @return false|object|array
	 */
	public static function plugin_information( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || ! isset( $args->slug ) || self::SLUG !== $args->slug ) {
			return $result;
		}

		$release  = self::latest_release();
		$meta     = Readme::headers();
		$sections = Readme::sections_html();

		if ( $release && $release['notes'] && version_compare( $release['version'], AFLANEX_COMMUNITY_VERSION, '>' ) ) {
			$sections = [ 'changelog' => '<h4>' . esc_html( $release['version'] ) . '</h4>' . wp_kses_post( wpautop( esc_html( $release['notes'] ) ) ) ] + $sections;
		}

		$has_update = $release && $release['package'] && version_compare( $release['version'], AFLANEX_COMMUNITY_VERSION, '>' );

		return (object) [
			'name'          => 'Aflanex Community',
			'slug'          => self::SLUG,
			'version'       => $has_update ? $release['version'] : AFLANEX_COMMUNITY_VERSION,
			'author'        => '<a href="' . esc_url( self::repo_url() ) . '">Aflanex</a>',
			'homepage'      => self::repo_url(),
			'requires'      => $meta['requires'] ?? '',
			'tested'        => $meta['tested'] ?? '',
			'requires_php'  => $meta['requires_php'] ?? '',
			'last_updated'  => $release['published'] ?? '',
			'sections'      => $sections,
			'download_link' => $has_update ? $release['package'] : '',
			'banners'       => [],
		];
	}
}
