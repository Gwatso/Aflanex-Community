<?php
/**
 * Community guidelines (public). Content is the editable WordPress page.
 *
 * @var \WP_Post $post
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="aflx-page aflx-page--narrow">
	<header class="aflx-page-header">
		<div class="aflx-page-header__text">
			<p class="aflx-eyebrow"><?php esc_html_e( 'Aflanex Community', 'aflanex-community' ); ?></p>
			<h1 class="aflx-h1"><?php echo esc_html( get_the_title( $post ) ); ?></h1>
		</div>
	</header>
	<div class="aflx-prose aflx-guidelines">
		<?php echo apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core content filter ?>
	</div>
</div>
