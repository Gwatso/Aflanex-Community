<?php
/**
 * Project page.
 *
 * @var array $project Projects::detail()
 */
defined( 'ABSPATH' ) || exit;

use Aflanex\Community\Pages\Pages;
use Aflanex\Community\Profiles\MemberProfile;
use Aflanex\Community\Projects\ProjectPostType;
use Aflanex\Community\Support\View;

$aflx_team = array_filter( array_merge( [ $project['owner'] ], $project['collaborators'] ) );
?>
<div class="aflx-page">
	<?php MemberProfile::render_notice(); ?>

	<p style="margin:0 0 var(--aflanex-space-4)"><a class="aflx-small" href="<?php echo esc_url( Pages::projects_url() ); ?>"><?php echo View::icon( 'back', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'All projects', 'aflanex-community' ); ?></a></p>

	<header class="aflx-project-hero">
		<?php if ( $project['cover'] ) : ?>
			<img class="aflx-project-hero__cover" src="<?php echo esc_url( $project['cover'] ); ?>" alt="">
		<?php endif; ?>
		<div class="aflx-row" style="margin-bottom:var(--aflanex-space-3)">
			<span class="aflx-status aflx-status--<?php echo esc_attr( $project['status'] ); ?>"><?php echo esc_html( $project['status_label'] ); ?></span>
			<?php foreach ( $project['areas'] as $aflx_area ) : ?>
				<span class="aflx-chip"><?php echo esc_html( $aflx_area ); ?></span>
			<?php endforeach; ?>
		</div>
		<h1 class="aflx-h1"><?php echo esc_html( $project['title'] ); ?></h1>
		<?php if ( $project['summary'] ) : ?>
			<p class="aflx-lead" style="margin-top:var(--aflanex-space-3)"><?php echo esc_html( $project['summary'] ); ?></p>
		<?php endif; ?>
		<div class="aflx-row" style="margin-top:var(--aflanex-space-5)">
			<?php if ( $project['discussion'] ) : ?>
				<a class="aflx-btn aflx-btn--primary" href="<?php echo esc_url( $project['discussion']['url'] ); ?>">
					<?php echo View::icon( 'chat', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php
					echo esc_html(
						$project['discussion']['comments']
							/* translators: %d: comment count */
							? sprintf( _n( 'Join the discussion (%d)', 'Join the discussion (%d)', $project['discussion']['comments'], 'aflanex-community' ), $project['discussion']['comments'] )
							: ( $project['feedback_requested'] ? __( 'Give feedback', 'aflanex-community' ) : __( 'Start the discussion', 'aflanex-community' ) )
					);
					?>
				</a>
			<?php endif; ?>
			<?php if ( $project['can_edit'] ) : ?>
				<a class="aflx-btn aflx-btn--secondary" href="<?php echo esc_url( $project['edit_url'] ); ?>"><?php echo View::icon( 'edit', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Edit project', 'aflanex-community' ); ?></a>
			<?php endif; ?>
		</div>
	</header>

	<div class="aflx-split" style="margin-top:var(--aflanex-space-8)">
		<div class="aflx-stack-lg">
			<?php if ( 'seeking_collaborators' === $project['status'] && $project['looking_for'] ) : ?>
				<div class="aflx-card aflx-card--tint">
					<p class="aflx-eyebrow" style="color:var(--aflanex-accent)"><?php esc_html_e( 'Looking for collaborators', 'aflanex-community' ); ?></p>
					<p style="margin:var(--aflanex-space-2) 0 0"><?php echo esc_html( $project['looking_for'] ); ?></p>
					<?php if ( $project['discussion'] && ! $project['can_edit'] ) : ?>
						<p style="margin:var(--aflanex-space-3) 0 0"><a href="<?php echo esc_url( $project['discussion']['url'] ); ?>"><?php esc_html_e( 'Offer to help in the discussion →', 'aflanex-community' ); ?></a></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( $project['problem'] ) : ?>
				<section aria-labelledby="aflx-problem">
					<h2 id="aflx-problem" class="aflx-h2"><?php esc_html_e( 'The problem', 'aflanex-community' ); ?></h2>
					<div class="aflx-prose" style="margin-top:var(--aflanex-space-3)"><?php echo wp_kses_post( wpautop( esc_html( $project['problem'] ) ) ); ?></div>
				</section>
			<?php endif; ?>

			<?php if ( $project['approach'] ) : ?>
				<section aria-labelledby="aflx-approach">
					<h2 id="aflx-approach" class="aflx-h2"><?php esc_html_e( 'The approach', 'aflanex-community' ); ?></h2>
					<div class="aflx-prose" style="margin-top:var(--aflanex-space-3)"><?php echo wp_kses_post( wpautop( esc_html( $project['approach'] ) ) ); ?></div>
				</section>
			<?php endif; ?>

			<?php if ( trim( wp_strip_all_tags( $project['content'] ) ) ) : ?>
				<section aria-labelledby="aflx-about">
					<h2 id="aflx-about" class="aflx-h2"><?php esc_html_e( 'About the project', 'aflanex-community' ); ?></h2>
					<div class="aflx-prose" style="margin-top:var(--aflanex-space-3)"><?php echo wp_kses_post( $project['content'] ); ?></div>
				</section>
			<?php endif; ?>

			<section aria-labelledby="aflx-updates">
				<div class="aflx-section-title">
					<h2 id="aflx-updates" class="aflx-h2"><?php esc_html_e( 'Progress updates', 'aflanex-community' ); ?></h2>
				</div>

				<?php if ( $project['can_edit'] ) : ?>
					<form class="aflx-card aflx-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:var(--aflanex-space-5)">
						<input type="hidden" name="action" value="aflx_project_update">
						<input type="hidden" name="project_id" value="<?php echo esc_attr( $project['id'] ); ?>">
						<?php wp_nonce_field( 'aflx_project_update_' . $project['id'], '_aflx_nonce' ); ?>
						<div class="aflx-field">
							<label class="aflx-label" for="aflx-update"><?php esc_html_e( 'What moved forward?', 'aflanex-community' ); ?></label>
							<textarea class="aflx-textarea" id="aflx-update" name="update" rows="3" maxlength="3000" required placeholder="<?php esc_attr_e( 'What you did, what you learned, what’s next…', 'aflanex-community' ); ?>"></textarea>
							<p class="aflx-hint"><?php esc_html_e( 'Updates are shared in the Projects space, so your progress shows up in people’s Home feed.', 'aflanex-community' ); ?></p>
						</div>
						<div class="aflx-form-actions">
							<label class="aflx-label" for="aflx-update-status" style="font-weight:500"><?php esc_html_e( 'Status', 'aflanex-community' ); ?></label>
							<select class="aflx-select" id="aflx-update-status" name="status" style="width:auto">
								<?php foreach ( ProjectPostType::statuses() as $aflx_key => $aflx_label ) : ?>
									<option value="<?php echo esc_attr( $aflx_key ); ?>" <?php selected( $project['status'], $aflx_key ); ?>><?php echo esc_html( $aflx_label ); ?></option>
								<?php endforeach; ?>
							</select>
							<button class="aflx-btn aflx-btn--primary" type="submit"><?php esc_html_e( 'Post update', 'aflanex-community' ); ?></button>
						</div>
					</form>
				<?php endif; ?>

				<?php if ( $project['updates'] ) : ?>
					<ol class="aflx-timeline">
						<?php foreach ( $project['updates'] as $aflx_update ) : ?>
							<li class="aflx-timeline__item">
								<div class="aflx-person" style="margin-bottom:var(--aflanex-space-2)">
									<?php if ( $aflx_update['author'] ) : ?>
										<img class="aflx-avatar aflx-avatar--sm" src="<?php echo esc_url( $aflx_update['author']['avatar'] ); ?>" alt="" width="28" height="28" loading="lazy">
										<span class="aflx-person__name aflx-small"><?php echo esc_html( $aflx_update['author']['name'] ); ?></span>
									<?php endif; ?>
									<a class="aflx-small aflx-muted" href="<?php echo esc_url( $aflx_update['url'] ); ?>">
										<?php
										/* translators: %s: human time difference */
										echo esc_html( sprintf( __( '%s ago', 'aflanex-community' ), human_time_diff( (int) $aflx_update['time'] ) ) );
										?>
									</a>
								</div>
								<div class="aflx-prose aflx-small"><?php echo wp_kses_post( $aflx_update['html'] ); ?></div>
							</li>
						<?php endforeach; ?>
					</ol>
				<?php else : ?>
					<p class="aflx-muted aflx-small">
						<?php
						echo esc_html(
							$project['can_edit']
								? __( 'No updates yet. Short, regular updates help people follow along and offer help.', 'aflanex-community' )
								: __( 'No updates yet. Check back soon, or ask how it’s going in the discussion.', 'aflanex-community' )
						);
						?>
					</p>
				<?php endif; ?>
			</section>
		</div>

		<aside class="aflx-stack" aria-label="<?php esc_attr_e( 'Project details', 'aflanex-community' ); ?>">
			<div class="aflx-card">
				<h2 class="aflx-eyebrow"><?php esc_html_e( 'Team', 'aflanex-community' ); ?></h2>
				<ul style="list-style:none;margin:var(--aflanex-space-3) 0 0;padding:0;display:grid;gap:var(--aflanex-space-3)">
					<?php foreach ( $aflx_team as $aflx_i => $aflx_member ) : ?>
						<li>
							<a class="aflx-person" href="<?php echo esc_url( $aflx_member['portfolio_url'] ); ?>" style="text-decoration:none">
								<img class="aflx-avatar" src="<?php echo esc_url( $aflx_member['avatar'] ); ?>" alt="" width="40" height="40" loading="lazy">
								<span style="min-width:0">
									<span class="aflx-person__name" style="display:block"><?php echo esc_html( $aflx_member['name'] ); ?></span>
									<span class="aflx-person__meta" style="display:block"><?php echo esc_html( 0 === $aflx_i ? __( 'Project owner', 'aflanex-community' ) : ( $aflx_member['headline'] ?: __( 'Collaborator', 'aflanex-community' ) ) ); ?></span>
								</span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>

			<?php if ( $project['skill_names'] ) : ?>
				<div class="aflx-card">
					<h2 class="aflx-eyebrow"><?php esc_html_e( 'Skills used', 'aflanex-community' ); ?></h2>
					<ul class="aflx-chip-list" style="margin-top:var(--aflanex-space-3)">
						<?php foreach ( $project['skill_names'] as $aflx_skill ) : ?>
							<li><a class="aflx-chip" href="<?php echo esc_url( Pages::projects_url( [ 'skill' => sanitize_title( $aflx_skill ) ] ) ); ?>"><?php echo esc_html( $aflx_skill ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php if ( $project['links'] ) : ?>
				<div class="aflx-card">
					<h2 class="aflx-eyebrow"><?php esc_html_e( 'Links', 'aflanex-community' ); ?></h2>
					<ul style="list-style:none;margin:var(--aflanex-space-3) 0 0;padding:0;display:grid;gap:var(--aflanex-space-2)">
						<?php foreach ( $project['links'] as $aflx_link ) : ?>
							<li><a href="<?php echo esc_url( $aflx_link['url'] ); ?>" rel="noopener nofollow ugc" target="_blank"><?php echo View::icon( 'link', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( $aflx_link['label'] ); ?><span class="aflx-sr-only"> <?php esc_html_e( '(opens in a new tab)', 'aflanex-community' ); ?></span></a></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<dl class="aflx-card aflx-facts">
				<div>
					<dt><?php esc_html_e( 'Started', 'aflanex-community' ); ?></dt>
					<dd><?php echo esc_html( wp_date( get_option( 'date_format' ), (int) $project['created'] ) ); ?></dd>
				</div>
				<div>
					<dt><?php esc_html_e( 'Last activity', 'aflanex-community' ); ?></dt>
					<dd>
						<?php
						/* translators: %s: human time difference */
						echo esc_html( sprintf( __( '%s ago', 'aflanex-community' ), human_time_diff( (int) $project['updated'] ) ) );
						?>
					</dd>
				</div>
			</dl>
		</aside>
	</div>
</div>
