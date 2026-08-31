<?php
/**
 * The contract every AI chat provider must implement.
 *
 * @since      1.0.0
 *
 * @package    Maqsy
 * @subpackage Maqsy/includes/ai
 */

/**
 * AI provider contract.
 *
 * Other plugins can implement this interface and register an instance via
 * the `maqsy_ai_providers` filter to add support for additional
 * services (Anthropic, Azure OpenAI, a local LLM, etc.) without touching
 * Maqsy's own code.
 *
 * @since      1.0.0
 * @package    Maqsy
 * @subpackage Maqsy/includes/ai
 */
interface Maqsy_AI_Provider {

	/**
	 * A unique, stable identifier for this provider, used as the stored
	 * settings value and REST/registry lookup key.
	 *
	 * @since  1.0.0
	 * @return string
	 */
	public function get_id();

	/**
	 * A human-readable label shown in the settings dropdown.
	 *
	 * @since  1.0.0
	 * @return string
	 */
	public function get_label();

	/**
	 * Send a chat completion request.
	 *
	 * @since  1.0.0
	 * @param  array $messages Array of { role: 'system'|'user'|'assistant', content: string }.
	 * @param  array $settings The plugin's AI settings (api_key, model, etc.).
	 * @return string|WP_Error The assistant's reply text, or a WP_Error on failure.
	 */
	public function send_message( array $messages, array $settings );
}
