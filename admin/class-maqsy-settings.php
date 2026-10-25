<?php
/**
 * The AI chat settings page.
 *
 * @since      1.0.0
 *
 * @package    Maqsy
 * @subpackage Maqsy/admin
 */

/**
 * Registers and renders the "AI Chat Settings" submenu page.
 *
 * @since      1.0.0
 * @package    Maqsy
 * @subpackage Maqsy/admin
 */
class Maqsy_Settings {

	/**
	 * The option name the settings are stored under.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const OPTION_NAME = 'maqsy_ai_settings';

	/**
	 * The settings page slug.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const PAGE_SLUG = 'maqsy-ai-settings';

	/**
	 * Default values for every setting.
	 *
	 * @since  1.0.0
	 * @return array
	 */
	public static function get_defaults() {
		return array(
			'enabled'             => false,
			'provider'            => 'openai_compatible',
			'api_key'             => '',
			'model'               => 'openrouter/free',
			'base_url'            => 'https://openrouter.ai/api/v1',
			'title'               => __( 'Ask us anything', 'maqsy-faq-ai-chat' ),
			'button_label'        => __( 'Chat with us', 'maqsy-faq-ai-chat' ),
			'welcome_message'     => __( 'Ask a question and I\'ll do my best to answer using our FAQs.', 'maqsy-faq-ai-chat' ),
			'custom_instructions' => '',
			'history_limit'       => 6,
			'color_mode'          => 'auto',
			'primary_color'       => '#2563eb',
		);
	}

	/**
	 * The available chat widget color modes, keyed by their stored value.
	 *
	 * @since  1.0.0
	 * @return array
	 */
	public static function get_color_modes() {
		return array(
			'auto'  => __( 'Automatic (match visitor\'s system setting)', 'maqsy-faq-ai-chat' ),
			'light' => __( 'Light', 'maqsy-faq-ai-chat' ),
			'dark'  => __( 'Dark', 'maqsy-faq-ai-chat' ),
		);
	}

	/**
	 * The built-in API base URL presets, keyed by URL.
	 *
	 * @since  1.0.0
	 * @return array
	 */
	public static function get_base_url_presets() {
		return array(
			'https://api.openai.com/v1'    => __( 'OpenAI', 'maqsy-faq-ai-chat' ),
			'https://openrouter.ai/api/v1' => __( 'OpenRouter', 'maqsy-faq-ai-chat' ),
		);
	}

	/**
	 * Get the current settings, merged with defaults.
	 *
	 * @since  1.0.0
	 * @return array
	 */
	public static function get_settings() {
		$stored = get_option( self::OPTION_NAME, array() );
		return wp_parse_args( $stored, self::get_defaults() );
	}

	/**
	 * The settings page's admin hook suffix, used to scope asset loading.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	private $hook_suffix;

	/**
	 * Add the settings submenu page under the Maqsy CPT menu.
	 *
	 * @since 1.0.0
	 */
	public function add_settings_page() {

		$this->hook_suffix = add_submenu_page(
			'edit.php?post_type=maqsy',
			__( 'AI Chat Settings', 'maqsy-faq-ai-chat' ),
			__( 'AI Chat Settings', 'maqsy-faq-ai-chat' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue the settings page script (base-URL toggle + model autocomplete).
	 *
	 * Reuses WP core's own bundled jQuery UI Autocomplete widget (the same one
	 * behind the tag/user suggest fields), including its wp-admin theming and
	 * accessibility (wp-a11y) wiring, instead of a hand-rolled combobox.
	 *
	 * @since 1.0.0
	 * @param string $hook The current admin page's hook suffix.
	 */
	public function enqueue_settings_assets( $hook ) {

		if ( $hook !== $this->hook_suffix ) {
			return;
		}

		wp_enqueue_script(
			'maqsy-settings',
			plugin_dir_url( __DIR__ ) . 'admin/js/settings.js',
			array( 'jquery', 'jquery-ui-autocomplete', 'wp-a11y' ),
			MAQSY_VERSION,
			true
		);

		wp_localize_script(
			'maqsy-settings',
			'maqsySettings',
			array(
				'optionName'     => self::OPTION_NAME,
				// The provider field is hidden for now (only one provider is
				// supported), so there is no form field to read this from.
				'provider'       => self::get_defaults()['provider'],
				'basePresetUrls' => array_keys( self::get_base_url_presets() ),
				'modelsEndpoint' => esc_url_raw( rest_url( 'maqsy/v1/models' ) ),
				'restNonce'      => wp_create_nonce( 'wp_rest' ),
				'i18n'           => array(
					'fetching'     => __( 'Fetching…', 'maqsy-faq-ai-chat' ),
					'modelsLoaded' => __( 'models loaded.', 'maqsy-faq-ai-chat' ),
					'fetchFailed'  => __( 'Failed to fetch models.', 'maqsy-faq-ai-chat' ),
				),
			)
		);
	}

	/**
	 * Register the setting, section, and fields.
	 *
	 * @since 1.0.0
	 */
	public function register_settings() {

		register_setting(
			'maqsy_ai_settings_group',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => self::get_defaults(),
			)
		);

		add_settings_section( 'maqsy_ai_general', __( 'AI Chat', 'maqsy-faq-ai-chat' ), '__return_false', self::PAGE_SLUG );

		$fields = array(
			'enabled'             => __( 'Enable AI chat', 'maqsy-faq-ai-chat' ),
			// 'provider' is hidden for now — only one provider (OpenRouter/OpenAI-
			// compatible) is currently supported, so a one-option select adds
			// nothing. get_defaults()/sanitize_settings() still handle it normally;
			// re-add the field here once a second provider exists.
			'api_key'             => __( 'API key', 'maqsy-faq-ai-chat' ),
			'base_url'            => __( 'API base URL', 'maqsy-faq-ai-chat' ),
			'model'               => __( 'Model', 'maqsy-faq-ai-chat' ),
			'history_limit'       => __( 'Previous messages to include', 'maqsy-faq-ai-chat' ),
			'title'               => __( 'Chat window title', 'maqsy-faq-ai-chat' ),
			'button_label'        => __( 'Chat button label', 'maqsy-faq-ai-chat' ),
			'welcome_message'     => __( 'Welcome message', 'maqsy-faq-ai-chat' ),
			'custom_instructions' => __( 'Site & branding instructions', 'maqsy-faq-ai-chat' ),
			'color_mode'          => __( 'Color mode', 'maqsy-faq-ai-chat' ),
			'primary_color'       => __( 'Primary color', 'maqsy-faq-ai-chat' ),
			'system_prompt'       => __( 'System prompt (preview)', 'maqsy-faq-ai-chat' ),
		);

		foreach ( $fields as $key => $label ) {
			add_settings_field(
				$key,
				$label,
				array( $this, 'render_field' ),
				self::PAGE_SLUG,
				'maqsy_ai_general',
				array( 'key' => $key )
			);
		}
	}

	/**
	 * Sanitize submitted settings.
	 *
	 * @since  1.0.0
	 * @param  array $input Raw submitted values.
	 * @return array
	 */
	public function sanitize_settings( $input ) {

		$defaults  = self::get_defaults();
		$providers = Maqsy_AI_Registry::get_providers();
		$input     = is_array( $input ) ? $input : array();

		$output                        = array();
		$output['enabled']             = ! empty( $input['enabled'] );
		$output['provider']            = isset( $input['provider'], $providers[ $input['provider'] ] ) ? $input['provider'] : $defaults['provider'];
		$output['api_key']             = isset( $input['api_key'] ) ? sanitize_text_field( $input['api_key'] ) : '';
		$output['model']               = ! empty( $input['model'] ) ? sanitize_text_field( $input['model'] ) : $defaults['model'];
		$output['base_url']            = ! empty( $input['base_url'] ) ? esc_url_raw( $input['base_url'] ) : $defaults['base_url'];
		$output['title']               = ! empty( $input['title'] ) ? sanitize_text_field( $input['title'] ) : $defaults['title'];
		$output['button_label']        = ! empty( $input['button_label'] ) ? sanitize_text_field( $input['button_label'] ) : $defaults['button_label'];
		$output['welcome_message']     = isset( $input['welcome_message'] ) ? sanitize_textarea_field( $input['welcome_message'] ) : $defaults['welcome_message'];
		$output['custom_instructions'] = isset( $input['custom_instructions'] ) ? sanitize_textarea_field( $input['custom_instructions'] ) : '';
		$output['color_mode']          = isset( $input['color_mode'], self::get_color_modes()[ $input['color_mode'] ] ) ? $input['color_mode'] : $defaults['color_mode'];
		$output['primary_color']       = ! empty( $input['primary_color'] ) && sanitize_hex_color( $input['primary_color'] ) ? sanitize_hex_color( $input['primary_color'] ) : $defaults['primary_color'];

		$output['history_limit'] = isset( $input['history_limit'] ) && is_numeric( $input['history_limit'] ) ? max( 0, min( 20, (int) $input['history_limit'] ) ) : $defaults['history_limit'];

		return $output;
	}

	/**
	 * Render a single settings field.
	 *
	 * @since 1.0.0
	 * @param array $args { 'key' => string }.
	 */
	public function render_field( $args ) {

		$key      = $args['key'];
		$settings = self::get_settings();
		$value    = isset( $settings[ $key ] ) ? $settings[ $key ] : '';
		$name     = self::OPTION_NAME . "[$key]";

		switch ( $key ) {
			case 'enabled':
				printf(
					'<label><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</label>',
					esc_attr( $name ),
					checked( $value, true, false ),
					esc_html__( 'Yes', 'maqsy-faq-ai-chat' )
				);
				break;

			case 'provider':
				echo '<select name="' . esc_attr( $name ) . '">';
				foreach ( Maqsy_AI_Registry::get_providers() as $id => $provider ) {
					printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $id ), selected( $value, $id, false ), esc_html( $provider->get_label() ) );
				}
				echo '</select>';
				break;

			case 'color_mode':
				echo '<select name="' . esc_attr( $name ) . '">';
				foreach ( self::get_color_modes() as $id => $label ) {
					printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $id ), selected( $value, $id, false ), esc_html( $label ) );
				}
				echo '</select>';
				break;

			case 'api_key':
				printf( '<input type="password" class="regular-text" name="%1$s" value="%2$s" autocomplete="off" />', esc_attr( $name ), esc_attr( $value ) );
				break;

			case 'history_limit':
				printf( '<input type="number" min="0" max="20" step="1" name="%1$s" value="%2$s" /><p class="description">%3$s</p>', esc_attr( $name ), esc_attr( $value ), esc_html__( 'Send this many previous visitor and assistant messages with each new question, in chronological order. The current question is always included separately. Set to 0 to disable conversation history. More messages may increase API usage.', 'maqsy-faq-ai-chat' ) );
				break;

			case 'primary_color':
				printf( '<input type="color" name="%1$s" value="%2$s" />', esc_attr( $name ), esc_attr( $value ) );
				break;

			case 'base_url':
				$presets   = self::get_base_url_presets();
				$is_custom = ! isset( $presets[ $value ] );
				// The preset/custom toggle and the model autocomplete/fetch
				// behaviour live in admin/js/settings.js, enqueued by
				// enqueue_settings_assets() for this page only.
				?>
				<select id="maqsy_base_url_preset">
					<?php foreach ( $presets as $url => $label ) : ?>
						<option value="<?php echo esc_attr( $url ); ?>" <?php selected( ! $is_custom && $value === $url ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
					<option value="custom" <?php selected( $is_custom ); ?>><?php esc_html_e( 'Custom', 'maqsy-faq-ai-chat' ); ?></option>
				</select>
				<input
					type="url"
					id="maqsy_base_url_custom"
					class="regular-text"
					name="<?php echo esc_attr( $name ); ?>"
					value="<?php echo esc_attr( $value ); ?>"
					placeholder="https://…/v1"
					style="display:<?php echo $is_custom ? 'inline-block' : 'none'; ?>;margin-top:6px;"
				/>
				<?php
				break;

			case 'model':
				?>
				<input
					type="text"
					id="maqsy_model_input"
					class="regular-text"
					name="<?php echo esc_attr( $name ); ?>"
					value="<?php echo esc_attr( $value ); ?>"
					autocomplete="off"
					placeholder="gpt-4o-mini"
				/>
				<button type="button" id="maqsy_fetch_models" class="button">
					<?php esc_html_e( 'Fetch models', 'maqsy-faq-ai-chat' ); ?>
				</button>
				<div id="maqsy_models_status" style="opacity:0.75;position:absolute;"></div>
				<?php
				break;

			case 'welcome_message':
			case 'custom_instructions':
				printf( '<textarea class="large-text" rows="4" name="%1$s">%2$s</textarea>', esc_attr( $name ), esc_textarea( $value ) );
				break;

			case 'system_prompt':
				$preview = Maqsy_AI_Chat::build_system_prompt( $settings );
				printf(
					'<textarea class="large-text code" rows="10" readonly>%1$s</textarea><p class="description">%2$s</p>',
					esc_textarea( $preview ),
					esc_html__( 'A live preview of the exact system prompt sent to the AI provider, built from the site & branding instructions and published FAQs above. Read-only — edit those fields to change it.', 'maqsy-faq-ai-chat' )
				);
				break;

			default:
				printf( '<input type="text" class="regular-text" name="%1$s" value="%2$s" />', esc_attr( $name ), esc_attr( $value ) );
		}
	}

	/**
	 * Render the settings page.
	 *
	 * @since 1.0.0
	 */
	public function render_settings_page() {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Maqsy – AI Chat Settings', 'maqsy-faq-ai-chat' ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'maqsy_ai_settings_group' );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
