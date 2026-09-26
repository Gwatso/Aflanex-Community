<?php
/**
 * Public entry page for Aflanex Community.
 *
 * Deliberately not a marketing site (that's aflanex.com). It explains what
 * the Community is, how members behave, and leads to Join / Sign in.
 * Signed-in members never see it; the plugin sends them to the portal.
 * All figures and spaces shown here are real data, and nothing is invented.
 */

defined( 'ABSPATH' ) || exit;

$aflx_brand  = aflanex_theme_brand();
$aflx_links  = function_exists( 'aflanex_community_links' ) ? aflanex_community_links() : [
	'sign_in' => wp_login_url(),
	'join'    => '',
	'sso'     => false,
];
$aflx_spaces = function_exists( 'aflanex_community_public_spaces' ) ? aflanex_community_public_spaces( 6 ) : [];
$aflx_stats  = function_exists( 'aflanex_community_public_stats' ) ? aflanex_community_public_stats() : [];

$aflx_icon = static function ( string $name ): string {
	$paths = [
		'connect' => '<circle cx="9" cy="8" r="3.25"/><circle cx="17" cy="9.5" r="2.5"/><path d="M3.5 19c.6-3 2.9-4.75 5.5-4.75S13.9 16 14.5 19"/><path d="M15 14.4c2.6-.2 4.6 1.3 5.1 4.1"/>',
		'build'   => '<path d="M4 7.5 12 3l8 4.5v9L12 21l-8-4.5z"/><path d="m4 7.5 8 4.5 8-4.5M12 12v9"/>',
		'apply'   => '<path d="M5 12.5 9.5 17 19 7.5"/>',
		'advance' => '<path d="M5 19 19 5"/><path d="M9 5h10v10"/>',
		'arrow'   => '<path d="M5 12h14M13 6l6 6-6 6"/>',
	];
	return '<svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">' . ( $paths[ $name ] ?? '' ) . '</svg>';
};
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="description" content="<?php echo esc_attr__( 'Aflanex Community is where people who are learning, building and doing what’s next meet, share their work and collaborate.', 'aflanex-community' ); ?>">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'aflx-scope aflx-landing' ); ?>>
<?php wp_body_open(); ?>
<a class="aflx-skip-link" href="#aflx-main"><?php esc_html_e( 'Skip to content', 'aflanex-community' ); ?></a>

<header class="aflx-landing-header">
	<div class="aflx-landing-container aflx-landing-header__inner">
		<?php aflanex_brand_mark(); ?>
		<nav class="aflx-landing-header__actions" aria-label="<?php esc_attr_e( 'Account', 'aflanex-community' ); ?>">
			<a class="aflx-btn aflx-btn--ghost" href="<?php echo esc_url( $aflx_links['sign_in'] ); ?>"><?php esc_html_e( 'Sign in', 'aflanex-community' ); ?></a>
			<?php if ( $aflx_links['join'] ) : ?>
				<a class="aflx-btn aflx-btn--primary aflx-hide-sm" href="<?php echo esc_url( $aflx_links['join'] ); ?>"><?php esc_html_e( 'Join the community', 'aflanex-community' ); ?></a>
			<?php endif; ?>
		</nav>
	</div>
</header>

<main id="aflx-main">
	<section class="aflx-hero" aria-labelledby="aflx-hero-title">
		<div class="aflx-landing-container">
			<p class="aflx-eyebrow"><?php echo esc_html( $aflx_brand['name'] . ' ' . $aflx_brand['product'] ); ?></p>
			<h1 id="aflx-hero-title" class="aflx-hero__title"><?php esc_html_e( 'Build what’s next together.', 'aflanex-community' ); ?></h1>
			<p class="aflx-hero__lead">
				<?php esc_html_e( 'Meet people who are learning, building and doing what’s next. Share what you’re working on, get useful feedback, start projects and find the people to build them with.', 'aflanex-community' ); ?>
			</p>
			<div class="aflx-hero__actions">
				<?php if ( $aflx_links['join'] ) : ?>
					<a class="aflx-btn aflx-btn--primary aflx-btn--lg" href="<?php echo esc_url( $aflx_links['join'] ); ?>"><?php esc_html_e( 'Join the community', 'aflanex-community' ); ?></a>
					<a class="aflx-btn aflx-btn--secondary aflx-btn--lg" href="<?php echo esc_url( $aflx_links['sign_in'] ); ?>"><?php esc_html_e( 'Sign in', 'aflanex-community' ); ?></a>
				<?php else : ?>
					<a class="aflx-btn aflx-btn--primary aflx-btn--lg" href="<?php echo esc_url( $aflx_links['sign_in'] ); ?>"><?php esc_html_e( 'Sign in', 'aflanex-community' ); ?></a>
				<?php endif; ?>
			</div>
			<?php if ( ! empty( $aflx_links['sso'] ) ) : ?>
				<p class="aflx-hero__note"><?php esc_html_e( 'Learning on Erudify? Use the same account. There’s nothing new to set up.', 'aflanex-community' ); ?></p>
			<?php elseif ( ! $aflx_links['join'] ) : ?>
				<p class="aflx-hero__note"><?php esc_html_e( 'Membership is opening in stages. Existing members can sign in now.', 'aflanex-community' ); ?></p>
			<?php endif; ?>

			<ol class="aflx-path" aria-label="<?php esc_attr_e( 'How the community works', 'aflanex-community' ); ?>">
				<li><span><?php esc_html_e( 'Learn', 'aflanex-community' ); ?></span></li>
				<li><span><?php esc_html_e( 'Build', 'aflanex-community' ); ?></span></li>
				<li><span><?php esc_html_e( 'Apply', 'aflanex-community' ); ?></span></li>
				<li><span><?php esc_html_e( 'Advance', 'aflanex-community' ); ?></span></li>
			</ol>
		</div>
	</section>

	<section class="aflx-landing-section" aria-labelledby="aflx-do-title">
		<div class="aflx-landing-container">
			<div class="aflx-landing-section__head">
				<h2 id="aflx-do-title" class="aflx-landing-h2"><?php esc_html_e( 'What you can do here', 'aflanex-community' ); ?></h2>
				<p class="aflx-lead"><?php esc_html_e( 'Learning is where it starts. Here is where you start doing something with it.', 'aflanex-community' ); ?></p>
			</div>
			<ul class="aflx-feature-grid">
				<li class="aflx-feature">
					<span class="aflx-feature__icon"><?php echo $aflx_icon( 'connect' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG ?></span>
					<h3 class="aflx-h3"><?php esc_html_e( 'Connect', 'aflanex-community' ); ?></h3>
					<p><?php esc_html_e( 'Join spaces built around shared goals. Find people by the skills they have and the things they’re working on.', 'aflanex-community' ); ?></p>
				</li>
				<li class="aflx-feature">
					<span class="aflx-feature__icon"><?php echo $aflx_icon( 'build' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<h3 class="aflx-h3"><?php esc_html_e( 'Build', 'aflanex-community' ); ?></h3>
					<p><?php esc_html_e( 'Start a project, post progress updates and invite collaborators. Ideas are welcome. Shipped work is celebrated.', 'aflanex-community' ); ?></p>
				</li>
				<li class="aflx-feature">
					<span class="aflx-feature__icon"><?php echo $aflx_icon( 'apply' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<h3 class="aflx-h3"><?php esc_html_e( 'Apply', 'aflanex-community' ); ?></h3>
					<p><?php esc_html_e( 'Ask the community, get feedback on real work and help others with what you already know.', 'aflanex-community' ); ?></p>
				</li>
				<li class="aflx-feature">
					<span class="aflx-feature__icon"><?php echo $aflx_icon( 'advance' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<h3 class="aflx-h3"><?php esc_html_e( 'Advance', 'aflanex-community' ); ?></h3>
					<p><?php esc_html_e( 'Your projects and contributions build a portfolio that shows what you can actually do.', 'aflanex-community' ); ?></p>
				</li>
			</ul>
		</div>
	</section>

	<?php if ( $aflx_spaces ) : ?>
		<section class="aflx-landing-section aflx-landing-section--tint" aria-labelledby="aflx-spaces-title">
			<div class="aflx-landing-container">
				<div class="aflx-landing-section__head">
					<h2 id="aflx-spaces-title" class="aflx-landing-h2"><?php esc_html_e( 'Where conversations happen', 'aflanex-community' ); ?></h2>
					<p class="aflx-lead"><?php esc_html_e( 'Spaces bring together people working towards similar things.', 'aflanex-community' ); ?></p>
				</div>
				<ul class="aflx-space-list">
					<?php foreach ( $aflx_spaces as $aflx_space ) : ?>
						<li class="aflx-space-item">
							<span class="aflx-space-item__emoji" aria-hidden="true"><?php echo esc_html( $aflx_space['emoji'] ?: '•' ); ?></span>
							<span>
								<strong><?php echo esc_html( $aflx_space['title'] ); ?></strong>
								<?php if ( $aflx_space['description'] ) : ?>
									<span class="aflx-space-item__desc"><?php echo esc_html( $aflx_space['description'] ); ?></span>
								<?php endif; ?>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
				<?php if ( ! empty( $aflx_stats['show'] ) ) : ?>
					<p class="aflx-muted aflx-small aflx-stats-line">
						<?php
						printf(
							/* translators: 1: member count, 2: project count */
							esc_html__( '%1$s members · %2$s projects shared so far', 'aflanex-community' ),
							esc_html( number_format_i18n( $aflx_stats['members'] ) ),
							esc_html( number_format_i18n( $aflx_stats['projects'] ) )
						);
						?>
					</p>
				<?php endif; ?>
			</div>
		</section>
	<?php endif; ?>

	<section class="aflx-landing-section" aria-labelledby="aflx-culture-title">
		<div class="aflx-landing-container">
			<div class="aflx-landing-section__head">
				<h2 id="aflx-culture-title" class="aflx-landing-h2"><?php esc_html_e( 'How we show up', 'aflanex-community' ); ?></h2>
				<p class="aflx-lead"><?php esc_html_e( 'You’re here to take part, not just to scroll. A few habits keep the community useful:', 'aflanex-community' ); ?></p>
			</div>
			<dl class="aflx-culture">
				<div><dt><?php esc_html_e( 'Ask questions', 'aflanex-community' ); ?></dt><dd><?php esc_html_e( 'Curiosity is welcome. There are no basic questions, only unasked ones.', 'aflanex-community' ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Own your growth', 'aflanex-community' ); ?></dt><dd><?php esc_html_e( 'Set your direction, share progress and follow through.', 'aflanex-community' ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Show your work', 'aflanex-community' ); ?></dt><dd><?php esc_html_e( 'Real projects and honest updates beat polished claims.', 'aflanex-community' ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Keep adjusting', 'aflanex-community' ); ?></dt><dd><?php esc_html_e( 'Learn, unlearn and try again. Feedback is a gift.', 'aflanex-community' ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Make it useful', 'aflanex-community' ); ?></dt><dd><?php esc_html_e( 'Help someone else move forward whenever you can.', 'aflanex-community' ); ?></dd></div>
			</dl>
		</div>
	</section>

	<section class="aflx-landing-cta" aria-labelledby="aflx-cta-title">
		<div class="aflx-landing-container">
			<h2 id="aflx-cta-title" class="aflx-landing-h2"><?php esc_html_e( 'Your next step starts with a conversation.', 'aflanex-community' ); ?></h2>
			<div class="aflx-hero__actions">
				<?php if ( $aflx_links['join'] ) : ?>
					<a class="aflx-btn aflx-btn--accent aflx-btn--lg" href="<?php echo esc_url( $aflx_links['join'] ); ?>"><?php esc_html_e( 'Join the community', 'aflanex-community' ); ?> <?php echo $aflx_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
				<?php endif; ?>
				<a class="aflx-btn aflx-btn--on-dark aflx-btn--lg" href="<?php echo esc_url( $aflx_links['sign_in'] ); ?>"><?php esc_html_e( 'Sign in', 'aflanex-community' ); ?></a>
			</div>
		</div>
	</section>
</main>

<footer class="aflx-landing-footer">
	<div class="aflx-landing-container aflx-landing-footer__inner">
		<?php aflanex_brand_mark( [ 'class' => 'aflx-brand--footer' ] ); ?>
		<nav aria-label="<?php esc_attr_e( 'Footer', 'aflanex-community' ); ?>">
			<ul class="aflx-footer-links">
				<?php if ( ! empty( $aflx_links['guidelines'] ) ) : ?>
					<li><a href="<?php echo esc_url( $aflx_links['guidelines'] ); ?>"><?php esc_html_e( 'Community guidelines', 'aflanex-community' ); ?></a></li>
				<?php endif; ?>
				<?php if ( get_privacy_policy_url() ) : ?>
					<li><a href="<?php echo esc_url( get_privacy_policy_url() ); ?>"><?php esc_html_e( 'Privacy', 'aflanex-community' ); ?></a></li>
				<?php endif; ?>
				<?php if ( ! empty( $aflx_links['erudify'] ) ) : ?>
					<li><a href="<?php echo esc_url( $aflx_links['erudify'] ); ?>"><?php esc_html_e( 'Learn on Erudify', 'aflanex-community' ); ?></a></li>
				<?php endif; ?>
			</ul>
		</nav>
		<p class="aflx-muted aflx-small">&copy; <?php echo esc_html( wp_date( 'Y' ) . ' ' . $aflx_brand['name'] ); ?></p>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
