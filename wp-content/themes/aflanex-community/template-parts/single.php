<?php
/**
 * Singular content. FluentCommunity's Frame template calls this part for
 * Hello Elementor, so it's where Aflanex screens (Projects, People,
 * Portfolio) are rendered inside the community header and sidebar.
 *
 * The plugin decides what to render; anything it doesn't handle falls
 * back to Hello Elementor's standard singular markup.
 */

defined( 'ABSPATH' ) || exit;

if ( function_exists( 'aflanex_community_render_frame_content' ) && aflanex_community_render_frame_content() ) {
	return;
}

while ( have_posts() ) :
	the_post();
	?>
	<main id="content" <?php post_class( 'site-main' ); ?>>
		<?php if ( apply_filters( 'hello_elementor_page_title', true ) ) : ?>
			<div class="page-header">
				<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
			</div>
		<?php endif; ?>

		<div class="page-content">
			<?php the_content(); ?>
			<?php wp_link_pages(); ?>
		</div>

		<?php comments_template(); ?>
	</main>
	<?php
endwhile;
