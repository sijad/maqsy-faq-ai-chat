<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @since      1.0.0
 *
 * @package    Maqsy
 * @subpackage Maqsy/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Maqsy
 * @subpackage Maqsy/public
 */
class Maqsy_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string $plugin_name       The name of the plugin.
	 * @param      string $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Register the post types, shortcodes, and blocks.
	 *
	 * @since    1.0.0
	 */
	public function init() {

		$this->register_style();
		$this->add_shortcodes();
		$this->register_post_types();
		$this->register_blocks();
	}

	/**
	 * Register the public stylesheet.
	 *
	 * Registered (not enqueued) on `init` so it's available to two consumers:
	 * enqueue_styles() on the front end, and the maqsy/faq block, whose
	 * block.json names this handle as its `style` — that's what pulls the CSS
	 * into the (iframed) block editor canvas so the server-rendered preview
	 * matches the front end. enqueue_block_editor_assets styles do NOT reach the
	 * iframe, which is why the block `style` handle is used instead.
	 *
	 * @since    1.0.0
	 */
	public function register_style() {

		$asset_file = plugin_dir_path( __DIR__ ) . 'build/public.asset.php';
		$asset      = file_exists( $asset_file ) ? include $asset_file : array( 'version' => $this->version );

		wp_register_style(
			$this->plugin_name . '-public',
			plugin_dir_url( __DIR__ ) . 'build/style-public.css',
			array(),
			$asset['version']
		);
	}

	/**
	 * Register the maqsy/faq block.
	 *
	 * @since    1.0.0
	 */
	public function register_blocks() {

		$block_dir = plugin_dir_path( __DIR__ ) . 'build/block';

		if ( file_exists( $block_dir . '/block.json' ) ) {
			register_block_type( $block_dir );
		}
	}

	/**
	 * Register the post types
	 *
	 * @since    1.0.0
	 */
	public function register_post_types() {
		register_post_type(
			'maqsy',
			array(
				'label'               => __( 'FAQ', 'maqsy-faq-ai-chat' ),
				'description'         => __( 'FAQ', 'maqsy-faq-ai-chat' ),
				'labels'              => array(
					'name'                  => _x( 'FAQs', 'Post Type General Name', 'maqsy-faq-ai-chat' ),
					'singular_name'         => _x( 'FAQ', 'Post Type Singular Name', 'maqsy-faq-ai-chat' ),
					'menu_name'             => __( 'FAQs', 'maqsy-faq-ai-chat' ),
					'name_admin_bar'        => __( 'FAQ', 'maqsy-faq-ai-chat' ),
					'archives'              => __( 'Item Archives', 'maqsy-faq-ai-chat' ),
					'attributes'            => __( 'Item Attributes', 'maqsy-faq-ai-chat' ),
					'parent_item_colon'     => __( 'Parent Item:', 'maqsy-faq-ai-chat' ),
					'all_items'             => __( 'All Items', 'maqsy-faq-ai-chat' ),
					'add_new_item'          => __( 'Add New Item', 'maqsy-faq-ai-chat' ),
					'add_new'               => __( 'Add New', 'maqsy-faq-ai-chat' ),
					'new_item'              => __( 'New Item', 'maqsy-faq-ai-chat' ),
					'edit_item'             => __( 'Edit Item', 'maqsy-faq-ai-chat' ),
					'update_item'           => __( 'Update Item', 'maqsy-faq-ai-chat' ),
					'view_item'             => __( 'View Item', 'maqsy-faq-ai-chat' ),
					'view_items'            => __( 'View Items', 'maqsy-faq-ai-chat' ),
					'search_items'          => __( 'Search Item', 'maqsy-faq-ai-chat' ),
					'not_found'             => __( 'Not found', 'maqsy-faq-ai-chat' ),
					'not_found_in_trash'    => __( 'Not found in Trash', 'maqsy-faq-ai-chat' ),
					'featured_image'        => __( 'Featured Image', 'maqsy-faq-ai-chat' ),
					'set_featured_image'    => __( 'Set featured image', 'maqsy-faq-ai-chat' ),
					'remove_featured_image' => __( 'Remove featured image', 'maqsy-faq-ai-chat' ),
					'use_featured_image'    => __( 'Use as featured image', 'maqsy-faq-ai-chat' ),
					'insert_into_item'      => __( 'Insert into item', 'maqsy-faq-ai-chat' ),
					'uploaded_to_this_item' => __( 'Uploaded to this item', 'maqsy-faq-ai-chat' ),
					'items_list'            => __( 'Items list', 'maqsy-faq-ai-chat' ),
					'items_list_navigation' => __( 'Items list navigation', 'maqsy-faq-ai-chat' ),
					'filter_items_list'     => __( 'Filter items list', 'maqsy-faq-ai-chat' ),
				),
				'supports'            => array( 'title' ),
				'hierarchical'        => false,
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'menu_position'       => 5,
				'menu_icon'           => 'dashicons-sos',
				'show_in_admin_bar'   => false,
				'show_in_nav_menus'   => false,
				'can_export'          => true,
				'has_archive'         => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'capability_type'     => 'post',
				'show_in_rest'        => false,
			),
		);
	}

	/**
	 * The available question templates, keyed by their stored value.
	 *
	 * @since    1.0.0
	 * @return   array
	 */
	public static function get_templates() {
		return array(
			'simple'    => __( 'Simple', 'maqsy-faq-ai-chat' ),
			'accordion' => __( 'Accordion', 'maqsy-faq-ai-chat' ),
		);
	}

	/**
	 * Add short codes.
	 *
	 * @since    1.0.0
	 */
	public function add_shortcodes() {

		add_shortcode( 'maqsy', array( $this, 'faq_short_code' ) );
	}

	/**
	 * Render short code.
	 *
	 * @since    1.0.0
	 * @param    array $atts Shortcode attributes: id, template.
	 */
	public function faq_short_code( $atts ) {

		$atts = shortcode_atts(
			array(
				'id'       => 0,
				'template' => '',
			),
			$atts,
			'maqsy'
		);

		return self::render_faq(
			$atts['id'],
			array(
				'template' => $atts['template'],
			)
		);
	}

	/**
	 * Whether FAQ markup should render in the current request context.
	 *
	 * FAQ output — and especially its FAQPage JSON-LD — belongs only on a
	 * singular view whose content is the FAQ. On archives, blog/search/category
	 * listings and feeds the same FAQ (or several different ones) can appear
	 * many times per URL, which breaks Google's one-FAQPage-per-page structured
	 * data guidance and defeats should_enqueue_assets(), which only inspects the
	 * first post in the main query.
	 *
	 * The block editor renders the block server-side over the REST
	 * block-renderer route, where is_singular() is false, so REST requests are
	 * allowed through to keep the editor preview working. Filterable for themes
	 * that deliberately want FAQs in other contexts.
	 *
	 * @since    1.0.0
	 * @return   bool
	 */
	public static function should_render() {

		$allowed = is_singular() || ( defined( 'REST_REQUEST' ) && REST_REQUEST );

		return (bool) apply_filters( 'maqsy_should_render', $allowed );
	}

	/**
	 * Render a FAQ post's questions/answers as HTML plus JSON-LD schema.
	 *
	 * Shared by the [maqsy] shortcode and the maqsy/faq block so both
	 * code paths stay in sync.
	 *
	 * @since    1.0.0
	 * @param    int   $id        The `maqsy` post ID to render.
	 * @param    array $overrides Optional. { 'template' => string } to override
	 *                            the post's saved defaults.
	 * @return   string The rendered markup, or an empty string if nothing to render.
	 */
	public static function render_faq( $id, array $overrides = array() ) {

		if ( ! self::should_render() ) {
			return '';
		}

		$id = intval( $id );

		if ( ! $id || 'maqsy' !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) {
			return '';
		}

		$questions = get_post_meta( $id, '_maqsy_questions', true );
		$questions = $questions ? json_decode( $questions, true ) : array();

		if ( ! is_array( $questions ) || empty( $questions ) ) {
			return '';
		}

		$template = get_post_meta( $id, '_maqsy_questions_template', true );
		$template = $template ? $template : 'simple';
		if ( ! empty( $overrides['template'] ) && isset( self::get_templates()[ $overrides['template'] ] ) ) {
			$template = $overrides['template'];
		}

		$classes = array( 'maqsy', "maqsy-$id", $template );

		$wrapper_tag = 'accordion' === $template ? 'ul' : 'dl';

		$html = "<$wrapper_tag class=\"" . esc_attr( implode( ' ', $classes ) ) . '">';

		foreach ( $questions as $q ) {
			$question = esc_html( $q['question'] );
			$answer   = wp_kses_post( wpautop( $q['answer'] ) );
			if ( 'accordion' === $template ) {
				$html .= '<li><details class="maqsy-item">';
				$html .= "<summary class=\"maqsy-question\">$question</summary>";
				$html .= "<div class=\"maqsy-answer\"><div class=\"maqsy-answer-content\">$answer</div></div>";
				$html .= '</details></li>';
			} else {
				$html .= "<dt class=\"maqsy-question\">$question</dt>";
				$html .= "<dd class=\"maqsy-answer\"><div class=\"maqsy-answer-content\">$answer</div></dd>";
			}
		}

		$html  .= "</$wrapper_tag>";
		$schema = "\n<script type=\"application/ld+json\">" . wp_json_encode( self::get_schema( $id ), JSON_PRETTY_PRINT | JSON_HEX_TAG ) . "</script>\n";

		return $html . $schema;
	}

	/**
	 * Get a published FAQ post's structured data without rendering its markup.
	 *
	 * @since    1.1.0
	 * @param    int $id The `maqsy` post ID.
	 * @return   array FAQPage schema, or an empty array if no FAQ is available.
	 */
	public static function get_schema( $id ) {

		$id = intval( $id );

		if ( ! $id || 'maqsy' !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) {
			return array();
		}

		$questions = get_post_meta( $id, '_maqsy_questions', true );
		$questions = $questions ? json_decode( $questions, true ) : array();

		if ( ! is_array( $questions ) || empty( $questions ) ) {
			return array();
		}

		$schema = array(
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => array(),
		);

		foreach ( $questions as $q ) {
			$schema['mainEntity'][] = array(
				'@type'          => 'Question',
				'name'           => wp_strip_all_tags( $q['question'] ),
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => wp_strip_all_tags( $q['answer'] ),
				),
			);
		}

		return $schema;
	}

	/**
	 * Determine whether the current request needs the public assets enqueued.
	 *
	 * Avoids loading the plugin's CSS/JS on pages that don't use the shortcode
	 * or block. Filterable so other code paths (e.g. widgets) can force it on.
	 *
	 * @since    1.0.0
	 */
	private function should_enqueue_assets() {

		$post   = get_post();
		$needed = self::should_render()
			&& $post instanceof WP_Post
			&& ( has_shortcode( $post->post_content, 'maqsy' ) || has_block( 'maqsy/faq', $post ) );

		return apply_filters( 'maqsy_enqueue_public_assets', $needed, $post );
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		if ( ! $this->should_enqueue_assets() ) {
			return;
		}

		wp_enqueue_style( $this->plugin_name . '-public' );
	}

	/**
	 * Enqueue the floating AI chat launcher, if the feature is configured and enabled.
	 *
	 * The launcher itself is a tiny always-loaded script; the chat UI (Preact,
	 * Tailwind) is only downloaded when a visitor actually clicks the button.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_chat_widget() {

		$settings = Maqsy_Settings::get_settings();

		if ( empty( $settings['enabled'] ) || empty( $settings['api_key'] ) ) {
			return;
		}

		$module_url  = plugin_dir_url( __DIR__ ) . 'build/chat/chat-launcher.js';
		$module_path = plugin_dir_path( __DIR__ ) . 'build/chat/chat-launcher.js';

		if ( ! file_exists( $module_path ) ) {
			return;
		}

		wp_register_script_module( 'maqsy-chat-launcher', $module_url, array(), MAQSY_VERSION );
		wp_enqueue_script_module( 'maqsy-chat-launcher' );

		add_action( 'wp_footer', array( $this, 'print_chat_settings' ) );
	}

	/**
	 * Print the chat widget's configuration as a JSON data island.
	 *
	 * Script modules (loaded as `type="module"`) aren't handled by
	 * `wp_scripts()`, so `wp_localize_script()` can't attach data to them.
	 * A plain JSON `<script>` tag works regardless of module load timing,
	 * and the launcher module reads it via `getElementById`.
	 *
	 * @since 1.0.0
	 */
	public function print_chat_settings() {

		$settings = Maqsy_Settings::get_settings();

		$data = array(
			'enabled'        => true,
			'restUrl'        => esc_url_raw( rest_url( 'maqsy/v1/chat' ) ),
			'nonce'          => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
			'title'          => $settings['title'],
			'buttonLabel'    => $settings['button_label'],
			'welcomeMessage' => $settings['welcome_message'],
			'historyLimit'   => max( 0, min( 20, (int) $settings['history_limit'] ) ),
			'loadingMessage' => __( 'Preparing your answer…', 'maqsy-faq-ai-chat' ),
			'waitingMessage' => __( 'Still working on your answer. Thanks for waiting.', 'maqsy-faq-ai-chat' ),
			'errorMessage'   => __( "Sorry, I couldn't respond. Please try again.", 'maqsy-faq-ai-chat' ),
			'colorMode'      => $settings['color_mode'],
			'primaryColor'   => $settings['primary_color'],
			'storageKey'     => 'maqsy-chat-history:' . (string) wp_parse_url( home_url(), PHP_URL_PATH ),
		);

		printf(
			'<script type="application/json" id="maqsy-chat-settings">%s</script>' . "\n",
			wp_json_encode( $data )
		);
	}
}
