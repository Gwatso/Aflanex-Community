<?php
/**
 * Project directory.
 *
 * @var array            $filters
 * @var array            $result   items/total/pages
 * @var \WP_Term[]|mixed $areas
 * @var array            $statuses
 * @var bool             $has_any
 */
defined( 'ABSPATH' ) || exit;

use Aflanex\Community\Pages\Pages;
use Aflanex\Community\Profiles\MemberProfile;
use Aflanex\Community\Support\View;

$aflx_filtering = $filters['status'] || $filters['area'] || $filters['skill'] || $filters['search'];
$aflx_base      = Pages::projects_url( array_filter( [ 'status' => $filters['status'], 'area' => $filters['area'], 'skill' => $filters['skill'], 'q' => $filters['search'], 'mine' => $filters['member'] ? 1 : 0 ] ) );
$aflx_areas     = is_array( $areas ) ? $areas : [];
?>
<div class="aflx-page">
	<?php MemberProfile::render_notice(); ?>

	<header class="aflx-page-header">
		<div class="aflx-page-header__text">
			<p class="aflx-eyebrow"><?php esc_html_e( 'Build', 'aflanex-community' ); ?></p>
			<h1 class="aflx-h1"><?php esc_html_e( 'Projects', 'aflanex-community' ); ?></h1>
			<p class="aflx-lead"><?php esc_html_e( 'What members are building. Join in, give feedback or start your own.', 'aflanex-community' ); ?></p>
		</div>
		<a class="aflx-btn aflx-btn--primary" href="<?php echo esc_url( Pages::project_form_url() ); ?>"><?php echo View::icon( 'plus', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Start a project', 'aflanex-community' ); ?></a>
	</header>

	<nav class="aflx-tabs" aria-label="<?php esc_attr_e( 'Project views', 'aflanex-community' ); ?>">
		<a class="aflx-tab" href="<?php echo esc_url( Pages::projects_url() ); ?>" <?php echo ( ! $filters['member'] && ! $filters['status'] ) ? 'aria-current="page"' : ''; ?>><?php esc_html_e( 'All projects', 'aflanex-community' ); ?></a>
		<a class="aflx-tab" href="<?php echo esc_url( Pages::projects_url( [ 'status' => 'seeking_collaborators' ] ) ); ?>" <?php echo 'seeking_collaborators' === $filters['status'] && ! $filters['member'] ? 'aria-current="page"' : ''; ?>><?php esc_html_e( 'Seeking collaborators', 'aflanex-community' ); ?></a>
		<a class="aflx-tab" href="<?php echo esc_url( Pages::projects_url( [ 'status' => 'ready_for_feedback' ] ) ); ?>" <?php echo 'ready_for_feedback' === $filters['status'] && ! $filters['member'] ? 'aria-current="page"' : ''; ?>><?php esc_html_e( 'Needs feedback', 'aflanex-community' ); ?></a>
		<a class="aflx-tab" href="<?php echo esc_url( Pages::projects_url( [ 'mine' => 1 ] ) ); ?>" <?php echo $filters['member'] ? 'aria-current="page"' : ''; ?>><?php esc_html_e( 'My projects', 'aflanex-community' ); ?></a>
	</nav>

	<?php if ( $has_any ) : ?>
		<details class="aflx-filter-panel" data-aflx-open-desktop <?php echo $aflx_filtering ? 'open' : ''; ?>>
		<summary class="aflx-btn aflx-btn--secondary aflx-btn--sm aflx-filter-panel__toggle"><?php esc_html_e( 'Search & filter', 'aflanex-community' ); ?></summary>
		<form class="aflx-filters" method="get" action="<?php echo esc_url( Pages::projects_url() ); ?>" role="search">
				<?php if ( $filters['member'] ) : ?><input type="hidden" name="mine" value="1"><?php endif; ?>
				<div class="aflx-field">
					<label class="aflx-label" for="aflx-q"><?php esc_html_e( 'Search projects', 'aflanex-community' ); ?></label>
					<input class="aflx-input" id="aflx-q" type="search" name="q" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php esc_attr_e( 'Name, idea or problem', 'aflanex-community' ); ?>">
				</div>
				<div class="aflx-field">
					<label class="aflx-label" for="aflx-status"><?php esc_html_e( 'Status', 'aflanex-community' ); ?></label>
					<select class="aflx-select" id="aflx-status" name="status">
						<option value=""><?php esc_html_e( 'Any status', 'aflanex-community' ); ?></option>
						<?php foreach ( $statuses as $aflx_key => $aflx_label ) : ?>
							<option value="<?php echo esc_attr( $aflx_key ); ?>" <?php selected( $filters['status'], $aflx_key ); ?>><?php echo esc_html( $aflx_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="aflx-field">
					<label class="aflx-label" for="aflx-area"><?php esc_html_e( 'Focus area', 'aflanex-community' ); ?></label>
					<select class="aflx-select" id="aflx-area" name="area">
						<option value=""><?php esc_html_e( 'All areas', 'aflanex-community' ); ?></option>
						<?php foreach ( $aflx_areas as $aflx_term ) : ?>
							<option value="<?php echo esc_attr( $aflx_term->term_id ); ?>" <?php selected( (int) $filters['area'], (int) $aflx_term->term_id ); ?>><?php echo esc_html( $aflx_term->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="aflx-row">
					<button class="aflx-btn aflx-btn--secondary" type="submit"><?php esc_html_e( 'Apply', 'aflanex-community' ); ?></button>
					<?php if ( $aflx_filtering ) : ?>
						<a class="aflx-btn aflx-btn--ghost" href="<?php echo esc_url( Pages::projects_url( $filters['member'] ? [ 'mine' => 1 ] : [] ) ); ?>"><?php esc_html_e( 'Clear', 'aflanex-community' ); ?></a>
					<?php endif; ?>
				</div>
			</form>
	</details>
	<script>document.querySelectorAll('details[data-aflx-open-desktop]').forEach(function(d){if(window.matchMedia('(min-width: 768px)').matches){d.open=true;}});</script>
	<?php endif; ?>

	<?php if ( $filters['skill'] ) : ?>
		<p class="aflx-small aflx-muted" style="margin-bottom:var(--aflanex-space-4)">
			<?php
			/* translators: %s: skill */
			echo esc_html( sprintf( __( 'Showing projects using “%s”.', 'aflanex-community' ), str_replace( '-', ' ', $filters['skill'] ) ) );
			?>
		</p>
	<?php endif; ?>

	<?php if ( $result['items'] ) : ?>
		<p class="aflx-sr-only" role="status">
			<?php
			/* translators: %d: number of projects */
			echo esc_html( sprintf( _n( '%d project found', '%d projects found', $result['total'], 'aflanex-community' ), $result['total'] ) );
			?>
		</p>
		<h2 class="aflx-sr-only"><?php esc_html_e( 'Project list', 'aflanex-community' ); ?></h2>
		<div class="aflx-grid">
			<?php foreach ( $result['items'] as $aflx_project ) : ?>
				<?php View::render( 'partials/project-card', [ 'project' => $aflx_project ] ); ?>
			<?php endforeach; ?>
		</div>
		<?php View::render( 'partials/pagination', [ 'current' => (int) $filters['paged'], 'pages' => (int) $result['pages'], 'base_url' => $aflx_base ] ); ?>

	<?php elseif ( $filters['member'] ) : ?>
		<?php
		View::render(
			'partials/empty',
			[
				'icon'         => 'build',
				'title'        => __( 'You haven’t shared a project yet.', 'aflanex-community' ),
				'text'         => __( 'It doesn’t need to be finished. An idea, a practice build or a problem you want to solve all count.', 'aflanex-community' ),
				'action_url'   => Pages::project_form_url(),
				'action_label' => __( 'Start a project', 'aflanex-community' ),
			]
		);
		?>
	<?php elseif ( $aflx_filtering ) : ?>
		<?php
		View::render(
			'partials/empty',
			[
				'icon'            => 'search',
				'title'           => __( 'Nothing matches those filters yet.', 'aflanex-community' ),
				'text'            => __( 'Try a broader search, or be the first to start something in this area.', 'aflanex-community' ),
				'action_url'      => Pages::projects_url(),
				'action_label'    => __( 'See all projects', 'aflanex-community' ),
				'secondary_url'   => Pages::project_form_url(),
				'secondary_label' => __( 'Start a project', 'aflanex-community' ),
			]
		);
		?>
	<?php else : ?>
		<?php
		View::render(
			'partials/empty',
			[
				'icon'         => 'build',
				'title'        => __( 'No projects yet. What are you building?', 'aflanex-community' ),
				'text'         => __( 'Share an idea, a work-in-progress or something you’ve finished. Other members can offer feedback, skills or a second pair of hands.', 'aflanex-community' ),
				'action_url'   => Pages::project_form_url(),
				'action_label' => __( 'Start the first project', 'aflanex-community' ),
			]
		);
		?>
	<?php endif; ?>
</div>
