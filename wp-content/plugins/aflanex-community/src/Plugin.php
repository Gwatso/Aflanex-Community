<?php

namespace Aflanex\Community;

defined( 'ABSPATH' ) || exit;

/**
 * Composition root. Each module registers its own hooks; modules that
 * depend on FluentCommunity only load when it's active.
 */
final class Plugin {

	public static function boot(): void {
		add_action( 'init', [ self::class, 'load_textdomain' ], 0 );

		// Admin, updates and privacy work even if FluentCommunity is inactive.
		Admin\AdminPage::register();
		Admin\Updater::register();
		Privacy\Privacy::register();

		Security\Hardening::register();
		Projects\ProjectPostType::register();
		Profiles\MemberProfile::register();
		Identity\Sso::register();
		Pages\Pages::register();
		Setup\Installer::maybe_upgrade();

		if ( ! self::has_fluent_community() ) {
			add_action( 'admin_notices', [ self::class, 'missing_dependency_notice' ] );
			return;
		}

		Portal\Portal::register();
		Portal\Copy::register();
		Projects\ProjectForms::register();
		Projects\CommunityBridge::register();
	}

	public static function load_textdomain(): void {
		load_plugin_textdomain( 'aflanex-community', false, dirname( plugin_basename( AFLANEX_COMMUNITY_FILE ) ) . '/languages' );
	}

	public static function has_fluent_community(): bool {
		return defined( 'FLUENT_COMMUNITY_PLUGIN_VERSION' ) && class_exists( '\FluentCommunity\App\Services\Helper' );
	}

	public static function missing_dependency_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p>' . esc_html__( 'Aflanex Community needs FluentCommunity to be active. Community features are paused until it is.', 'aflanex-community' ) . '</p></div>';
	}
}
