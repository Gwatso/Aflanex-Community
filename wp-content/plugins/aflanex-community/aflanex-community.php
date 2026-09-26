<?php
/**
 * Plugin Name:       Aflanex Community
 * Description:       Community logic for Aflanex Community on top of FluentCommunity: projects, member portfolios, onboarding, integration identifiers and Erudify sign-in (OpenID Connect). Presentation lives in the Aflanex Community child theme.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.0
 * Author:            Aflanex
 * Text Domain:       aflanex-community
 * License:           GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

define( 'AFLANEX_COMMUNITY_VERSION', '1.0.0' );
define( 'AFLANEX_COMMUNITY_FILE', __FILE__ );
define( 'AFLANEX_COMMUNITY_DIR', plugin_dir_path( __FILE__ ) );
define( 'AFLANEX_COMMUNITY_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'Aflanex\\Community\\';
		if ( strncmp( $class, $prefix, strlen( $prefix ) ) !== 0 ) {
			return;
		}
		$file = AFLANEX_COMMUNITY_DIR . 'src/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

require AFLANEX_COMMUNITY_DIR . 'src/functions.php';

register_activation_hook( __FILE__, [ \Aflanex\Community\Setup\Installer::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ \Aflanex\Community\Setup\Installer::class, 'deactivate' ] );

add_action( 'plugins_loaded', [ \Aflanex\Community\Plugin::class, 'boot' ], 20 );
