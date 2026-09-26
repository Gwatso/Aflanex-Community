<?php

namespace Aflanex\Community\Admin;

use Aflanex\Community\Identity\Sso;
use Aflanex\Community\Pages\Pages;
use Aflanex\Community\Projects\CommunityBridge;
use Aflanex\Community\Projects\Projects;
use Aflanex\Community\Setup\Installer;
use Aflanex\Community\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Settings → Aflanex Community: health status, settings and repair tools.
 * Everyday community management (spaces, members, moderation, menus)
 * stays in FluentCommunity's own admin; this screen doesn't duplicate it.
 */
final class AdminPage {

	public const SLUG = 'aflanex-community';

	public static function register(): void {
		add_action( 'admin_menu', [ self::class, 'menu' ] );
		add_action( 'admin_init', [ self::class, 'settings' ] );
		add_action( 'admin_post_aflx_repair_setup', [ self::class, 'repair' ] );
		add_filter( 'plugin_action_links_' . plugin_basename( AFLANEX_COMMUNITY_FILE ), [ self::class, 'action_links' ] );
		add_filter( 'plugin_row_meta', [ self::class, 'row_meta' ], 10, 2 );
	}

	public static function url(): string {
		return admin_url( 'options-general.php?page=' . self::SLUG );
	}

	public static function docs_url(): string {
		return Updater::repo_url() . '/blob/main/docs/ARCHITECTURE.md';
	}

	public static function menu(): void {
		add_options_page(
			__( 'Aflanex Community', 'aflanex-community' ),
			__( 'Aflanex Community', 'aflanex-community' ),
			'manage_options',
			self::SLUG,
			[ self::class, 'render' ]
		);
	}

	public static function settings(): void {
		register_setting(
			'aflx_settings_group',
			Settings::OPTION,
			[
				'type'              => 'array',
				'sanitize_callback' => [ Settings::class, 'sanitize' ],
				'default'           => Settings::defaults(),
				'show_in_rest'      => false,
			]
		);
	}

	/**
	 * @param string[] $links
	 * @return string[]
	 */
	public static function action_links( array $links ): array {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Settings', 'aflanex-community' ) . '</a>' );
		return $links;
	}

	/**
	 * @param string[] $meta
	 * @return string[]
	 */
	public static function row_meta( array $meta, string $file ): array {
		if ( plugin_basename( AFLANEX_COMMUNITY_FILE ) !== $file ) {
			return $meta;
		}
		$meta[] = '<a href="' . esc_url( self::docs_url() ) . '" target="_blank" rel="noopener">' . esc_html__( 'Docs', 'aflanex-community' ) . '</a>';
		$meta[] = '<a href="' . esc_url( Updater::repo_url() . '/issues' ) . '" target="_blank" rel="noopener">' . esc_html__( 'Report an issue', 'aflanex-community' ) . '</a>';
		return $meta;
	}

	public static function repair(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'aflanex-community' ), 403 );
		}
		check_admin_referer( 'aflx_repair_setup' );

		Installer::install();
		Pages::rewrites();
		Sso::rewrites();
		flush_rewrite_rules();
		Updater::flush_cache();

		wp_safe_redirect( add_query_arg( 'aflx_repaired', 1, self::url() ) );
		exit;
	}

	/* ------------------------------------------------------------------ */

	private static function checks(): array {
		$checks = [];

		$fc = defined( 'FLUENT_COMMUNITY_PLUGIN_VERSION' );
		$checks[] = [
			'label'  => __( 'FluentCommunity', 'aflanex-community' ),
			'ok'     => $fc,
			'detail' => $fc
				/* translators: %s: version */
				? sprintf( __( 'Active, version %s', 'aflanex-community' ), FLUENT_COMMUNITY_PLUGIN_VERSION ) . ( defined( 'FLUENT_COMMUNITY_PRO_VERSION' ) ? ' + Pro' : '' )
				: __( 'Not active. Community features are paused.', 'aflanex-community' ),
		];

		$missing = [];
		foreach ( [ 'projects', 'project_form', 'people', 'portfolio', 'portfolio_edit', 'guidelines' ] as $key ) {
			if ( ! Pages::url( $key ) ) {
				$missing[] = $key;
			}
		}
		$checks[] = [
			'label'  => __( 'Community screens', 'aflanex-community' ),
			'ok'     => ! $missing,
			/* translators: %s: list of missing page keys */
			'detail' => $missing ? sprintf( __( 'Missing or unpublished: %s. Use “Repair setup”.', 'aflanex-community' ), implode( ', ', $missing ) ) : __( 'All pages published', 'aflanex-community' ),
		];

		$space = $fc ? CommunityBridge::space() : null;
		$checks[] = [
			'label'  => __( 'Projects space', 'aflanex-community' ),
			'ok'     => (bool) $space,
			'detail' => $space ? (string) $space->title : __( 'Not found. Project discussions can’t be created. Use “Repair setup”.', 'aflanex-community' ),
		];

		$smtp        = get_option( 'fluentmail-settings' );
		$smtp_ok     = is_array( $smtp ) && ! empty( $smtp['connections'] );
		$checks[] = [
			'label'  => __( 'Email delivery', 'aflanex-community' ),
			'ok'     => $smtp_ok,
			'detail' => $smtp_ok ? __( 'FluentSMTP connection configured', 'aflanex-community' ) : __( 'No FluentSMTP connection. Password resets and notifications may not arrive.', 'aflanex-community' ),
		];

		$checks[] = [
			'label'  => __( 'Erudify sign-in', 'aflanex-community' ),
			'ok'     => Sso::is_enabled(),
			'detail' => Sso::is_enabled()
				/* translators: %s: identity provider host */
				? sprintf( __( 'Configured (%s)', 'aflanex-community' ), (string) wp_parse_url( (string) AFLANEX_SSO_ISSUER, PHP_URL_HOST ) )
				: __( 'Not configured. Members sign in with a community password.', 'aflanex-community' ),
			'info'   => ! Sso::is_enabled(),
		];

		return $checks;
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = Settings::all();
		$release  = Updater::latest_release();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Aflanex Community', 'aflanex-community' ); ?></h1>
			<p><?php esc_html_e( 'Projects, portfolios, people discovery and Erudify sign-in on top of FluentCommunity. Spaces, members, moderation and menus are managed in FluentCommunity.', 'aflanex-community' ); ?></p>

			<?php if ( isset( $_GET['aflx_repaired'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Setup checked and repaired where needed.', 'aflanex-community' ); ?></p></div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Status', 'aflanex-community' ); ?></h2>
			<table class="widefat striped" style="max-width:900px">
				<tbody>
				<?php foreach ( self::checks() as $check ) : ?>
					<tr>
						<td style="width:28px">
							<span class="dashicons <?php echo esc_attr( $check['ok'] ? 'dashicons-yes-alt' : ( ! empty( $check['info'] ) ? 'dashicons-info' : 'dashicons-warning' ) ); ?>" style="color:<?php echo esc_attr( $check['ok'] ? '#15803d' : ( ! empty( $check['info'] ) ? '#50575e' : '#b32d2e' ) ); ?>" aria-hidden="true"></span>
						</td>
						<th scope="row" style="width:200px"><?php echo esc_html( $check['label'] ); ?></th>
						<td>
							<span class="screen-reader-text"><?php echo esc_html( $check['ok'] ? __( 'OK:', 'aflanex-community' ) : __( 'Needs attention:', 'aflanex-community' ) ); ?></span>
							<?php echo esc_html( $check['detail'] ); ?>
						</td>
					</tr>
				<?php endforeach; ?>
					<tr>
						<td></td>
						<th scope="row"><?php esc_html_e( 'Plugin version', 'aflanex-community' ); ?></th>
						<td>
							<?php echo esc_html( AFLANEX_COMMUNITY_VERSION ); ?>
							<?php if ( $release && version_compare( $release['version'], AFLANEX_COMMUNITY_VERSION, '>' ) ) : ?>
								· <a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>">
								<?php
								/* translators: %s: version */
								echo esc_html( sprintf( __( 'Version %s is available', 'aflanex-community' ), $release['version'] ) );
								?>
								</a>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td></td>
						<th scope="row"><?php esc_html_e( 'Published projects', 'aflanex-community' ); ?></th>
						<td><?php echo esc_html( number_format_i18n( Projects::count_published() ) ); ?> · <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=aflx_project' ) ); ?>"><?php esc_html_e( 'Manage projects', 'aflanex-community' ); ?></a></td>
					</tr>
				</tbody>
			</table>

			<?php if ( ! Sso::is_enabled() ) : ?>
				<h2><?php esc_html_e( 'Erudify sign-in', 'aflanex-community' ); ?></h2>
				<p><?php esc_html_e( 'To give members one account across Erudify and the Community, register this redirect URI in Erudify’s Supabase OAuth app, then add the credentials to wp-config.php:', 'aflanex-community' ); ?></p>
				<p><code><?php echo esc_html( Sso::redirect_uri() ); ?></code></p>
				<p><a href="<?php echo esc_url( Updater::repo_url() . '/blob/main/docs/ERUDIFY-SSO-SETUP.md' ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Read the setup guide', 'aflanex-community' ); ?></a></p>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Settings', 'aflanex-community' ); ?></h2>
			<form method="post" action="options.php">
				<?php settings_fields( 'aflx_settings_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="aflx-daily-limit"><?php esc_html_e( 'New projects per member per day', 'aflanex-community' ); ?></label></th>
						<td>
							<input type="number" min="1" max="50" id="aflx-daily-limit" name="<?php echo esc_attr( Settings::OPTION ); ?>[daily_project_limit]" value="<?php echo esc_attr( $settings['daily_project_limit'] ); ?>" class="small-text">
							<p class="description"><?php esc_html_e( 'Limits spam. Moderators aren’t limited.', 'aflanex-community' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aflx-stats-threshold"><?php esc_html_e( 'Show member count publicly from', 'aflanex-community' ); ?></label></th>
						<td>
							<input type="number" min="0" id="aflx-stats-threshold" name="<?php echo esc_attr( Settings::OPTION ); ?>[public_stats_threshold]" value="<?php echo esc_attr( $settings['public_stats_threshold'] ); ?>" class="small-text">
							<?php esc_html_e( 'members', 'aflanex-community' ); ?>
							<p class="description"><?php esc_html_e( 'The entry page shows member and project counts only once the community reaches this size.', 'aflanex-community' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'On uninstall', 'aflanex-community' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( Settings::OPTION ); ?>[delete_data_on_uninstall]" value="1" <?php checked( $settings['delete_data_on_uninstall'] ); ?>>
								<?php esc_html_e( 'Delete all Aflanex Community data when the plugin is deleted', 'aflanex-community' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Off by default. When on, deleting the plugin permanently removes projects, portfolio details, focus areas, skills and the plugin’s pages. FluentCommunity posts and spaces are never touched.', 'aflanex-community' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<h2><?php esc_html_e( 'Tools', 'aflanex-community' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="aflx_repair_setup">
				<?php wp_nonce_field( 'aflx_repair_setup' ); ?>
				<p><?php esc_html_e( 'Recreates any missing community pages and spaces, refreshes URLs and re-checks for updates. Safe to run at any time; it never overwrites content you’ve edited.', 'aflanex-community' ); ?></p>
				<?php submit_button( __( 'Repair setup', 'aflanex-community' ), 'secondary', 'submit', false ); ?>
			</form>

			<h2><?php esc_html_e( 'Help', 'aflanex-community' ); ?></h2>
			<ul>
				<li><a href="<?php echo esc_url( self::docs_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Architecture and admin guide', 'aflanex-community' ); ?></a></li>
				<li><a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=aflx_area&post_type=aflx_project' ) ); ?>"><?php esc_html_e( 'Manage focus areas', 'aflanex-community' ); ?></a> · <a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=aflx_skill&post_type=aflx_project' ) ); ?>"><?php esc_html_e( 'Tidy up skills', 'aflanex-community' ); ?></a></li>
				<li><a href="<?php echo esc_url( Updater::repo_url() . '/issues' ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Report an issue', 'aflanex-community' ); ?></a></li>
			</ul>
		</div>
		<?php
	}
}
