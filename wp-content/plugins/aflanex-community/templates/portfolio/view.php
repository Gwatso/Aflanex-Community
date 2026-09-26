<?php
/**
 * Member portfolio: a lightweight professional/community identity.
 * Who are you? What are you learning? What can you do? What are you
 * building? What have you contributed? What could we build together?
 *
 * @var array      $member   MemberProfile::portfolio()
 * @var array|null $learning Learning summary from a connected integration (none today)
 */
defined( 'ABSPATH' ) || exit;

use Aflanex\Community\Pages\Pages;
use Aflanex\Community\Profiles\MemberProfile;
use Aflanex\Community\Support\View;

$aflx_link_labels = MemberProfile::link_fields();
$aflx_c           = $member['contributions'];
$aflx_is_own      = $member['is_own'];
$aflx_sparse      = ! $member['headline'] && ! $member['skills'] && ! $member['learning_now'] && ! $member['bio'];
?>
<div class="aflx-page">
	<?php MemberProfile::render_notice(); ?>

	<header class="aflx-card aflx-portfolio-head">
		<div class="aflx-portfolio-head__main">
			<img class="aflx-avatar aflx-avatar--xl" src="<?php echo esc_url( $member['avatar'] ); ?>" alt="" width="112" height="112">
			<div style="min-width:0">
				<h1 class="aflx-h1" style="font-size:var(--aflanex-text-2xl)"><?php echo esc_html( $member['name'] ); ?></h1>
				<?php if ( $member['headline'] ) : ?>
					<p class="aflx-lead" style="margin-top:var(--aflanex-space-1)"><?php echo esc_html( $member['headline'] ); ?></p>
				<?php endif; ?>
				<p class="aflx-small aflx-muted" style="margin:var(--aflanex-space-2) 0 0">
					<?php if ( $member['location'] ) : ?>
						<?php echo View::icon( 'pin', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( $member['location'] ); ?> ·
					<?php endif; ?>
					<?php
					/* translators: %s: month and year */
					echo esc_html( sprintf( __( 'Member since %s', 'aflanex-community' ), wp_date( 'F Y', (int) $member['joined'] ) ) );
					?>
				</p>
			</div>
		</div>
		<div class="aflx-row">
			<?php if ( $aflx_is_own ) : ?>
				<a class="aflx-btn aflx-btn--primary" href="<?php echo esc_url( Pages::portfolio_edit_url() ); ?>"><?php echo View::icon( 'edit', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Edit portfolio', 'aflanex-community' ); ?></a>
			<?php endif; ?>
			<a class="aflx-btn aflx-btn--secondary" href="<?php echo esc_url( $member['profile_url'] ); ?>"><?php echo esc_html( $aflx_is_own ? __( 'Photo & bio', 'aflanex-community' ) : __( 'Posts & activity', 'aflanex-community' ) ); ?></a>
		</div>
	</header>

	<?php if ( $aflx_is_own && $aflx_sparse ) : ?>
		<div class="aflx-alert aflx-alert--info" style="margin-top:var(--aflanex-space-5)">
			<span class="aflx-alert__icon" aria-hidden="true">i</span>
			<span><strong><?php esc_html_e( 'Your portfolio is almost ready.', 'aflanex-community' ); ?></strong>
			<?php esc_html_e( 'Add a headline, a few skills and what you’re learning so people know what you’re about.', 'aflanex-community' ); ?>
			<a href="<?php echo esc_url( Pages::portfolio_edit_url() ); ?>"><?php esc_html_e( 'Add details', 'aflanex-community' ); ?></a></span>
		</div>
	<?php endif; ?>

	<div class="aflx-split" style="margin-top:var(--aflanex-space-6)">
		<div class="aflx-stack-lg">
			<?php if ( $member['bio'] ) : ?>
				<section aria-labelledby="aflx-about">
					<h2 id="aflx-about" class="aflx-h2"><?php esc_html_e( 'About', 'aflanex-community' ); ?></h2>
					<div class="aflx-prose" style="margin-top:var(--aflanex-space-3)"><?php echo wp_kses_post( wpautop( esc_html( $member['bio'] ) ) ); ?></div>
				</section>
			<?php endif; ?>

			<section aria-labelledby="aflx-building">
				<div class="aflx-section-title">
					<h2 id="aflx-building" class="aflx-h2"><?php esc_html_e( 'Building', 'aflanex-community' ); ?></h2>
					<?php if ( $aflx_is_own && $member['projects'] ) : ?>
						<a class="aflx-small" href="<?php echo esc_url( Pages::project_form_url() ); ?>"><?php esc_html_e( 'Start another', 'aflanex-community' ); ?></a>
					<?php endif; ?>
				</div>
				<?php if ( $member['projects'] ) : ?>
					<div class="aflx-grid">
						<?php foreach ( $member['projects'] as $aflx_project ) : ?>
							<?php View::render( 'partials/project-card', [ 'project' => $aflx_project ] ); ?>
						<?php endforeach; ?>
					</div>
				<?php elseif ( $aflx_is_own ) : ?>
					<?php
					View::render(
						'partials/empty',
						[
							'icon'         => 'build',
							'title'        => __( 'What are you building?', 'aflanex-community' ),
							'text'         => __( 'Projects are the strongest evidence of what you can do, and they don’t need to be finished.', 'aflanex-community' ),
							'action_url'   => Pages::project_form_url(),
							'action_label' => __( 'Start a project', 'aflanex-community' ),
						]
					);
					?>
				<?php else : ?>
					<p class="aflx-muted"><?php esc_html_e( 'No projects shared yet.', 'aflanex-community' ); ?></p>
				<?php endif; ?>
			</section>

			<?php if ( $learning ) : ?>
				<section aria-labelledby="aflx-learning">
					<h2 id="aflx-learning" class="aflx-h2"><?php esc_html_e( 'Learning', 'aflanex-community' ); ?></h2>
					<?php View::render( 'portfolio/learning', [ 'learning' => $learning ] ); ?>
				</section>
			<?php endif; ?>
		</div>

		<aside class="aflx-stack" aria-label="<?php esc_attr_e( 'Skills and interests', 'aflanex-community' ); ?>">
			<?php if ( $member['learning_now'] ) : ?>
				<div class="aflx-card">
					<h2 class="aflx-eyebrow"><?php esc_html_e( 'Learning right now', 'aflanex-community' ); ?></h2>
					<p style="margin:var(--aflanex-space-2) 0 0"><?php echo esc_html( $member['learning_now'] ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( $member['skills'] ) : ?>
				<div class="aflx-card">
					<h2 class="aflx-eyebrow"><?php esc_html_e( 'Skills', 'aflanex-community' ); ?></h2>
					<ul class="aflx-chip-list" style="margin-top:var(--aflanex-space-3)">
						<?php foreach ( $member['skills'] as $aflx_term ) : ?>
							<li><a class="aflx-chip" href="<?php echo esc_url( Pages::people_url( [ 'skill' => $aflx_term->slug ] ) ); ?>"><?php echo esc_html( $aflx_term->name ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php if ( $member['can_help_with'] || $member['open_to'] ) : ?>
				<div class="aflx-card aflx-card--tint">
					<?php if ( $member['can_help_with'] ) : ?>
						<h2 class="aflx-eyebrow" style="color:var(--aflanex-accent)"><?php esc_html_e( 'Can help with', 'aflanex-community' ); ?></h2>
						<p style="margin:var(--aflanex-space-2) 0 0"><?php echo esc_html( $member['can_help_with'] ); ?></p>
					<?php endif; ?>
					<?php if ( $member['open_to'] ) : ?>
						<h2 class="aflx-eyebrow" style="color:var(--aflanex-accent);margin-top:var(--aflanex-space-4)"><?php esc_html_e( 'Open to', 'aflanex-community' ); ?></h2>
						<ul style="margin:var(--aflanex-space-2) 0 0;padding-left:1.1em">
							<?php foreach ( $member['open_to'] as $aflx_label ) : ?>
								<li><?php echo esc_html( $aflx_label ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( $member['areas'] ) : ?>
				<div class="aflx-card">
					<h2 class="aflx-eyebrow"><?php esc_html_e( 'Interested in', 'aflanex-community' ); ?></h2>
					<ul class="aflx-chip-list" style="margin-top:var(--aflanex-space-3)">
						<?php foreach ( $member['areas'] as $aflx_term ) : ?>
							<li><a class="aflx-chip" href="<?php echo esc_url( Pages::people_url( [ 'area' => $aflx_term->term_id ] ) ); ?>"><?php echo esc_html( $aflx_term->name ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<div class="aflx-card">
				<h2 class="aflx-eyebrow"><?php esc_html_e( 'Contribution', 'aflanex-community' ); ?></h2>
				<dl class="aflx-contrib">
					<div><dt><?php esc_html_e( 'Projects', 'aflanex-community' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $aflx_c['projects'] ) ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Posts', 'aflanex-community' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $aflx_c['posts'] ) ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Replies', 'aflanex-community' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $aflx_c['replies'] ) ); ?></dd></div>
				</dl>
			</div>

			<?php if ( $member['links'] ) : ?>
				<div class="aflx-card">
					<h2 class="aflx-eyebrow"><?php esc_html_e( 'Elsewhere', 'aflanex-community' ); ?></h2>
					<ul style="list-style:none;margin:var(--aflanex-space-3) 0 0;padding:0;display:grid;gap:var(--aflanex-space-2)">
						<?php foreach ( $member['links'] as $aflx_key => $aflx_url ) : ?>
							<li><a href="<?php echo esc_url( $aflx_url ); ?>" rel="noopener nofollow me" target="_blank"><?php echo esc_html( $aflx_link_labels[ $aflx_key ] ?? $aflx_url ); ?><span class="aflx-sr-only"> <?php esc_html_e( '(opens in a new tab)', 'aflanex-community' ); ?></span></a></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
		</aside>
	</div>
</div>
