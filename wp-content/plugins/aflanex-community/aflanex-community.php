<?php
/**
 * Plugin Name:       Aflanex Community
 * Plugin URI:        https://github.com/Gwatso/Aflanex-Community
 * Description:       Projects, member portfolios, people discovery, onboarding and Erudify sign-in for Aflanex Community, built on FluentCommunity.
 * Version:           1.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.0
 * Requires Plugins:  fluent-community
 * Author:            Aflanex
 * Author URI:        https://aflanex.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       aflanex-community
 * Domain Path:       /languages
 * Update URI:        https://github.com/Gwatso/Aflanex-Community
 *
 * @package AflanexCommunity
 */

defined( 'ABSPATH' ) || exit;

define( 'AFLANEX_COMMUNITY_VERSION', '1.1.0' );
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
