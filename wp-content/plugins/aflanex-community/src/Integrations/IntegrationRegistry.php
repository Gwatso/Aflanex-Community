<?php

namespace Aflanex\Community\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Where future integrations plug in. Register a provider with:
 *
 *   add_filter( 'aflanex/integrations/learning_providers', function ( $providers ) {
 *       $providers[] = new My\ErudifyLearningProvider();
 *       return $providers;
 *   } );
 *
 * Templates call learning_summary(); with no connected provider it returns
 * null and the portfolio simply doesn't show a learning section.
 */
final class IntegrationRegistry {

	/**
	 * @return LearningSummaryProvider[]
	 */
	public static function learning_providers(): array {
		$providers = apply_filters( 'aflanex/integrations/learning_providers', [] );
		return array_values(
			array_filter(
				(array) $providers,
				static fn( $p ) => $p instanceof LearningSummaryProvider && $p->is_connected()
			)
		);
	}

	public static function learning_summary( int $user_id ): ?array {
		foreach ( self::learning_providers() as $provider ) {
			$summary = $provider->summary( $user_id );
			if ( $summary ) {
				return $summary;
			}
		}
		return null;
	}
}
