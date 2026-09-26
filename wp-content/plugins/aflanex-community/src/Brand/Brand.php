<?php

namespace Aflanex\Community\Brand;

defined( 'ABSPATH' ) || exit;

/**
 * Canonical brand data. The visible product brand is "Aflanex Community",
 * independent of the (temporary) domain.
 *
 * Logo replacement: drop logo.svg (and optionally logo-dark.svg) into the
 * child theme's assets/brand/ folder. The theme brand mark, the portal
 * header and emails then all use it. No code changes are needed.
 */
final class Brand {

	public static function get(): array {
		$brand = [
			'name'      => 'Aflanex',
			'product'   => 'Community',
			'full_name' => 'Aflanex Community',
			'tagline'   => __( 'Build what’s next together.', 'aflanex-community' ),
			'logo'      => self::logo(),
			'logo_dark' => self::logo( true ),
		];

		/**
		 * Filter the brand data (e.g. from a future central brand service).
		 */
		return apply_filters( 'aflanex/brand', $brand );
	}

	/**
	 * URL of the approved logo file, or '' while the wordmark is in use.
	 */
	public static function logo( bool $dark = false ): string {
		$file = $dark ? 'logo-dark.svg' : 'logo.svg';
		$path = get_stylesheet_directory() . '/assets/brand/' . $file;

		if ( file_exists( $path ) ) {
			return get_stylesheet_directory_uri() . '/assets/brand/' . $file;
		}

		return $dark ? self::logo( false ) : '';
	}
}
