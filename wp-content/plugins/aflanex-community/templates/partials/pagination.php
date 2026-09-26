<?php
/**
 * @var int    $current
 * @var int    $pages
 * @var string $base_url  URL (with current filters) to add ?pg= to
 */
defined( 'ABSPATH' ) || exit;

if ( $pages < 2 ) {
	return;
}
?>
<nav class="aflx-row aflx-pagination" aria-label="<?php esc_attr_e( 'Pages', 'aflanex-community' ); ?>" style="justify-content:center;margin-top:var(--aflanex-space-8)">
	<?php if ( $current > 1 ) : ?>
		<a class="aflx-btn aflx-btn--secondary aflx-btn--sm" href="<?php echo esc_url( add_query_arg( 'pg', $current - 1, $base_url ) ); ?>"><?php esc_html_e( 'Previous', 'aflanex-community' ); ?></a>
	<?php endif; ?>
	<span class="aflx-small aflx-muted">
		<?php
		/* translators: 1: current page, 2: total pages */
		echo esc_html( sprintf( __( 'Page %1$d of %2$d', 'aflanex-community' ), $current, $pages ) );
		?>
	</span>
	<?php if ( $current < $pages ) : ?>
		<a class="aflx-btn aflx-btn--secondary aflx-btn--sm" href="<?php echo esc_url( add_query_arg( 'pg', $current + 1, $base_url ) ); ?>"><?php esc_html_e( 'Next', 'aflanex-community' ); ?></a>
	<?php endif; ?>
</nav>
