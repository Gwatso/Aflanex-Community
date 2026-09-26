<?php
/**
 * @var array $project Projects::summary()
 */
defined( 'ABSPATH' ) || exit;

$aflx_people = array_filter( array_merge( [ $project['owner'] ], $project['collaborators'] ) );
?>
<article class="aflx-card aflx-card--link aflx-project-card">
	<?php if ( $project['cover'] ) : ?>
		<img class="aflx-project-card__cover" src="<?php echo esc_url( $project['cover'] ); ?>" alt="" loading="lazy" decoding="async">
	<?php endif; ?>
	<div class="aflx-row aflx-row--between">
		<span class="aflx-status aflx-status--<?php echo esc_attr( $project['status'] ); ?>"><?php echo esc_html( $project['status_label'] ); ?></span>
		<?php if ( $project['areas'] ) : ?>
			<span class="aflx-small aflx-muted"><?php echo esc_html( $project['areas'][0] ); ?></span>
		<?php endif; ?>
	</div>
	<h3 class="aflx-card__title"><a href="<?php echo esc_url( $project['url'] ); ?>"><?php echo esc_html( $project['title'] ); ?></a></h3>
	<?php if ( $project['summary'] ) : ?>
		<p class="aflx-card__body"><?php echo esc_html( $project['summary'] ); ?></p>
	<?php endif; ?>
	<?php if ( 'seeking_collaborators' === $project['status'] && $project['looking_for'] ) : ?>
		<p class="aflx-small"><strong><?php esc_html_e( 'Looking for:', 'aflanex-community' ); ?></strong> <?php echo esc_html( $project['looking_for'] ); ?></p>
	<?php endif; ?>
	<?php if ( $project['skills'] ) : ?>
		<ul class="aflx-chip-list" aria-label="<?php esc_attr_e( 'Skills', 'aflanex-community' ); ?>">
			<?php foreach ( array_slice( $project['skills'], 0, 4 ) as $aflx_skill ) : ?>
				<li class="aflx-chip"><?php echo esc_html( $aflx_skill ); ?></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
	<div class="aflx-card__footer aflx-row aflx-row--between">
		<div class="aflx-person">
			<span class="aflx-avatar-stack">
				<?php foreach ( array_slice( $aflx_people, 0, 3 ) as $aflx_p ) : ?>
					<img class="aflx-avatar aflx-avatar--sm" src="<?php echo esc_url( $aflx_p['avatar'] ); ?>" alt="" loading="lazy" width="28" height="28">
				<?php endforeach; ?>
			</span>
			<span class="aflx-person__meta">
				<?php
				echo esc_html( $project['owner']['name'] ?? '' );
				if ( count( $aflx_people ) > 1 ) {
					/* translators: %d: number of other collaborators */
					echo esc_html( sprintf( _n( ' + %d other', ' + %d others', count( $aflx_people ) - 1, 'aflanex-community' ), count( $aflx_people ) - 1 ) );
				}
				?>
			</span>
		</div>
		<span class="aflx-small aflx-muted">
			<?php
			/* translators: %s: human time difference */
			echo esc_html( sprintf( __( 'Active %s ago', 'aflanex-community' ), human_time_diff( (int) $project['updated'] ) ) );
			?>
		</span>
	</div>
</article>
