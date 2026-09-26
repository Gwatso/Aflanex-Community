<?php

namespace Aflanex\Community\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Template loader. A theme can override any template by copying it to
 * `{theme}/aflanex/{name}.php`; otherwise the plugin's copy is used.
 */
final class View {

	public static function render( string $name, array $vars = [] ): void {
		$template = locate_template( 'aflanex/' . $name . '.php' );
		if ( ! $template ) {
			$template = AFLANEX_COMMUNITY_DIR . 'templates/' . $name . '.php';
		}
		if ( ! is_readable( $template ) ) {
			return;
		}

		( static function ( string $__template, array $__vars ): void {
			extract( $__vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
			include $__template;
		} )( $template, $vars );
	}

	public static function capture( string $name, array $vars = [] ): string {
		ob_start();
		self::render( $name, $vars );
		return (string) ob_get_clean();
	}

	/**
	 * Flash notices travel as a short code in the query string after a
	 * POST → redirect, so no session storage is needed.
	 */
	public static function notice_from_request(): ?array {
		$code = isset( $_GET['aflx_notice'] ) ? sanitize_key( wp_unslash( $_GET['aflx_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $code ) {
			return null;
		}

		$notices = [
			'project_created'  => [ 'success', __( 'Your project is live. It has been shared in the Projects space so people can find it.', 'aflanex-community' ) ],
			'project_saved'    => [ 'success', __( 'Project updated.', 'aflanex-community' ) ],
			'update_posted'    => [ 'success', __( 'Update posted. Nice momentum.', 'aflanex-community' ) ],
			'profile_saved'    => [ 'success', __( 'Your portfolio details are saved.', 'aflanex-community' ) ],
			'not_allowed'      => [ 'danger', __( 'You don’t have permission to do that.', 'aflanex-community' ) ],
			'invalid'          => [ 'danger', __( 'Something in the form needs attention. Please check and try again.', 'aflanex-community' ) ],
			'expired'          => [ 'warning', __( 'That form expired. Please try again.', 'aflanex-community' ) ],
			'upload_failed'    => [ 'warning', __( 'The image couldn’t be uploaded. Use a JPG, PNG or WebP under 5 MB.', 'aflanex-community' ) ],
			'update_empty'     => [ 'warning', __( 'Write a short update before posting.', 'aflanex-community' ) ],
		];

		if ( ! isset( $notices[ $code ] ) ) {
			return null;
		}

		return [
			'type'    => $notices[ $code ][0],
			'message' => $notices[ $code ][1],
		];
	}

	public static function icon( string $name, int $size = 20 ): string {
		$paths = [
			'plus'     => '<path d="M12 5v14M5 12h14"/>',
			'search'   => '<circle cx="11" cy="11" r="6.5"/><path d="m20 20-3.8-3.8"/>',
			'build'    => '<path d="M4 7.5 12 3l8 4.5v9L12 21l-8-4.5z"/><path d="m4 7.5 8 4.5 8-4.5M12 12v9"/>',
			'people'   => '<circle cx="9" cy="8" r="3.25"/><circle cx="17" cy="9.5" r="2.5"/><path d="M3.5 19c.6-3 2.9-4.75 5.5-4.75S13.9 16 14.5 19"/><path d="M15 14.4c2.6-.2 4.6 1.3 5.1 4.1"/>',
			'chat'     => '<path d="M5 18.5V6.5A1.5 1.5 0 0 1 6.5 5h11A1.5 1.5 0 0 1 19 6.5v8a1.5 1.5 0 0 1-1.5 1.5H8.5z"/>',
			'link'     => '<path d="M10 14a4 4 0 0 0 5.66 0l3-3a4 4 0 0 0-5.66-5.66l-1 1"/><path d="M14 10a4 4 0 0 0-5.66 0l-3 3a4 4 0 0 0 5.66 5.66l1-1"/>',
			'pin'      => '<path d="M12 21s-6.5-5.6-6.5-11a6.5 6.5 0 0 1 13 0c0 5.4-6.5 11-6.5 11z"/><circle cx="12" cy="10" r="2.25"/>',
			'edit'     => '<path d="M4 20h4L19 9l-4-4L4 16z"/>',
			'arrow'    => '<path d="M5 12h14M13 6l6 6-6 6"/>',
			'back'     => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
			'spark'    => '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5 18 18M6 18l2.5-2.5M15.5 8.5 18 6"/>',
			'check'    => '<path d="M5 12.5 9.5 17 19 7.5"/>',
		];

		return sprintf(
			'<svg aria-hidden="true" focusable="false" width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">%2$s</svg>',
			$size,
			$paths[ $name ] ?? ''
		);
	}
}
