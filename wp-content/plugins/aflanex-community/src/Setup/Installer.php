<?php

namespace Aflanex\Community\Setup;

use Aflanex\Community\Pages\Pages;
use Aflanex\Community\Projects\CommunityBridge;
use Aflanex\Community\Projects\ProjectPostType;

defined( 'ABSPATH' ) || exit;

/**
 * Idempotent setup. Safe to run repeatedly: it only creates what's missing
 * and never overwrites content an admin has since edited (it checks
 * before writing). No sample members or posts are created, ever.
 */
final class Installer {

	public const VERSION_OPTION = 'aflx_setup_version';
	public const SCHEMA_VERSION = '1.0.0';

	public static function activate(): void {
		ProjectPostType::register_types();
		self::install();
		// init has already run during activation, so register our rules explicitly before flushing.
		Pages::rewrites();
		\Aflanex\Community\Identity\Sso::rewrites();
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	public static function maybe_upgrade(): void {
		if ( get_option( self::VERSION_OPTION ) === self::SCHEMA_VERSION ) {
			return;
		}
		add_action(
			'init',
			static function () {
				self::install();
				Pages::rewrites(); // Page IDs may be new; re-register before flushing.
				flush_rewrite_rules();
			},
			99
		);
	}

	public static function install(): void {
		self::ensure_pages();
		self::ensure_focus_areas();

		if ( class_exists( '\FluentCommunity\App\Models\Space' ) ) {
			self::ensure_spaces();
			self::ensure_welcome_banner();
		}

		update_option( self::VERSION_OPTION, self::SCHEMA_VERSION, false );
	}

	/* ------------------------------------------------------------------ */

	private static function ensure_pages(): void {
		$ids = Pages::ids();

		$pages = [
			'projects'       => [ 'title' => __( 'Projects', 'aflanex-community' ), 'slug' => 'projects', 'parent' => '' ],
			'project_form'   => [ 'title' => __( 'Start a project', 'aflanex-community' ), 'slug' => 'new', 'parent' => 'projects' ],
			'people'         => [ 'title' => __( 'People', 'aflanex-community' ), 'slug' => 'people', 'parent' => '' ],
			'portfolio'      => [ 'title' => __( 'Portfolio', 'aflanex-community' ), 'slug' => 'portfolio', 'parent' => '' ],
			'portfolio_edit' => [ 'title' => __( 'Edit your portfolio', 'aflanex-community' ), 'slug' => 'edit', 'parent' => 'portfolio' ],
			'guidelines'     => [ 'title' => __( 'Community guidelines', 'aflanex-community' ), 'slug' => 'community-guidelines', 'parent' => '', 'content' => self::guidelines_content() ],
		];

		foreach ( $pages as $key => $page ) {
			if ( ! empty( $ids[ $key ] ) && get_post( $ids[ $key ] ) && 'trash' !== get_post_status( $ids[ $key ] ) ) {
				continue;
			}

			$parent_id = $page['parent'] ? (int) ( $ids[ $page['parent'] ] ?? 0 ) : 0;
			$path      = $page['parent'] && $parent_id ? get_page_uri( $parent_id ) . '/' . $page['slug'] : $page['slug'];
			$found     = get_page_by_path( $path );

			if ( $found ) {
				$ids[ $key ] = (int) $found->ID;
				continue;
			}

			$id = wp_insert_post(
				[
					'post_type'      => 'page',
					'post_status'    => 'publish',
					'post_title'     => $page['title'],
					'post_name'      => $page['slug'],
					'post_parent'    => $parent_id,
					'post_content'   => $page['content'] ?? '',
					'comment_status' => 'closed',
					'post_author'    => self::admin_id(),
				]
			);

			if ( $id && ! is_wp_error( $id ) ) {
				$ids[ $key ] = (int) $id;
			}
		}

		update_option( Pages::OPTION, $ids, true );
	}

	private static function ensure_focus_areas(): void {
		if ( get_option( 'aflx_focus_areas_seeded' ) ) {
			return;
		}
		// A starting vocabulary for grouping projects and matching interests.
		// Admins rename, add or remove these under Projects → Focus areas.
		$areas = [
			'AI & Emerging Technology',
			'Digital Skills',
			'Productivity',
			'Freelancing & Remote Work',
			'Entrepreneurship',
			'Careers & Opportunities',
		];
		foreach ( $areas as $area ) {
			if ( ! term_exists( $area, ProjectPostType::TAX_AREA ) ) {
				wp_insert_term( $area, ProjectPostType::TAX_AREA );
			}
		}
		update_option( 'aflx_focus_areas_seeded', 1, false );
	}

	/**
	 * A small, purposeful set of spaces. Quality over quantity.
	 */
	private static function ensure_spaces(): void {
		$admin = self::admin_id();

		self::ensure_group( 'get-started', __( 'Get Started', 'aflanex-community' ), 1 );
		$build       = self::ensure_group( 'build', __( 'Build', 'aflanex-community' ), 2 );
		$grow        = self::ensure_group( 'grow', __( 'Grow', 'aflanex-community' ), 3 );

		// Existing starter spaces: only fill in what's empty.
		$start = \FluentCommunity\App\Models\Space::where( 'slug', 'start-here' )->first();
		if ( $start && '' === (string) $start->description ) {
			$start->description = __( 'Announcements, how the community works and what’s coming next.', 'aflanex-community' );
			$start->save();
		}

		$hello = \FluentCommunity\App\Models\Space::where( 'slug', 'say-hello' )->first();
		if ( $hello ) {
			if ( 'Say Hello' === $hello->title ) {
				$hello->title = __( 'Introductions', 'aflanex-community' );
			}
			if ( '' === (string) $hello->description ) {
				$hello->description = __( 'Say who you are, what you’re learning and what you want to build.', 'aflanex-community' );
			}
			$hello->save();
			update_option( 'aflx_introductions_space', $hello->slug, false );
		}

		$projects = self::ensure_space(
			'projects',
			__( 'Projects & Collaboration', 'aflanex-community' ),
			__( 'Share what you’re building, post progress and find collaborators. New projects appear here automatically.', 'aflanex-community' ),
			$build,
			1,
			'🛠️'
		);
		if ( $projects ) {
			update_option( CommunityBridge::SPACE_OPTION, (int) $projects->id, false );
		}

		self::ensure_space(
			'ask-the-community',
			__( 'Ask the Community', 'aflanex-community' ),
			__( 'Stuck on something? Ask here. Answer what you can, because helping others is how we all get better.', 'aflanex-community' ),
			$build,
			2,
			'💬'
		);

		self::ensure_space(
			'careers',
			__( 'Career & Opportunities', 'aflanex-community' ),
			__( 'Career moves, remote work, freelancing and opportunities worth sharing.', 'aflanex-community' ),
			$grow,
			1,
			'🧭'
		);

		// The admin moderates every space it creates.
		if ( $admin ) {
			foreach ( \FluentCommunity\App\Models\Space::whereIn( 'slug', [ 'projects', 'ask-the-community', 'careers' ] )->get() as $space ) {
				\FluentCommunity\App\Services\Helper::addToSpace( $space, $admin, 'admin', 'by_admin' );
			}
		}
	}

	private static function ensure_group( string $slug, string $title, int $serial ): int {
		$group = \FluentCommunity\App\Models\SpaceGroup::where( 'slug', $slug )->where( 'type', 'space_group' )->first();
		if ( ! $group ) {
			$group = \FluentCommunity\App\Models\SpaceGroup::create(
				[
					'title'      => $title,
					'slug'       => $slug,
					'type'       => 'space_group',
					'status'     => 'active',
					'privacy'    => 'public',
					'serial'     => $serial,
					'created_by' => self::admin_id(),
					'settings'   => [ 'hide_members' => 'no', 'always_show_spaces' => 'yes' ],
				]
			);
		}
		return (int) $group->id;
	}

	private static function ensure_space( string $slug, string $title, string $description, int $group_id, int $serial, string $emoji ) {
		$space = \FluentCommunity\App\Models\Space::where( 'slug', $slug )->first();
		if ( $space ) {
			return $space;
		}

		$settings = array_merge(
			( new \FluentCommunity\App\Models\Space() )->defaultSettings(),
			[ 'emoji' => $emoji, 'can_request_join' => 'yes' ]
		);

		return \FluentCommunity\App\Models\Space::create(
			[
				'title'       => $title,
				'slug'        => $slug,
				'description' => $description,
				'type'        => 'community',
				'privacy'     => 'public',
				'status'      => 'published',
				'parent_id'   => $group_id,
				'serial'      => $serial,
				'created_by'  => self::admin_id(),
				'settings'    => $settings,
			]
		);
	}

	private static function ensure_welcome_banner(): void {
		$settings = \FluentCommunity\App\Services\Helper::getWelcomeBannerSettings();
		if ( 'yes' === ( $settings['login']['enabled'] ?? 'no' ) || ! empty( $settings['login']['description'] ) ) {
			return; // An admin has configured it; leave it alone.
		}

		$description = "## " . __( 'Build what’s next together.', 'aflanex-community' ) . "\n\n"
			. __( 'Connect with people, build practical projects, share what you’re learning and discover what comes next.', 'aflanex-community' );

		$settings['login']['enabled']              = 'yes';
		$settings['login']['allowClose']           = 'yes';
		$settings['login']['description']          = $description;
		$settings['login']['description_rendered'] = wp_kses_post( \FluentCommunity\App\Services\FeedsHelper::mdToHtml( $description ) );

		\FluentCommunity\App\Functions\Utility::updateOption( 'welcome_banner_settings', $settings );
		\FluentCommunity\App\Functions\Utility::forgetCache( 'welcome_banner_settings' );
	}

	private static function admin_id(): int {
		$admins = get_users( [ 'role' => 'administrator', 'number' => 1, 'orderby' => 'ID', 'order' => 'ASC', 'fields' => 'ID' ] );
		return $admins ? (int) $admins[0] : 0;
	}

	private static function guidelines_content(): string {
		return <<<'HTML'
<!-- wp:paragraph -->
<p>Aflanex Community is where people who are learning, building and doing what’s next help each other move forward. These guidelines keep it useful, safe and worth showing up for.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>How we show up</h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul>
<li><strong>Ask questions.</strong> Curiosity is welcome. Say what you’ve tried so far and people can help faster.</li>
<li><strong>Own your growth.</strong> Share your goals and your progress, including the parts that didn’t work.</li>
<li><strong>Show your work.</strong> Real projects, real updates and honest feedback beat polished claims.</li>
<li><strong>Keep adjusting.</strong> Take feedback as information, not judgement. Give it the same way.</li>
<li><strong>Make it useful.</strong> Before posting, ask: will this help someone learn, build or decide something?</li>
</ul>
<!-- /wp:list -->

<!-- wp:heading -->
<h2>Giving feedback</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Be specific and kind. Say what works, what could be better and what you’d try next. Critique the work, never the person.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>Not allowed</h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul>
<li>Harassment, hate speech, discrimination or personal attacks.</li>
<li>Spam, unsolicited promotion, get-rich-quick schemes or misleading offers.</li>
<li>Sharing other people’s private information, or content you don’t have the right to share.</li>
<li>Plagiarism: presenting someone else’s work as your own.</li>
</ul>
<!-- /wp:list -->

<!-- wp:heading -->
<h2>Privacy</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Community content and member profiles are visible to signed-in members only. You can hide yourself from the People directory in your portfolio settings at any time.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>If something’s wrong</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Use the report option on any post or comment, or contact a community moderator. Moderators may edit or remove content and restrict accounts that break these guidelines.</p>
<!-- /wp:paragraph -->
HTML;
	}
}
