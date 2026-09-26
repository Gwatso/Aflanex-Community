<?php
/**
 * Purposeful empty state: always explains and offers a next action.
 *
 * @var string $icon
 * @var string $title
 * @var string $text
 * @var string $action_url
 * @var string $action_label
 * @var string $secondary_url   (optional)
 * @var string $secondary_label (optional)
 */
defined( 'ABSPATH' ) || exit;

use Aflanex\Community\Support\View;
?>
<div class="aflx-empty">
	<div class="aflx-empty__icon"><?php echo View::icon( $icon ?? 'spark', 24 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG ?></div>
	<h2 class="aflx-empty__title"><?php echo esc_html( $title ); ?></h2>
	<p class="aflx-empty__text"><?php echo esc_html( $text ); ?></p>
	<div class="aflx-row" style="justify-content:center">
		<?php if ( ! empty( $action_url ) ) : ?>
			<a class="aflx-btn aflx-btn--primary" href="<?php echo esc_url( $action_url ); ?>"><?php echo esc_html( $action_label ); ?></a>
		<?php endif; ?>
		<?php if ( ! empty( $secondary_url ) ) : ?>
			<a class="aflx-btn aflx-btn--secondary" href="<?php echo esc_url( $secondary_url ); ?>"><?php echo esc_html( $secondary_label ); ?></a>
		<?php endif; ?>
	</div>
</div>
