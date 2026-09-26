<?php

namespace Aflanex\Community\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Contract for a future learning-data integration (e.g. an Erudify API
 * client). Erudify remains the source of truth for learning records; the
 * Community only *displays* a summary it receives through a controlled API.
 *
 * No implementation ships with this plugin. Nothing is mocked.
 */
interface LearningSummaryProvider {

	/**
	 * Machine name of the source, e.g. 'erudify'.
	 */
	public function source(): string;

	/**
	 * True only when a real, configured connection exists.
	 */
	public function is_connected(): bool;

	/**
	 * A display-ready summary for one member, or null when there is none.
	 *
	 * Expected shape:
	 * [
	 *   'source'          => 'erudify',
	 *   'programmes'      => [ [ 'external_id' => '', 'title' => '', 'status' => '' ] ],
	 *   'milestones'      => [ [ 'title' => '', 'achieved_at' => 'ISO-8601' ] ],
	 *   'skills'          => [ 'Skill name', ... ],
	 *   'last_synced_at'  => 'ISO-8601',
	 *   'url'             => 'Link to the learner record in Erudify',
	 * ]
	 */
	public function summary( int $user_id ): ?array;
}
