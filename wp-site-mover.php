<?php
/**
 * Plugin Name:       WP SiteMover - Backup, Migration & Site Cloning
 * Plugin URI:        https://wordpress.org/plugins/wp-site-mover/
 * Description:       High-performance WordPress backup, zero-downtime migration, and site cloning plugin. Supports large sites (20GB+) and large files (1GB+). Includes an independent standalone installer for clean hosting.
 * Version:           1.0.0
 * Author:            Massimiliano Masserelli
 * Author URI:        https://github.com/maxnegro/
 * License:           GPL-2.0+
 * Text Domain:       wp-site-mover
 * Domain Path:       /languages
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Define Plugin Constants
define('SITEMOVER_VERSION', '1.0.0');
define('SITEMOVER_FILE', __FILE__);
define('SITEMOVER_PATH', plugin_dir_path(__FILE__));
define('SITEMOVER_URL', plugin_dir_url(__FILE__));
define('SITEMOVER_UPLOADS_DIR', wp_upload_dir()['basedir'] . '/site-mover-packages');
define('SITEMOVER_UPLOADS_URL', wp_upload_dir()['baseurl'] . '/site-mover-packages');

// Require Class Loader
require_once SITEMOVER_PATH . 'includes/class-site-mover-package-manager.php';
require_once SITEMOVER_PATH . 'includes/class-site-mover-db-exporter.php';
require_once SITEMOVER_PATH . 'includes/class-site-mover-archive-builder.php';
require_once SITEMOVER_PATH . 'includes/class-site-mover-installer-generator.php';
require_once SITEMOVER_PATH . 'includes/class-site-mover-admin.php';
require_once SITEMOVER_PATH . 'includes/class-site-mover.php';

/**
 * Begins execution of the plugin.
 */
function run_site_mover() {
    $plugin = new SiteMover();
    $plugin->run();
}

// Activation and Deactivation Hooks
register_activation_hook(__FILE__, array('SiteMover_Package_Manager', 'init_storage_dir'));
register_deactivation_hook(__FILE__, function() {
    // Flush rewrite rules or temporary states if needed
});

add_action('plugins_loaded', function() {
    load_plugin_textdomain('wp-site-mover', false, dirname(plugin_basename(__FILE__)) . '/languages/');
});

run_site_mover();
