<?php
/**
 * Edit portfolio details (+ the full getting-started checklist).
 *
 * @var array            $member
 * @var \WP_Term[]|mixed $areas
 */
defined( 'ABSPATH' ) || exit;

use Aflanex\Community\Profiles\MemberProfile;
use Aflanex\Community\Projects\ProjectPostType;

$aflx_user_id  = get_current_user_id();
$aflx_areas    = is_array( $areas ) ? $areas : [];
$aflx_area_ids = wp_list_pluck( $member['areas'], 'term_id' );
$aflx_steps    = MemberProfile::next_steps( $aflx_user_id );
$aflx_percent  = (int) round( 100 * $aflx_steps['done'] / max( 1, $aflx_steps['total'] ) );
?>
<div class="aflx-page aflx-page--narrow">
	<?php MemberProfile::render_notice(); ?>

	<header class="aflx-page-header">
		<div class="aflx-page-header__text">
			<p class="aflx-eyebrow"><?php esc_html_e( 'Your portfolio', 'aflanex-community' ); ?></p>
			<h1 class="aflx-h1"><?php esc_html_e( 'Tell people what you’re about', 'aflanex-community' ); ?></h1>
			<p class="aflx-lead"><?php esc_html_e( 'A few details help the right people find you, and help you find them.', 'aflanex-community' ); ?></p>
		</div>
		<a class="aflx-btn aflx-btn--ghost" href="<?php echo esc_url( $member['portfolio_url'] ); ?>"><?php esc_html_e( 'View portfolio', 'aflanex-community' ); ?></a>
	</header>

	<?php if ( $aflx_steps['done'] < $aflx_steps['total'] ) : ?>
		<section class="aflx-card" id="aflx-getting-started" aria-labelledby="aflx-gs-title" style="margin-bottom:var(--aflanex-space-8)">
			<div class="aflx-row aflx-row--between">
				<h2 class="aflx-h3" id="aflx-gs-title"><?php esc_html_e( 'Getting started', 'aflanex-community' ); ?></h2>
				<span class="aflx-small aflx-muted">
					<?php
					/* translators: 1: steps done, 2: total steps */
					echo esc_html( sprintf( __( '%1$d of %2$d done', 'aflanex-community' ), $aflx_steps['done'], $aflx_steps['total'] ) );
					?>
				</span>
			</div>
			<div class="aflx-meter" aria-hidden="true"><span style="width: <?php echo esc_attr( max( 4, $aflx_percent ) ); ?>%"></span></div>
			<ul class="aflx-checklist">
				<?php foreach ( $aflx_steps['steps'] as $aflx_step ) : ?>
					<li class="<?php echo $aflx_step['done'] ? 'is-done' : ''; ?>">
						<span class="aflx-checklist__mark" aria-hidden="true">✓</span>
						<?php if ( $aflx_step['done'] ) : ?>
							<span class="aflx-checklist__label"><?php echo esc_html( $aflx_step['label'] ); ?><span class="aflx-sr-only"> <?php esc_html_e( '(done)', 'aflanex-community' ); ?></span></span>
						<?php else : ?>
							<a class="aflx-checklist__label" href="<?php echo esc_url( $aflx_step['url'] ); ?>"><?php echo esc_html( $aflx_step['label'] ); ?></a>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

	<form class="aflx-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="aflx_save_profile">
		<?php wp_nonce_field( 'aflx_save_profile_' . $aflx_user_id, '_aflx_nonce' ); ?>

		<p class="aflx-hint"><?php esc_html_e( 'Your name, photo and bio are managed on your community profile.', 'aflanex-community' ); ?> <a href="<?php echo esc_url( $member['profile_url'] ); ?>"><?php esc_html_e( 'Edit those there', 'aflanex-community' ); ?></a>.</p>

		<div class="aflx-field">
			<label class="aflx-label" for="aflx-headline"><?php esc_html_e( 'Headline', 'aflanex-community' ); ?></label>
			<input class="aflx-input" id="aflx-headline" name="aflx_headline" type="text" maxlength="120" value="<?php echo esc_attr( $member['headline'] ); ?>" aria-describedby="aflx-headline-hint" placeholder="<?php esc_attr_e( 'e.g. Virtual assistant learning automation', 'aflanex-community' ); ?>">
			<p class="aflx-hint" id="aflx-headline-hint"><?php esc_html_e( 'What you do or what you’re working towards.', 'aflanex-community' ); ?></p>
		</div>

		<div class="aflx-field">
			<label class="aflx-label" for="aflx-location"><?php esc_html_e( 'Location', 'aflanex-community' ); ?> <span class="aflx-optional"><?php esc_html_e( '(optional, city or country is enough)', 'aflanex-community' ); ?></span></label>
			<input class="aflx-input" id="aflx-location" name="aflx_location" type="text" maxlength="80" value="<?php echo esc_attr( $member['location'] ); ?>">
		</div>

		<div class="aflx-field">
			<label class="aflx-label" for="aflx-skills-profile"><?php esc_html_e( 'Skills', 'aflanex-community' ); ?></label>
			<input class="aflx-input" id="aflx-skills-profile" name="aflx_skills" type="text" value="<?php echo esc_attr( implode( ', ', wp_list_pluck( $member['skills'], 'name' ) ) ); ?>" aria-describedby="aflx-skills-profile-hint" placeholder="<?php esc_attr_e( 'e.g. Customer support, Canva, Research', 'aflanex-community' ); ?>">
			<p class="aflx-hint" id="aflx-skills-profile-hint"><?php esc_html_e( 'Separate with commas. Include skills you’re still building.', 'aflanex-community' ); ?></p>
		</div>

		<div class="aflx-field">
			<label class="aflx-label" for="aflx-learning"><?php esc_html_e( 'What are you learning right now?', 'aflanex-community' ); ?></label>
			<input class="aflx-input" id="aflx-learning" name="aflx_learning_now" type="text" maxlength="240" value="<?php echo esc_attr( $member['learning_now'] ); ?>">
		</div>

		<div class="aflx-field">
			<label class="aflx-label" for="aflx-help"><?php esc_html_e( 'What’s one thing you can help others with?', 'aflanex-community' ); ?></label>
			<input class="aflx-input" id="aflx-help" name="aflx_can_help_with" type="text" maxlength="240" value="<?php echo esc_attr( $member['can_help_with'] ); ?>">
		</div>

		<?php if ( $aflx_areas ) : ?>
			<fieldset class="aflx-fieldset">
				<legend class="aflx-label"><?php esc_html_e( 'Interested in', 'aflanex-community' ); ?></legend>
				<div class="aflx-choice-grid">
					<?php foreach ( $aflx_areas as $aflx_term ) : ?>
						<label class="aflx-choice">
							<input type="checkbox" name="aflx_area[]" value="<?php echo esc_attr( $aflx_term->term_id ); ?>" <?php checked( in_array( $aflx_term->term_id, $aflx_area_ids, true ) ); ?>>
							<span class="aflx-choice__title"><?php echo esc_html( $aflx_term->name ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
			</fieldset>
		<?php endif; ?>

		<fieldset class="aflx-fieldset">
			<legend class="aflx-label"><?php esc_html_e( 'Open to', 'aflanex-community' ); ?></legend>
			<div class="aflx-choice-grid">
				<?php foreach ( MemberProfile::open_to_options() as $aflx_key => $aflx_label ) : ?>
					<label class="aflx-choice">
						<input type="checkbox" name="aflx_open_to[]" value="<?php echo esc_attr( $aflx_key ); ?>" <?php checked( in_array( $aflx_key, $member['open_to_keys'], true ) ); ?>>
						<span class="aflx-choice__title"><?php echo esc_html( $aflx_label ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
		</fieldset>

		<fieldset class="aflx-form-section aflx-fieldset">
			<legend class="aflx-label"><?php esc_html_e( 'Links', 'aflanex-community' ); ?> <span class="aflx-optional"><?php esc_html_e( '(optional)', 'aflanex-community' ); ?></span></legend>
			<?php foreach ( MemberProfile::link_fields() as $aflx_key => $aflx_label ) : ?>
				<div class="aflx-field">
					<label class="aflx-label" for="aflx-link-<?php echo esc_attr( $aflx_key ); ?>" style="font-weight:500"><?php echo esc_html( $aflx_label ); ?></label>
					<input class="aflx-input" id="aflx-link-<?php echo esc_attr( $aflx_key ); ?>" name="aflx_links[<?php echo esc_attr( $aflx_key ); ?>]" type="url" inputmode="url" placeholder="https://" value="<?php echo esc_attr( $member['links'][ $aflx_key ] ?? '' ); ?>">
				</div>
			<?php endforeach; ?>
		</fieldset>

		<fieldset class="aflx-form-section aflx-fieldset">
			<legend class="aflx-label"><?php esc_html_e( 'Visibility', 'aflanex-community' ); ?></legend>
			<label class="aflx-choice">
				<input type="checkbox" name="aflx_directory_visible" value="1" <?php checked( $member['visible'] ); ?>>
				<span><span class="aflx-choice__title"><?php esc_html_e( 'Show me in the People directory', 'aflanex-community' ); ?></span><span class="aflx-choice__desc"><?php esc_html_e( 'Only signed-in members can see the directory. Your email is never shown.', 'aflanex-community' ); ?></span></span>
			</label>
		</fieldset>

		<div class="aflx-form-actions">
			<button class="aflx-btn aflx-btn--primary aflx-btn--lg" type="submit"><?php esc_html_e( 'Save portfolio', 'aflanex-community' ); ?></button>
		</div>
	</form>
</div>
