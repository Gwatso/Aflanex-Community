<?php
/**
 * Learning summary received from a connected integration (e.g. Erudify).
 * Rendered only when a real provider returns data. See
 * Integrations\LearningSummaryProvider for the expected shape.
 *
 * @var array $learning
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="aflx-card" style="margin-top:var(--aflanex-space-3)">
	<?php if ( ! empty( $learning['programmes'] ) ) : ?>
		<ul style="margin:0;padding-left:1.1em">
			<?php foreach ( (array) $learning['programmes'] as $aflx_programme ) : ?>
				<li><?php echo esc_html( $aflx_programme['title'] ?? '' ); ?><?php echo ! empty( $aflx_programme['status'] ) ? ' · <span class="aflx-muted">' . esc_html( $aflx_programme['status'] ) . '</span>' : ''; ?></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
	<?php if ( ! empty( $learning['milestones'] ) ) : ?>
		<ul style="margin:var(--aflanex-space-3) 0 0;padding-left:1.1em">
			<?php foreach ( (array) $learning['milestones'] as $aflx_milestone ) : ?>
				<li><?php echo esc_html( $aflx_milestone['title'] ?? '' ); ?></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
	<p class="aflx-small aflx-muted" style="margin:var(--aflanex-space-3) 0 0">
		<?php
		/* translators: %s: source system name */
		echo esc_html( sprintf( __( 'From %s', 'aflanex-community' ), ucfirst( (string) ( $learning['source'] ?? '' ) ) ) );
		?>
		<?php if ( ! empty( $learning['url'] ) ) : ?>
			· <a href="<?php echo esc_url( $learning['url'] ); ?>"><?php esc_html_e( 'View learning record', 'aflanex-community' ); ?></a>
		<?php endif; ?>
	</p>
</div>
