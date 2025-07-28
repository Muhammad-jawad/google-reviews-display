<?php
/**
 * Plugin Name:       Google Reviews Display
 * Plugin URI:        https://muhammad-jawad.github.io/
 * Description:       Display Google Reviews in a secure, customizable format with caching and dashboard widgets.
 * Version:           1.3.0
 * Author:            Muhammad Jawad Arshad
 * Author URI:        https://muhammad-jawad.github.io/
 * Text Domain:       google-reviews-display
 * Domain Path:       /languages
 * Requires at least: 5.6
 * Tested up to:      6.5
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

defined('ABSPATH') || exit;

// Define plugin path and URL constants
if (!defined('GRD_PLUGIN_FILE')) {
    define('GRD_PLUGIN_FILE', __FILE__);
}

if (!defined('GRD_PLUGIN_DIR')) {
    define('GRD_PLUGIN_DIR', plugin_dir_path(GRD_PLUGIN_FILE));
}

if (!defined('GRD_PLUGIN_URL')) {
    define('GRD_PLUGIN_URL', plugin_dir_url(GRD_PLUGIN_FILE));
}

if (!defined('GRD_PLUGIN_BASENAME')) {
    define('GRD_PLUGIN_BASENAME', plugin_basename(GRD_PLUGIN_FILE));
}

if (!defined('GRD_PLUGIN_LANG_DIR')) {
    define('GRD_PLUGIN_LANG_DIR', dirname(GRD_PLUGIN_BASENAME) . '/languages');
}


// Load plugin translations
add_action('plugins_loaded', 'grd_load_textdomain');
function grd_load_textdomain() {
    load_plugin_textdomain('google-reviews-display', false, GRD_PLUGIN_LANG_DIR);
}

// Include the main plugin class
require_once GRD_PLUGIN_DIR . 'includes/class-google-reviews-display.php';

// Initialize the plugin
add_action('plugins_loaded', function () {
    if (class_exists('GoogleReviewsDisplay')) {
        new GoogleReviewsDisplay();
    }
});
