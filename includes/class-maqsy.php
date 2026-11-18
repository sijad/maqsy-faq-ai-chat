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
			$this->version = '1.2.0';
		}
		$this->plugin_name = 'maqsy';

		$this->load_dependencies();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->define_rest_hooks();
		$this->define_quiz_hooks();
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

		/**
		 * The classes that implement conversational quizzes: the definition
		 * schema, the runtime engine, storage, analytics, AI generation, and
		 * their REST routes and admin screens.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'includes/quiz/class-maqsy-quiz-schema.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/quiz/class-maqsy-quiz-engine.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/quiz/class-maqsy-quiz-store.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/quiz/class-maqsy-quizzes.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/quiz/class-maqsy-quiz-analytics.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/quiz/class-maqsy-quiz-generator.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/quiz/class-maqsy-quiz-privacy.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/quiz/class-maqsy-quiz-rest-controller.php';
		require_once plugin_dir_path( __DIR__ ) . 'admin/class-maqsy-quiz-admin.php';

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
	 * Register all of the hooks related to conversational quizzes.
	 *
	 * @since    1.2.0
	 * @access   private
	 */
	private function define_quiz_hooks() {

		$quizzes = new Maqsy_Quizzes();

		$this->loader->add_action( 'init', $quizzes, 'init' );
		$this->loader->add_action( 'clean_post_cache', 'Maqsy_Quizzes', 'flush_cache', 10, 2 );

		$quiz_rest = new Maqsy_Quiz_Rest_Controller();

		$this->loader->add_action( 'rest_api_init', $quiz_rest, 'register_routes' );

		$quiz_admin = new Maqsy_Quiz_Admin();

		// Not just on activation: activation hooks don't run on plugin updates.
		$this->loader->add_action( 'init', 'Maqsy_Quiz_Store', 'maybe_upgrade' );
		$this->loader->add_action( 'admin_menu', $quiz_admin, 'add_menu' );
		$this->loader->add_action( 'edit_form_after_title', $quiz_admin, 'edit_form_after_title' );
		$this->loader->add_action( 'save_post_' . Maqsy_Quizzes::POST_TYPE, $quiz_admin, 'save_post' );
		$this->loader->add_action( 'before_delete_post', $quiz_admin, 'before_delete_post' );
		$this->loader->add_action( 'admin_enqueue_scripts', $quiz_admin, 'enqueue_assets' );
		$this->loader->add_action( 'admin_post_maqsy_quiz_export', $quiz_admin, 'export_csv' );
		$this->loader->add_action( 'manage_' . Maqsy_Quizzes::POST_TYPE . '_posts_custom_column', $quiz_admin, 'list_column', 10, 2 );
		$this->loader->add_filter( 'manage_' . Maqsy_Quizzes::POST_TYPE . '_posts_columns', $quiz_admin, 'list_columns' );
		$this->loader->add_filter( 'post_row_actions', $quiz_admin, 'row_actions', 10, 2 );
		$this->loader->add_filter( 'enter_title_here', $quiz_admin, 'enter_title_here', 10, 2 );
		$this->loader->add_filter( 'parent_file', $quiz_admin, 'parent_file' );

		$quiz_privacy = new Maqsy_Quiz_Privacy();

		$this->loader->add_filter( 'wp_privacy_personal_data_exporters', $quiz_privacy, 'register_exporter' );
		$this->loader->add_filter( 'wp_privacy_personal_data_erasers', $quiz_privacy, 'register_eraser' );
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
