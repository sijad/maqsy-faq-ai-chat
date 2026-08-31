<?php
/**
 * Registry of available AI providers.
 *
 * @since      1.0.0
 *
 * @package    Maqsy
 * @subpackage Maqsy/includes/ai
 */

/**
 * Collects the built-in AI provider plus any registered by other plugins.
 *
 * @since      1.0.0
 * @package    Maqsy
 * @subpackage Maqsy/includes/ai
 */
class Maqsy_AI_Registry {

	/**
	 * Get all registered AI providers, keyed by provider ID.
	 *
	 * Other plugins can register additional providers without modifying
	 * Maqsy itself:
	 *
	 *     add_filter( 'maqsy_ai_providers', function( $providers ) {
	 *         $providers[] = new My_Custom_Provider();
	 *         return $providers;
	 *     } );
	 *
	 * @since  1.0.0
	 * @return Maqsy_AI_Provider[] Keyed by provider ID.
	 */
	public static function get_providers() {

		$providers = apply_filters( 'maqsy_ai_providers', array( new Maqsy_AI_Provider_OpenAI_Compatible() ) );

		$keyed = array();
		foreach ( $providers as $provider ) {
			if ( $provider instanceof Maqsy_AI_Provider ) {
				$keyed[ $provider->get_id() ] = $provider;
			}
		}

		return $keyed;
	}

	/**
	 * Get a single provider by ID.
	 *
	 * @since  1.0.0
	 * @param  string $id Provider ID.
	 * @return Maqsy_AI_Provider|null
	 */
	public static function get_provider( $id ) {
		$providers = self::get_providers();
		return isset( $providers[ $id ] ) ? $providers[ $id ] : null;
	}
}
