<?php
/**
 * Brand mark: the ONE place the Aflanex logo is rendered in theme markup.
 *
 * Until the official logo is approved this prints a typographic wordmark.
 * To switch to the final logo, add these files and nothing else changes:
 *   assets/brand/logo.svg       (for light backgrounds)
 *   assets/brand/logo-dark.svg  (optional, for dark backgrounds)
 * The FluentCommunity portal header picks up the same files automatically
 * (see Aflanex\Community\Brand\Brand::logo()).
 *
 * @var array $args {
 *     @type string $href     Link target. Default: front page.
 *     @type bool   $compact  Hide the "Community" product label.
 *     @type string $class    Extra classes.
 * }
 */

defined( 'ABSPATH' ) || exit;

$aflx_args = wp_parse_args(
	$args ?? [],
	[
		'href'    => home_url( '/' ),
		'compact' => false,
		'class'   => '',
	]
);

$aflx_brand = aflanex_theme_brand();
$aflx_class = trim( 'aflx-brand ' . ( $aflx_args['compact'] ? 'aflx-brand--compact ' : '' ) . $aflx_args['class'] );
?>
<a class="<?php echo esc_attr( $aflx_class ); ?>" href="<?php echo esc_url( $aflx_args['href'] ); ?>" aria-label="<?php echo esc_attr( $aflx_brand['name'] . ' ' . $aflx_brand['product'] . ', home' ); ?>">
	<?php if ( ! empty( $aflx_brand['logo'] ) ) : ?>
		<img class="aflx-brand__logo" src="<?php echo esc_url( $aflx_brand['logo'] ); ?>" alt="" width="120" height="28">
	<?php else : ?>
		<span class="aflx-brand__word"><?php echo esc_html( $aflx_brand['name'] ); ?></span>
	<?php endif; ?>
	<span class="aflx-brand__product"><?php echo esc_html( $aflx_brand['product'] ); ?></span>
</a>
