<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @since      1.0.0
 *
 * @package    Maqsy
 * @subpackage Maqsy/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Maqsy
 * @subpackage Maqsy/includes
 */
class Maqsy {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Maqsy_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'MAQSY_VERSION' ) ) {
			$this->version = MAQSY_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'maqsy';

		$this->load_dependencies();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->define_rest_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Maqsy_Loader. Orchestrates the hooks of the plugin.
	 * - Maqsy_Admin. Defines all hooks for the admin area.
	 * - Maqsy_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-maqsy-loader.php';

		/**
		 * The class responsible for activation tasks and data migrations.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-maqsy-activator.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'admin/class-maqsy-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'public/class-maqsy-public.php';

		/**
		 * The class responsible for registering the plugin's REST API routes.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-maqsy-rest-controller.php';

		/**
		 * The classes that implement the pluggable AI chat feature.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'includes/ai/interface-maqsy-ai-provider.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/ai/interface-maqsy-ai-provider-supports-models.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/ai/class-maqsy-ai-provider-openai-compatible.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/ai/class-maqsy-ai-registry.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/ai/class-maqsy-ai-chat.php';

		/**
		 * The class responsible for the AI chat settings page.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'admin/class-maqsy-settings.php';

		$this->loader = new Maqsy_Loader();
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Maqsy_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'edit_form_advanced', $plugin_admin, 'edit_form_advanced' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
		$this->loader->add_action( 'save_post', $plugin_admin, 'save_post' );
		$this->loader->add_action( 'post_submitbox_misc_actions', $plugin_admin, 'post_submitbox_misc_actions', -1 );

		$plugin_settings = new Maqsy_Settings();

		$this->loader->add_action( 'admin_menu', $plugin_settings, 'add_settings_page' );
		$this->loader->add_action( 'admin_init', $plugin_settings, 'register_settings' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_settings, 'enqueue_settings_assets' );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Maqsy_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'init', $plugin_public, 'init' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_chat_widget' );
	}

	/**
	 * Register all of the hooks related to the plugin's REST API routes.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_rest_hooks() {

		$rest_controller = new Maqsy_Rest_Controller();

		$this->loader->add_action( 'rest_api_init', $rest_controller, 'register_routes' );
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Maqsy_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}
}
