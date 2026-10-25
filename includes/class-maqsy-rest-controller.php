<?php
/**
 * REST API endpoints for the plugin.
 *
 * @since      1.0.0
 *
 * @package    Maqsy
 * @subpackage Maqsy/includes
 */

/**
 * Registers the plugin's REST API routes.
 *
 * @since      1.0.0
 * @package    Maqsy
 * @subpackage Maqsy/includes
 */
class Maqsy_Rest_Controller {

	/**
	 * The REST route namespace.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const NAMESPACE_V1 = 'maqsy/v1';

	/**
	 * Register all routes.
	 *
	 * @since    1.0.0
	 */
	public function register_routes() {

		register_rest_route(
			self::NAMESPACE_V1,
			'/faqs',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_faqs' ),
				'permission_callback' => array( $this, 'editor_permissions_check' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/models',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'post_models' ),
				'permission_callback' => array( $this, 'admin_permissions_check' ),
				'args'                => array(
					'provider' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'api_key'  => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'base_url' => array(
						'type'              => 'string',
						'sanitize_callback' => 'esc_url_raw',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/chat',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'post_chat' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'history' => array(
						'type'     => 'array',
						'default'  => array(),
						'maxItems' => 20,
						'items'    => array(
							'type'       => 'object',
							'required'   => array( 'role', 'content' ),
							'properties' => array(
								'role'    => array(
									'type' => 'string',
									'enum' => array( 'user', 'assistant' ),
								),
								'content' => array(
									'type'      => 'string',
									'maxLength' => 16000,
								),
							),
						),
					),
					'message' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
				),
			)
		);
	}

	/**
	 * Permission check limiting a route to users who can edit posts.
	 *
	 * Used by the FAQ picker route, which is only ever called from the
	 * block editor and must not leak FAQ titles to anonymous visitors.
	 * The callback itself additionally filters each FAQ through a per-post
	 * 'read_post' capability check so unpublished FAQs are never exposed to
	 * users who could not otherwise see them.
	 *
	 * @since    1.0.0
	 * @return   bool
	 */
	public function editor_permissions_check() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Permission check limiting a route to users who can manage options.
	 *
	 * Used by the "fetch models" route, which is only ever called from the
	 * AI chat settings page and accepts an API key in its request body.
	 *
	 * @since    1.0.0
	 * @return   bool
	 */
	public function admin_permissions_check() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * List FAQ posts for the block editor's FAQ picker.
	 *
	 * @since    1.0.0
	 * @return   WP_REST_Response
	 */
	public function get_faqs() {

		$faqs = get_posts(
			array(
				'post_type'   => 'maqsy',
				'post_status' => array( 'publish' ),
				'numberposts' => -1,
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);

		$data = array();
		foreach ( $faqs as $faq ) {

			$data[] = array(
				'id'     => $faq->ID,
				'title'  => $faq->post_title ? $faq->post_title : __( '(no title)', 'maqsy-faq-ai-chat' ),
				'status' => $faq->post_status,
			);
		}

		return rest_ensure_response( $data );
	}

	/**
	 * List a provider's available models for the settings page's model field.
	 *
	 * Uses whatever provider/api_key/base_url are currently in the settings
	 * form (which may not be saved yet), not necessarily the saved settings.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request $request The incoming request.
	 * @return   WP_REST_Response|WP_Error
	 */
	public function post_models( WP_REST_Request $request ) {

		$provider = Maqsy_AI_Registry::get_provider( (string) $request->get_param( 'provider' ) );

		if ( ! $provider instanceof Maqsy_AI_Provider_Supports_Models ) {
			return new WP_Error( 'maqsy_ai_models_unsupported', __( 'This provider does not support fetching models.', 'maqsy-faq-ai-chat' ), array( 'status' => 400 ) );
		}

		$models = $provider->get_models(
			array(
				'api_key'  => (string) $request->get_param( 'api_key' ),
				'base_url' => (string) $request->get_param( 'base_url' ),
			)
		);

		if ( is_wp_error( $models ) ) {
			$models->add_data( array( 'status' => 502 ) );
			return $models;
		}

		return rest_ensure_response( array( 'models' => $models ) );
	}

	/**
	 * Answer a visitor's chat message, grounded in the site's FAQs.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request $request The incoming request.
	 * @return   WP_REST_Response|WP_Error
	 */
	public function post_chat( WP_REST_Request $request ) {

		$message = trim( (string) $request->get_param( 'message' ) );

		if ( '' === $message ) {
			return new WP_Error( 'maqsy_ai_empty_message', __( 'Please enter a message.', 'maqsy-faq-ai-chat' ), array( 'status' => 400 ) );
		}

		if ( strlen( $message ) > 1000 ) {
			return new WP_Error( 'maqsy_ai_message_too_long', __( 'Your message is too long.', 'maqsy-faq-ai-chat' ), array( 'status' => 400 ) );
		}

		$rate_limit_error = $this->check_rate_limit();
		if ( is_wp_error( $rate_limit_error ) ) {
			return $rate_limit_error;
		}

		$reply = Maqsy_AI_Chat::ask( $message, (array) $request->get_param( 'history' ) );

		if ( is_wp_error( $reply ) ) {
			$reply->add_data( array( 'status' => 502 ) );
			return $reply;
		}

		return rest_ensure_response( array( 'reply' => $reply ) );
	}

	/**
	 * A simple per-IP rate limit so the public chat endpoint can't be used
	 * to run up the site owner's AI provider bill.
	 *
	 * @since    1.0.0
	 * @return   true|WP_Error
	 */
	private function check_rate_limit() {

		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$key   = 'maqsy_chat_rate_' . md5( $ip );
		$count = (int) get_transient( $key );

		if ( $count >= 10 ) {
			return new WP_Error( 'maqsy_ai_rate_limited', __( 'You are sending messages too quickly. Please wait a moment and try again.', 'maqsy-faq-ai-chat' ), array( 'status' => 429 ) );
		}

		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );

		return true;
	}
}
