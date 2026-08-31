<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @since             1.0.0
 * @package           Maqsy
 *
 * @wordpress-plugin
 * Plugin Name:       Maqsy: FAQ Manager and AI Chat
 * Plugin URI:        https://github.com/sijad/maqsy-faq-ai-chat
 * Description:       Manage FAQs as a custom post type, insert them anywhere via shortcode or block, and let an AI chat widget answer visitor questions grounded in that content.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Sajjad Hashemian
 * Author URI:        https://hashemian.me
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       maqsy-faq-ai-chat
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'MAQSY_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-maqsy-activator.php
 */
function maqsy_activate() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-maqsy-activator.php';
	Maqsy_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-maqsy-deactivator.php
 */
function maqsy_deactivate() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-maqsy-deactivator.php';
	Maqsy_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'maqsy_activate' );
register_deactivation_hook( __FILE__, 'maqsy_deactivate' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-maqsy.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function maqsy_run() {

	$plugin = new Maqsy();
	$plugin->run();
}
maqsy_run();
