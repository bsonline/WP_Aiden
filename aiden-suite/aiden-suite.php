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

// Load the GitHub Updater class and instantiate it only in the admin area.
if ( is_admin() ) {
    require_once AIDEN_SUITE_PATH . 'updater.php';
    new AiDen_GitHub_Updater();
}

/**
 * Create the custom database table for the reaction queue upon plugin activation.
 *
 * This function is placed in the main suite file to ensure it fires correctly
 * when the unified "AiDen Suite" plugin is activated.
 */
function aiden_suite_create_db_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'ai_reaction_queue';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table_name} (
        queue_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id bigint(20) UNSIGNED NOT NULL,
        trigger_post_id bigint(20) UNSIGNED NOT NULL,
        trigger_comment_id bigint(20) UNSIGNED DEFAULT 0 NOT NULL,
        suggested_content text NOT NULL,
        status varchar(20) DEFAULT 'pending' NOT NULL,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (queue_id),
        KEY user_id (user_id),
        KEY status (status)
    ) {$charset_collate};";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}
register_activation_hook( __FILE__, 'aiden_suite_create_db_table' );

/**
 * ===================================================================
 * Proactive Posting Cron Scheduling
 * ===================================================================
 */

// Define the custom cron hook name for our proactive posting event.
define( 'AIDEN_PROACTIVE_POST_HOOK', 'aiden_proactive_post_trigger' );

/**
 * Add a custom two-hour cron schedule to WordPress.
 *
 * @param array $schedules An array of non-default cron schedules.
 * @return array The modified schedules array.
 */
function aiden_suite_add_cron_schedules( $schedules ) {
    $schedules['every_two_hours'] = array(
        'interval' => 7200, // 2 hours in seconds
        'display'  => __( 'Every Two Hours' ),
    );
    return $schedules;
}
add_filter( 'cron_schedules', 'aiden_suite_add_cron_schedules' );

/**
 * Schedule the cron event upon plugin activation if it's not already scheduled.
 */
function aiden_suite_schedule_cron() {
    if ( ! wp_next_scheduled( AIDEN_PROACTIVE_POST_HOOK ) ) {
        // Schedule the event to run at the custom two-hour interval.
        wp_schedule_event( time(), 'every_two_hours', AIDEN_PROACTIVE_POST_HOOK );
    }
}
// We also hook our DB table creation to the same activation hook.
register_activation_hook( __FILE__, 'aiden_suite_schedule_cron' );


/**
 * Unschedule the cron event upon plugin deactivation for clean removal.
 */
function aiden_suite_unschedule_cron() {
    $timestamp = wp_next_scheduled( AIDEN_PROACTIVE_POST_HOOK );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, AIDEN_PROACTIVE_POST_HOOK );
    }
}
register_deactivation_hook( __FILE__, 'aiden_suite_unschedule_cron' );
