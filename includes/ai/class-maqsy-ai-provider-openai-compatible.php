<?php
/**
 * An AI provider for any OpenAI-compatible /chat/completions API.
 *
 * @since      1.0.0
 *
 * @package    Maqsy
 * @subpackage Maqsy/includes/ai
 */

/**
 * Covers both OpenAI and OpenRouter (and anything else exposing the same
 * API shape) with a single implementation, since only the base URL, API
 * key, and model differ between them.
 *
 * @since      1.0.0
 * @package    Maqsy
 * @subpackage Maqsy/includes/ai
 */
class Maqsy_AI_Provider_OpenAI_Compatible implements Maqsy_AI_Provider, Maqsy_AI_Provider_Supports_Models {

	/**
	 * {@inheritDoc}
	 */
	public function get_id() {
		return 'openai_compatible';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label() {
		return __( 'OpenAI / OpenRouter (or compatible)', 'maqsy-faq-ai-chat' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array $messages Chat messages to send.
	 * @param array $settings The plugin's AI settings.
	 */
	public function send_message( array $messages, array $settings ) {

		$api_key  = isset( $settings['api_key'] ) ? $settings['api_key'] : '';
		$model    = ! empty( $settings['model'] ) ? $settings['model'] : 'gpt-4o-mini';
		$base_url = ! empty( $settings['base_url'] ) ? untrailingslashit( $settings['base_url'] ) : 'https://api.openai.com/v1';

		if ( ! $api_key ) {
			return new WP_Error( 'maqsy_ai_missing_key', __( 'The AI chat provider is not configured with an API key.', 'maqsy-faq-ai-chat' ) );
		}

		$response = wp_remote_post(
			$base_url . '/chat/completions',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'    => $model,
						'messages' => $messages,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			$message = isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'The AI provider returned an error.', 'maqsy-faq-ai-chat' );
			return new WP_Error( 'maqsy_ai_request_failed', $message );
		}

		$reply = isset( $body['choices'][0]['message']['content'] ) ? $body['choices'][0]['message']['content'] : '';

		if ( ! $reply ) {
			return new WP_Error( 'maqsy_ai_empty_reply', __( 'The AI provider returned an empty reply.', 'maqsy-faq-ai-chat' ) );
		}

		return trim( $reply );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array $settings Candidate settings from the settings page form.
	 */
	public function get_models( array $settings ) {

		$api_key  = isset( $settings['api_key'] ) ? $settings['api_key'] : '';
		$base_url = ! empty( $settings['base_url'] ) ? untrailingslashit( $settings['base_url'] ) : 'https://api.openai.com/v1';

		if ( ! $api_key ) {
			return new WP_Error( 'maqsy_ai_missing_key', __( 'Enter an API key first.', 'maqsy-faq-ai-chat' ) );
		}

		$response = wp_remote_get(
			$base_url . '/models',
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			$message = isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'The AI provider returned an error.', 'maqsy-faq-ai-chat' );
			return new WP_Error( 'maqsy_ai_models_request_failed', $message );
		}

		$models = array();
		foreach ( (array) ( isset( $body['data'] ) ? $body['data'] : array() ) as $model ) {
			if ( ! empty( $model['id'] ) && self::is_chat_model( $model['id'] ) ) {
				$models[] = $model['id'];
			}
		}

		if ( ! $models ) {
			return new WP_Error( 'maqsy_ai_no_models', __( 'The AI provider did not return any chat models.', 'maqsy-faq-ai-chat' ) );
		}

		sort( $models );

		return $models;
	}

	/**
	 * Heuristically filter out non-chat models (embeddings, transcription,
	 * text-to-speech, image generation, moderation) that some providers'
	 * `/models` endpoints list alongside their chat-capable LLMs.
	 *
	 * OpenAI's endpoint returns every model type it offers; OpenRouter's
	 * only ever lists chat models, so this is a no-op for it.
	 *
	 * @since  1.0.0
	 * @param  string $model_id The model ID to check.
	 * @return bool
	 */
	private static function is_chat_model( $model_id ) {

		$non_chat_patterns = array(
			'embedding',
			'whisper',
			'tts',
			'dall-e',
			'moderation',
			'davinci-002',
			'babbage-002',
			'image',
			'computer-use',
		);

		foreach ( $non_chat_patterns as $pattern ) {
			if ( false !== strpos( $model_id, $pattern ) ) {
				return false;
			}
		}

		return true;
	}
}
