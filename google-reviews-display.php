<?php
/**
 * Plugin Name:       Google Reviews Display
 * Plugin URI:        https://muhammad-jawad.github.io/
 * Description:       Display Google Reviews in a secure, customizable format with caching and dashboard widgets.
 * Version:           1.3.2
 * Author:            Muhammad Jawad Arshad
 * Author URI:        https://muhammad-jawad.github.io/
 * Text Domain:       google-reviews-display
 * Domain Path:       /languages
 * Requires at least: 5.6
 * Tested up to:      6.8.2
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;


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

// Load translations
add_action('plugins_loaded', function () {
    load_plugin_textdomain('google-reviews-display', false, GRD_PLUGIN_LANG_DIR);

    // Initialize plugin
    if (class_exists('GoogleReviewsDisplay')) {
        new GoogleReviewsDisplay();
    }
});

// Include main plugin class
require_once GRD_PLUGIN_DIR . 'includes/class-google-reviews-display.php';


// Define path to the update checker
$update_checker_path = GRD_PLUGIN_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';

if (file_exists($update_checker_path)) {
    require_once $update_checker_path;

    // Instantiate the update checker using namespaced class
    $myUpdateChecker = PucFactory::buildUpdateChecker(
        'https://github.com/muhammad-jawad/google-reviews-display/', // Your GitHub repo
        __FILE__,
        'google-reviews-display'
    );

    // Optionally, set the branch (default is 'master')
    $myUpdateChecker->setBranch('release');
} else {
    error_log('PUC: plugin-update-checker.php not found at expected path: ' . $update_checker_path);
}