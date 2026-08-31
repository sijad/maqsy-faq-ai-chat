<?php
/**
 * Optional capability interface for AI providers that can list their available models.
 *
 * @since      1.0.0
 *
 * @package    Maqsy
 * @subpackage Maqsy/includes/ai
 */

/**
 * Implemented by providers that can fetch their available model list from an API,
 * so the settings page can offer a "Fetch models" action instead of requiring the
 * model ID to be typed by hand. Kept as a separate, optional interface (rather
 * than adding a required method to Maqsy_AI_Provider) so third-party providers
 * that have no discoverable model list aren't forced to implement it.
 *
 * @since      1.0.0
 * @package    Maqsy
 * @subpackage Maqsy/includes/ai
 */
interface Maqsy_AI_Provider_Supports_Models {

	/**
	 * List the models available for the given (possibly unsaved) settings.
	 *
	 * @since  1.0.0
	 * @param  array $settings Candidate settings from the settings page form (api_key, base_url, etc.),
	 *                         which may not match what's currently saved in the database.
	 * @return string[]|WP_Error Sorted list of model IDs, or a WP_Error on failure.
	 */
	public function get_models( array $settings );
}
