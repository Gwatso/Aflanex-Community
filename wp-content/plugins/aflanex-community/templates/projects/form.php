<?php
/**
 * Start / edit a project.
 *
 * @var array|null       $project  Projects::detail() when editing
 * @var \WP_Term[]|mixed $areas
 * @var array            $statuses
 */
defined( 'ABSPATH' ) || exit;

use Aflanex\Community\Pages\Pages;
use Aflanex\Community\Profiles\MemberProfile;
use Aflanex\Community\Projects\ProjectForms;

$aflx_old    = ProjectForms::old_input();
$aflx_errors = $aflx_old['errors'];
$aflx_in     = $aflx_old['input'];
$aflx_edit   = (bool) $project;
$aflx_id     = $aflx_edit ? (int) $project['id'] : 0;
$aflx_areas  = is_array( $areas ) ? $areas : [];
// Only the owner (or a moderator) manages the collaborator list.
$aflx_can_manage = ! $aflx_edit || (int) ( $project['owner']['id'] ?? 0 ) === get_current_user_id() || current_user_can( 'edit_others_posts' );

$aflx_val = static function ( string $key, $fallback = '' ) use ( $aflx_in ) {
	return array_key_exists( $key, $aflx_in ) ? $aflx_in[ $key ] : $fallback;
};

$aflx_values = [
	'title'       => $aflx_val( 'title', $project['title'] ?? '' ),
	'summary'     => $aflx_val( 'summary', $project['summary'] ?? '' ),
	'description' => $aflx_val( 'description', $aflx_edit ? get_post_field( 'post_content', $aflx_id, 'raw' ) : '' ),
	'problem'     => $aflx_val( 'problem', $project['problem'] ?? '' ),
	'approach'    => $aflx_val( 'approach', $project['approach'] ?? '' ),
	'looking_for' => $aflx_val( 'looking_for', $project['looking_for'] ?? '' ),
	'status'      => $aflx_val( 'status', $project['status'] ?? 'idea' ),
	'area'        => array_map( 'intval', (array) $aflx_val( 'area', $project['area_ids'] ?? [] ) ),
	'skills'      => $aflx_val( 'skills', implode( ', ', $project['skill_names'] ?? [] ) ),
	'collabs'     => $aflx_val( 'collabs', implode( ', ', wp_list_pluck( $project['collaborators'] ?? [], 'username' ) ) ),
	'feedback'    => (bool) $aflx_val( 'feedback', $project['feedback_requested'] ?? false ),
	'links'       => (array) $aflx_val( 'links', $project['links'] ?? [] ),
];

$aflx_status_help = [
	'idea'                  => __( 'Still shaping it. Thoughts welcome.', 'aflanex-community' ),
	'in_progress'           => __( 'Actively working on it.', 'aflanex-community' ),
	'seeking_collaborators' => __( 'You want people to join.', 'aflanex-community' ),
	'ready_for_feedback'    => __( 'You want honest input.', 'aflanex-community' ),
	'completed'             => __( 'Shipped or finished.', 'aflanex-community' ),
];

$aflx_err = static function ( string $key ) use ( $aflx_errors ): void {
	if ( ! empty( $aflx_errors[ $key ] ) ) {
		printf( '<p class="aflx-field-error" id="aflx-err-%1$s">%2$s</p>', esc_attr( $key ), esc_html( $aflx_errors[ $key ] ) );
	}
};
$aflx_invalid = static function ( string $key ) use ( $aflx_errors ): string {
	return ! empty( $aflx_errors[ $key ] ) ? ' aria-invalid="true" aria-describedby="aflx-err-' . esc_attr( $key ) . '"' : '';
};
?>
<div class="aflx-page aflx-page--narrow">
	<?php
	// Field-level errors below are more useful than the generic banner.
	if ( ! $aflx_errors ) {
		MemberProfile::render_notice();
	}
	?>

	<p style="margin:0 0 var(--aflanex-space-4)"><a class="aflx-small" href="<?php echo esc_url( $aflx_edit ? $project['url'] : Pages::projects_url() ); ?>">← <?php echo esc_html( $aflx_edit ? __( 'Back to project', 'aflanex-community' ) : __( 'All projects', 'aflanex-community' ) ); ?></a></p>

	<header class="aflx-page-header">
		<div class="aflx-page-header__text">
			<p class="aflx-eyebrow"><?php esc_html_e( 'Build', 'aflanex-community' ); ?></p>
			<h1 class="aflx-h1"><?php echo esc_html( $aflx_edit ? __( 'Edit project', 'aflanex-community' ) : __( 'Start a project', 'aflanex-community' ) ); ?></h1>
			<?php if ( ! $aflx_edit ) : ?>
				<p class="aflx-lead"><?php esc_html_e( 'It doesn’t need to be finished. Share the idea, what you’re trying to solve and where you could use help.', 'aflanex-community' ); ?></p>
			<?php endif; ?>
		</div>
	</header>

	<?php if ( $aflx_errors ) : ?>
		<div class="aflx-alert aflx-alert--danger" role="alert" style="margin-bottom:var(--aflanex-space-5)">
			<span class="aflx-alert__icon" aria-hidden="true">!</span>
			<span><strong><?php esc_html_e( 'A few things need attention:', 'aflanex-community' ); ?></strong>
				<?php echo esc_html( implode( ' ', array_values( $aflx_errors ) ) ); ?></span>
		</div>
	<?php endif; ?>

	<form class="aflx-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" novalidate>
		<input type="hidden" name="action" value="aflx_save_project">
		<input type="hidden" name="project_id" value="<?php echo esc_attr( $aflx_id ); ?>">
		<?php wp_nonce_field( 'aflx_save_project_' . $aflx_id, '_aflx_nonce' ); ?>

		<div class="aflx-field">
			<label class="aflx-label" for="aflx-title"><?php esc_html_e( 'Project name', 'aflanex-community' ); ?></label>
			<input class="aflx-input" id="aflx-title" name="title" type="text" maxlength="120" required value="<?php echo esc_attr( $aflx_values['title'] ); ?>"<?php echo $aflx_invalid( 'title' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<?php $aflx_err( 'title' ); ?>
		</div>

		<div class="aflx-field">
			<label class="aflx-label" for="aflx-summary"><?php esc_html_e( 'One-line summary', 'aflanex-community' ); ?></label>
			<input class="aflx-input" id="aflx-summary" name="summary" type="text" maxlength="160" required value="<?php echo esc_attr( $aflx_values['summary'] ); ?>" aria-describedby="aflx-summary-hint"<?php echo $aflx_invalid( 'summary' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<p class="aflx-hint" id="aflx-summary-hint"><?php esc_html_e( 'What it is and who it’s for, in one sentence.', 'aflanex-community' ); ?></p>
			<?php $aflx_err( 'summary' ); ?>
		</div>

		<fieldset class="aflx-fieldset">
			<legend class="aflx-label"><?php esc_html_e( 'Where is it at?', 'aflanex-community' ); ?></legend>
			<div class="aflx-choice-grid">
				<?php foreach ( $statuses as $aflx_key => $aflx_label ) : ?>
					<label class="aflx-choice">
						<input type="radio" name="status" value="<?php echo esc_attr( $aflx_key ); ?>" <?php checked( $aflx_values['status'], $aflx_key ); ?>>
						<span><span class="aflx-choice__title"><?php echo esc_html( $aflx_label ); ?></span><span class="aflx-choice__desc"><?php echo esc_html( $aflx_status_help[ $aflx_key ] ?? '' ); ?></span></span>
					</label>
				<?php endforeach; ?>
			</div>
			<?php $aflx_err( 'status' ); ?>
		</fieldset>

		<div class="aflx-field">
			<label class="aflx-label" for="aflx-looking"><?php esc_html_e( 'What help are you looking for?', 'aflanex-community' ); ?> <span class="aflx-optional"><?php esc_html_e( '(optional)', 'aflanex-community' ); ?></span></label>
			<input class="aflx-input" id="aflx-looking" name="looking_for" type="text" maxlength="200" value="<?php echo esc_attr( $aflx_values['looking_for'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. A designer for the landing page, someone to test the prototype', 'aflanex-community' ); ?>">
		</div>

		<div class="aflx-form-section aflx-field">
			<label class="aflx-label" for="aflx-problem"><?php esc_html_e( 'What problem are you trying to solve?', 'aflanex-community' ); ?> <span class="aflx-optional"><?php esc_html_e( '(optional)', 'aflanex-community' ); ?></span></label>
			<textarea class="aflx-textarea" id="aflx-problem" name="problem" rows="4" maxlength="1500"<?php echo $aflx_invalid( 'problem' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_textarea( $aflx_values['problem'] ); ?></textarea>
			<?php $aflx_err( 'problem' ); ?>
		</div>

		<div class="aflx-field">
			<label class="aflx-label" for="aflx-approach"><?php esc_html_e( 'How are you approaching it?', 'aflanex-community' ); ?> <span class="aflx-optional"><?php esc_html_e( '(optional)', 'aflanex-community' ); ?></span></label>
			<textarea class="aflx-textarea" id="aflx-approach" name="approach" rows="4" maxlength="1500"<?php echo $aflx_invalid( 'approach' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_textarea( $aflx_values['approach'] ); ?></textarea>
			<?php $aflx_err( 'approach' ); ?>
		</div>

		<div class="aflx-field">
			<label class="aflx-label" for="aflx-description"><?php esc_html_e( 'Anything else people should know?', 'aflanex-community' ); ?> <span class="aflx-optional"><?php esc_html_e( '(optional)', 'aflanex-community' ); ?></span></label>
			<textarea class="aflx-textarea" id="aflx-description" name="description" rows="5" maxlength="5000"<?php echo $aflx_invalid( 'description' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_textarea( $aflx_values['description'] ); ?></textarea>
			<?php $aflx_err( 'description' ); ?>
		</div>

		<?php if ( $aflx_areas ) : ?>
			<fieldset class="aflx-form-section aflx-fieldset">
				<legend class="aflx-label"><?php esc_html_e( 'Focus area', 'aflanex-community' ); ?> <span class="aflx-optional"><?php esc_html_e( '(pick any that fit)', 'aflanex-community' ); ?></span></legend>
				<div class="aflx-choice-grid">
					<?php foreach ( $aflx_areas as $aflx_term ) : ?>
						<label class="aflx-choice">
							<input type="checkbox" name="area[]" value="<?php echo esc_attr( $aflx_term->term_id ); ?>" <?php checked( in_array( (int) $aflx_term->term_id, $aflx_values['area'], true ) ); ?>>
							<span class="aflx-choice__title"><?php echo esc_html( $aflx_term->name ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
			</fieldset>
		<?php endif; ?>

		<div class="aflx-field">
			<label class="aflx-label" for="aflx-skills"><?php esc_html_e( 'Skills used', 'aflanex-community' ); ?> <span class="aflx-optional"><?php esc_html_e( '(optional)', 'aflanex-community' ); ?></span></label>
			<input class="aflx-input" id="aflx-skills" name="skills" type="text" value="<?php echo esc_attr( $aflx_values['skills'] ); ?>" aria-describedby="aflx-skills-hint" placeholder="<?php esc_attr_e( 'e.g. Research, Figma, Prompt design', 'aflanex-community' ); ?>">
			<p class="aflx-hint" id="aflx-skills-hint"><?php esc_html_e( 'Separate with commas. Up to 15.', 'aflanex-community' ); ?></p>
		</div>

		<div class="aflx-form-section aflx-field">
			<label class="aflx-label" for="aflx-collabs"><?php esc_html_e( 'Collaborators', 'aflanex-community' ); ?> <span class="aflx-optional"><?php esc_html_e( '(optional)', 'aflanex-community' ); ?></span></label>
			<input class="aflx-input" id="aflx-collabs" name="collaborators" type="text" value="<?php echo esc_attr( $aflx_values['collabs'] ); ?>" aria-describedby="aflx-collabs-hint"<?php echo $aflx_invalid( 'collaborators' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php disabled( ! $aflx_can_manage ); ?>>
			<p class="aflx-hint" id="aflx-collabs-hint"><?php echo esc_html( $aflx_can_manage ? __( 'Community usernames, separated by commas. Collaborators can edit the project and post updates.', 'aflanex-community' ) : __( 'Only the project owner can change collaborators.', 'aflanex-community' ) ); ?></p>
			<?php $aflx_err( 'collaborators' ); ?>
		</div>

		<fieldset class="aflx-fieldset">
			<legend class="aflx-label"><?php esc_html_e( 'Links', 'aflanex-community' ); ?> <span class="aflx-optional"><?php esc_html_e( '(demo, repo, prototype… optional)', 'aflanex-community' ); ?></span></legend>
			<?php for ( $aflx_i = 0; $aflx_i < 3; $aflx_i++ ) : ?>
				<?php $aflx_link = $aflx_values['links'][ $aflx_i ] ?? [ 'label' => '', 'url' => '' ]; ?>
				<div class="aflx-link-row">
					<label class="aflx-sr-only" for="aflx-link-label-<?php echo esc_attr( $aflx_i ); ?>"><?php esc_html_e( 'Link label', 'aflanex-community' ); ?></label>
					<input class="aflx-input" id="aflx-link-label-<?php echo esc_attr( $aflx_i ); ?>" name="links[<?php echo esc_attr( $aflx_i ); ?>][label]" type="text" maxlength="60" placeholder="<?php esc_attr_e( 'Label', 'aflanex-community' ); ?>" value="<?php echo esc_attr( $aflx_link['label'] ?? '' ); ?>">
					<label class="aflx-sr-only" for="aflx-link-url-<?php echo esc_attr( $aflx_i ); ?>"><?php esc_html_e( 'Link URL', 'aflanex-community' ); ?></label>
					<input class="aflx-input" id="aflx-link-url-<?php echo esc_attr( $aflx_i ); ?>" name="links[<?php echo esc_attr( $aflx_i ); ?>][url]" type="url" inputmode="url" placeholder="https://" value="<?php echo esc_attr( $aflx_link['url'] ?? '' ); ?>">
				</div>
			<?php endfor; ?>
		</fieldset>

		<div class="aflx-field">
			<label class="aflx-label" for="aflx-cover"><?php esc_html_e( 'Cover image', 'aflanex-community' ); ?> <span class="aflx-optional"><?php esc_html_e( '(optional)', 'aflanex-community' ); ?></span></label>
			<input class="aflx-input" id="aflx-cover" name="cover" type="file" accept="image/jpeg,image/png,image/webp" aria-describedby="aflx-cover-hint">
			<p class="aflx-hint" id="aflx-cover-hint"><?php esc_html_e( 'A screenshot, sketch or photo of the work. JPG, PNG or WebP, up to 5 MB.', 'aflanex-community' ); ?></p>
		</div>

		<label class="aflx-choice">
			<input type="checkbox" name="feedback_requested" value="1" <?php checked( $aflx_values['feedback'] ); ?>>
			<span><span class="aflx-choice__title"><?php esc_html_e( 'I’d like feedback on this', 'aflanex-community' ); ?></span><span class="aflx-choice__desc"><?php esc_html_e( 'We’ll invite members to share constructive feedback in the discussion.', 'aflanex-community' ); ?></span></span>
		</label>

		<div class="aflx-form-actions">
			<button class="aflx-btn aflx-btn--primary aflx-btn--lg" type="submit"><?php echo esc_html( $aflx_edit ? __( 'Save changes', 'aflanex-community' ) : __( 'Share project', 'aflanex-community' ) ); ?></button>
			<a class="aflx-btn aflx-btn--ghost" href="<?php echo esc_url( $aflx_edit ? $project['url'] : Pages::projects_url() ); ?>"><?php esc_html_e( 'Cancel', 'aflanex-community' ); ?></a>
		</div>
		<?php if ( ! $aflx_edit ) : ?>
			<p class="aflx-hint"><?php esc_html_e( 'Sharing creates a post in the Projects space so members can discuss it with you.', 'aflanex-community' ); ?></p>
		<?php endif; ?>
	</form>
</div>
