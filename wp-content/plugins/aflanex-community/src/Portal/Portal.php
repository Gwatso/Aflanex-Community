<?php

namespace Aflanex\Community\Portal;

use Aflanex\Community\Brand\Brand;
use Aflanex\Community\Pages\Pages;
use Aflanex\Community\Profiles\MemberProfile;

defined( 'ABSPATH' ) || exit;

/**
 * FluentCommunity integration through its public filters and actions only.
 *
 * Navigation model (member intent first):
 *   Home · Spaces · Projects · People        (+ Notifications, Profile in the header)
 */
final class Portal {

	public static function register(): void {
		add_filter( 'fluent_community/header_vars', [ self::class, 'header_vars' ] );
		add_filter( 'fluent_community/menu_groups', [ self::class, 'menu_groups' ] );
		add_filter( 'fluent_community/profile_view_data', [ self::class, 'profile_view_data' ], 10, 2 );
		add_action( 'fluent_community/after_sidebar_wrap', [ self::class, 'render_next_steps' ] );
		add_filter( 'fluent_community/mobile_menu', [ self::class, 'mobile_menu' ], 10, 2 );
	}

	/**
	 * Use the approved logo file when it exists; otherwise the text title.
	 */
	public static function header_vars( array $vars ): array {
		$brand = Brand::get();
		if ( $brand['logo'] ) {
			$vars['logo']       = $brand['logo'];
			$vars['white_logo'] = $brand['logo_dark'] ?: $brand['logo'];
		}
		$vars['site_title'] = $brand['full_name'];
		return $vars;
	}

	private static function icon( string $paths ): string {
		return '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">' . $paths . '</svg>';
	}

	/**
	 * @param array $groups
	 */
	public static function menu_groups( $groups ) {
		if ( ! is_array( $groups ) || empty( $groups['mainMenuItems'] ) || ! is_array( $groups['mainMenuItems'] ) ) {
			return $groups;
		}

		$main = $groups['mainMenuItems'];
		$out  = [];

		foreach ( $main as $key => $item ) {
			if ( 'all_feeds' === $key && ( $item['title'] ?? '' ) === __( 'Feed', 'fluent-community' ) ) {
				$item['title'] = __( 'Home', 'aflanex-community' );
			}

			// People replaces the basic member list with skill/interest discovery.
			if ( 'all_members' === $key ) {
				continue;
			}

			$out[ $key ] = $item;

			if ( 'spaces' === $key ) {
				$out['aflx_projects'] = [
					'slug'         => 'aflx_projects',
					'title'        => __( 'Projects', 'aflanex-community' ),
					'is_system'    => 'no',
					'enabled'      => 'yes',
					'permalink'    => Pages::projects_url(),
					'link_classes' => 'fcom_aflx_projects',
					'shape_svg'    => self::icon( '<path d="M4 7.5 12 3l8 4.5v9L12 21l-8-4.5z"/><path d="m4 7.5 8 4.5 8-4.5M12 12v9"/>' ),
				];
				$out['aflx_people']   = [
					'slug'         => 'aflx_people',
					'title'        => __( 'People', 'aflanex-community' ),
					'is_system'    => 'no',
					'enabled'      => 'yes',
					'permalink'    => Pages::people_url(),
					'link_classes' => 'fcom_aflx_people',
					'shape_svg'    => self::icon( '<circle cx="9" cy="8" r="3.25"/><circle cx="17" cy="9.5" r="2.5"/><path d="M3.5 19c.6-3 2.9-4.75 5.5-4.75S13.9 16 14.5 19"/><path d="M15 14.4c2.6-.2 4.6 1.3 5.1 4.1"/>' ),
				];
			}
		}

		$groups['mainMenuItems'] = $out;

		if ( isset( $groups['profileDropdownItems'] ) && is_array( $groups['profileDropdownItems'] ) ) {
			$groups['profileDropdownItems'] = [
				'aflx_portfolio' => [
					'slug'      => 'aflx_portfolio',
					'title'     => __( 'My portfolio', 'aflanex-community' ),
					'is_system' => 'no',
					'enabled'   => 'yes',
					'permalink' => home_url( '/portfolio/' ),
					'shape_svg' => self::icon( '<rect x="3.5" y="7" width="17" height="12.5" rx="2"/><path d="M9 7V5.5A1.5 1.5 0 0 1 10.5 4h3A1.5 1.5 0 0 1 15 5.5V7"/>' ),
				],
				'aflx_my_projects' => [
					'slug'      => 'aflx_my_projects',
					'title'     => __( 'My projects', 'aflanex-community' ),
					'is_system' => 'no',
					'enabled'   => 'yes',
					'permalink' => Pages::projects_url( [ 'mine' => 1 ] ),
					'shape_svg' => self::icon( '<path d="M4 7.5 12 3l8 4.5v9L12 21l-8-4.5z"/>' ),
				],
			] + $groups['profileDropdownItems'];
		}

		return $groups;
	}

	/**
	 * Mobile bottom navigation: Home · Spaces · Projects · People · Profile.
	 *
	 * @param array $items
	 * @param mixed $xprofile
	 */
	public static function mobile_menu( $items, $xprofile = null ) {
		if ( ! is_array( $items ) || ! $xprofile ) {
			return $items;
		}

		$ours = [
			[
				'name'      => 'aflx_projects',
				'title'     => __( 'Projects', 'aflanex-community' ),
				'permalink' => Pages::projects_url(),
				'icon_svg'  => self::icon( '<path d="M4 7.5 12 3l8 4.5v9L12 21l-8-4.5z"/><path d="m4 7.5 8 4.5 8-4.5M12 12v9"/>' ),
			],
			[
				'name'      => 'aflx_people',
				'title'     => __( 'People', 'aflanex-community' ),
				'permalink' => Pages::people_url(),
				'icon_svg'  => self::icon( '<circle cx="9" cy="8" r="3.25"/><circle cx="17" cy="9.5" r="2.5"/><path d="M3.5 19c.6-3 2.9-4.75 5.5-4.75S13.9 16 14.5 19"/><path d="M15 14.4c2.6-.2 4.6 1.3 5.1 4.1"/>' ),
			],
		];

		// Insert before the profile item (always last when signed in).
		array_splice( $items, max( 0, count( $items ) - 1 ), 0, $ours );

		return $items;
	}

	/**
	 * Add a "Portfolio" tab to FluentCommunity profiles.
	 *
	 * @param array $profile
	 * @param mixed $xprofile
	 */
	public static function profile_view_data( $profile, $xprofile ) {
		if ( ! is_array( $profile ) || ! $xprofile || empty( $xprofile->username ) ) {
			return $profile;
		}

		$profile['profile_navs']   = is_array( $profile['profile_navs'] ?? null ) ? $profile['profile_navs'] : [];
		$profile['profile_navs'][] = [
			'slug'          => 'aflx_portfolio',
			'title'         => __( 'Portfolio', 'aflanex-community' ),
			'url'           => Pages::portfolio_url( $xprofile->username ),
			'wrapper_class' => 'fcom_profile_aflx_portfolio', // No `route` → plain same-tab link.
		];

		return $profile;
	}

	/**
	 * "Your next steps": lightweight onboarding in the sidebar, based on
	 * what the member has actually done. Disappears once complete.
	 */
	public static function render_next_steps( $context = '' ): void {
		$user_id = get_current_user_id();
		if ( ! $user_id || get_user_meta( $user_id, 'aflx_onboarding_dismissed', true ) ) {
			return;
		}

		$progress = MemberProfile::next_steps( $user_id );
		if ( $progress['done'] >= $progress['total'] ) {
			return;
		}

		$percent = (int) round( 100 * $progress['done'] / max( 1, $progress['total'] ) );
		?>
		<section class="aflx-sidebar-card" aria-labelledby="aflx-next-steps-title">
			<p class="aflx-sidebar-card__title" id="aflx-next-steps-title"><?php esc_html_e( 'Your next steps', 'aflanex-community' ); ?></p>
			<div class="aflx-meter" role="progressbar" aria-valuemin="0" aria-valuemax="<?php echo esc_attr( $progress['total'] ); ?>" aria-valuenow="<?php echo esc_attr( $progress['done'] ); ?>" aria-label="<?php esc_attr_e( 'Getting started progress', 'aflanex-community' ); ?>"><span style="width: <?php echo esc_attr( max( 4, $percent ) ); ?>%"></span></div>
			<ul>
				<?php foreach ( $progress['steps'] as $step ) : ?>
					<?php if ( $step['done'] ) { continue; } ?>
					<li><a href="<?php echo esc_url( $step['url'] ); ?>"><span class="aflx-dot" aria-hidden="true"></span><?php echo esc_html( $step['label'] ); ?></a></li>
					<?php break; // Show one clear next action at a time… ?>
				<?php endforeach; ?>
			</ul>
			<p class="aflx-small" style="margin:8px 0 0;color:var(--aflanex-muted)">
				<?php
				/* translators: 1: steps done, 2: total steps */
				echo esc_html( sprintf( __( '%1$d of %2$d done', 'aflanex-community' ), $progress['done'], $progress['total'] ) );
				?>
				· <a href="<?php echo esc_url( Pages::portfolio_edit_url() . '#aflx-getting-started' ); ?>"><?php esc_html_e( 'See all', 'aflanex-community' ); ?></a>
			</p>
		</section>
		<?php
	}
}
