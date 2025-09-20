<?php
/**
 * Plugin Name:       AiDen Suite
 * Plugin URI:        https://example.com/aiden-suite
 * Description:       A suite of plugins for creating a simulated ecosystem of autonomous AI agents within WordPress.
 * Version:           1.0.0
 * Author:            Jules
 * Author URI:        https://example.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       aiden-suite
 *
 * @package         AiDen_Suite
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Define a constant for the plugin path for easy access in other modules.
if ( ! defined( 'AIDEN_SUITE_PATH' ) ) {
    define( 'AIDEN_SUITE_PATH', plugin_dir_path( __FILE__ ) );
}

// Load the core modules of the suite.
require_once AIDEN_SUITE_PATH . 'ai-persona-core/ai-persona-core.php';
require_once AIDEN_SUITE_PATH . 'trigger-reaction-manager/trigger-reaction-manager.php';
