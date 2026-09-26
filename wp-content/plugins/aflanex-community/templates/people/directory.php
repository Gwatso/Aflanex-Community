<?php
/**
 * People: "Who can I connect with?" Discovery by skills, focus areas and
 * what people are open to. No follower counts, no popularity ranking.
 *
 * @var array            $filters
 * @var array            $result
 * @var \WP_Term[]|mixed $areas
 * @var array            $open_to
 * @var array|null       $me
 */
defined( 'ABSPATH' ) || exit;

use Aflanex\Community\Pages\Pages;
use Aflanex\Community\Profiles\MemberProfile;
use Aflanex\Community\Support\View;

$aflx_areas     = is_array( $areas ) ? $areas : [];
$aflx_filtering = $filters['search'] || $filters['area'] || $filters['skill'] || $filters['open_to'];
$aflx_base      = Pages::people_url( array_filter( [ 'q' => $filters['search'], 'area' => $filters['area'], 'skill' => $filters['skill'], 'open_to' => $filters['open_to'] ] ) );
$aflx_has_me    = $me && get_user_meta( $me['id'], 'aflx_headline', true );
?>
<div class="aflx-page">
	<header class="aflx-page-header">
		<div class="aflx-page-header__text">
			<p class="aflx-eyebrow"><?php esc_html_e( 'Connect', 'aflanex-community' ); ?></p>
			<h1 class="aflx-h1"><?php esc_html_e( 'People', 'aflanex-community' ); ?></h1>
			<p class="aflx-lead"><?php esc_html_e( 'Find people with similar interests, skills you need, or someone you can help.', 'aflanex-community' ); ?></p>
		</div>
		<?php if ( $me ) : ?>
			<a class="aflx-btn aflx-btn--secondary" href="<?php echo esc_url( $aflx_has_me ? $me['portfolio_url'] : Pages::portfolio_edit_url() ); ?>"><?php echo esc_html( $aflx_has_me ? __( 'View your portfolio', 'aflanex-community' ) : __( 'Complete your portfolio', 'aflanex-community' ) ); ?></a>
		<?php endif; ?>
	</header>

	<details class="aflx-filter-panel" data-aflx-open-desktop <?php echo $aflx_filtering ? 'open' : ''; ?>>
		<summary class="aflx-btn aflx-btn--secondary aflx-btn--sm aflx-filter-panel__toggle"><?php esc_html_e( 'Search & filter', 'aflanex-community' ); ?></summary>
		<form class="aflx-filters" method="get" action="<?php echo esc_url( Pages::people_url() ); ?>" role="search">
			<div class="aflx-field">
				<label class="aflx-label" for="aflx-people-q"><?php esc_html_e( 'Search by name', 'aflanex-community' ); ?></label>
				<input class="aflx-input" id="aflx-people-q" type="search" name="q" value="<?php echo esc_attr( $filters['search'] ); ?>">
			</div>
			<div class="aflx-field">
				<label class="aflx-label" for="aflx-people-area"><?php esc_html_e( 'Interested in', 'aflanex-community' ); ?></label>
				<select class="aflx-select" id="aflx-people-area" name="area">
					<option value=""><?php esc_html_e( 'Any area', 'aflanex-community' ); ?></option>
					<?php foreach ( $aflx_areas as $aflx_term ) : ?>
						<option value="<?php echo esc_attr( $aflx_term->term_id ); ?>" <?php selected( (int) $filters['area'], (int) $aflx_term->term_id ); ?>><?php echo esc_html( $aflx_term->name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="aflx-field">
				<label class="aflx-label" for="aflx-people-open"><?php esc_html_e( 'Open to', 'aflanex-community' ); ?></label>
				<select class="aflx-select" id="aflx-people-open" name="open_to">
					<option value=""><?php esc_html_e( 'Anything', 'aflanex-community' ); ?></option>
					<?php foreach ( $open_to as $aflx_key => $aflx_label ) : ?>
						<option value="<?php echo esc_attr( $aflx_key ); ?>" <?php selected( $filters['open_to'], $aflx_key ); ?>><?php echo esc_html( $aflx_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="aflx-row">
				<?php if ( $filters['skill'] ) : ?><input type="hidden" name="skill" value="<?php echo esc_attr( $filters['skill'] ); ?>"><?php endif; ?>
				<button class="aflx-btn aflx-btn--secondary" type="submit"><?php esc_html_e( 'Find people', 'aflanex-community' ); ?></button>
				<?php if ( $aflx_filtering ) : ?>
					<a class="aflx-btn aflx-btn--ghost" href="<?php echo esc_url( Pages::people_url() ); ?>"><?php esc_html_e( 'Clear', 'aflanex-community' ); ?></a>
				<?php endif; ?>
			</div>
		</form>
	</details>
	<script>document.querySelectorAll('details[data-aflx-open-desktop]').forEach(function(d){if(window.matchMedia('(min-width: 768px)').matches){d.open=true;}});</script>

	<?php if ( $filters['skill'] ) : ?>
		<p class="aflx-small aflx-muted" style="margin-bottom:var(--aflanex-space-4)">
			<?php
			/* translators: %s: skill */
			echo esc_html( sprintf( __( 'People with the skill “%s”.', 'aflanex-community' ), str_replace( '-', ' ', $filters['skill'] ) ) );
			?>
		</p>
	<?php endif; ?>

	<?php if ( $result['items'] ) : ?>
		<p class="aflx-sr-only" role="status">
			<?php
			/* translators: %d: number of people */
			echo esc_html( sprintf( _n( '%d person found', '%d people found', $result['total'], 'aflanex-community' ), $result['total'] ) );
			?>
		</p>
		<ul class="aflx-grid aflx-people-grid">
			<?php foreach ( $result['items'] as $aflx_person ) : ?>
				<li class="aflx-card aflx-card--link aflx-person-card">
					<div class="aflx-person">
						<img class="aflx-avatar aflx-avatar--lg" src="<?php echo esc_url( $aflx_person['avatar'] ); ?>" alt="" width="72" height="72" loading="lazy">
						<div style="min-width:0">
							<h2 class="aflx-card__title"><a href="<?php echo esc_url( $aflx_person['portfolio_url'] ); ?>"><?php echo esc_html( $aflx_person['name'] ); ?></a></h2>
							<?php if ( $aflx_person['headline'] ) : ?>
								<p class="aflx-small" style="margin:2px 0 0;color:var(--aflanex-text-secondary)"><?php echo esc_html( $aflx_person['headline'] ); ?></p>
							<?php endif; ?>
							<?php if ( $aflx_person['location'] ) : ?>
								<p class="aflx-small aflx-muted" style="margin:2px 0 0"><?php echo View::icon( 'pin', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( $aflx_person['location'] ); ?></p>
							<?php endif; ?>
						</div>
					</div>
					<?php if ( $aflx_person['skills'] ) : ?>
						<ul class="aflx-chip-list" aria-label="<?php esc_attr_e( 'Skills', 'aflanex-community' ); ?>">
							<?php foreach ( $aflx_person['skills'] as $aflx_skill ) : ?>
								<li class="aflx-chip"><?php echo esc_html( $aflx_skill ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
					<?php if ( $aflx_person['can_help_with'] ) : ?>
						<p class="aflx-small" style="margin-bottom:0"><strong><?php esc_html_e( 'Can help with:', 'aflanex-community' ); ?></strong> <?php echo esc_html( $aflx_person['can_help_with'] ); ?></p>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php View::render( 'partials/pagination', [ 'current' => (int) $filters['paged'], 'pages' => (int) $result['pages'], 'base_url' => $aflx_base ] ); ?>

	<?php elseif ( $aflx_filtering ) : ?>
		<?php
		View::render(
			'partials/empty',
			[
				'icon'         => 'search',
				'title'        => __( 'No one matches that yet.', 'aflanex-community' ),
				'text'         => __( 'The community is still growing. Try a broader search, or ask in a space and let people find you.', 'aflanex-community' ),
				'action_url'   => Pages::people_url(),
				'action_label' => __( 'See everyone', 'aflanex-community' ),
			]
		);
		?>
	<?php else : ?>
		<?php
		View::render(
			'partials/empty',
			[
				'icon'         => 'people',
				'title'        => __( 'Meet your community.', 'aflanex-community' ),
				'text'         => __( 'Members appear here once they join. Add your skills and interests so the right people can find you.', 'aflanex-community' ),
				'action_url'   => Pages::portfolio_edit_url(),
				'action_label' => __( 'Complete your portfolio', 'aflanex-community' ),
			]
		);
		?>
	<?php endif; ?>

	<p class="aflx-small aflx-muted" style="margin-top:var(--aflanex-space-8)">
		<?php esc_html_e( 'Only signed-in members can see this directory. You can hide yourself from it in your portfolio settings.', 'aflanex-community' ); ?>
	</p>
</div>
