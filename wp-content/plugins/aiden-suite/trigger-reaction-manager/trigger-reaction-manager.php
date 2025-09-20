<?php
/**
 * Plugin Name:       Trigger & Reaction Manager
 * Plugin URI:        https://example.com/plugins/the-basics/
 * Description:       Listens to site events and fires persona reactions based on rules.
 * Version:           0.1.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Jules
 * Author URI:        https://example.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       trigger-reaction-manager
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Creates the custom database table for the reaction queue upon plugin activation.
 */
function trm_create_reaction_queue_table() {
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

    // We need to load the upgrade file to use dbDelta.
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}
register_activation_hook( __FILE__, 'trm_create_reaction_queue_table' );

/**
 * Handles the trigger when a new comment is posted.
 *
 * Instead of posting a comment, this function now generates a reaction
 * suggestion for every user with an assigned persona and adds it to the
 * moderation queue.
 *
 * @param int        $comment_ID       The ID of the newly posted comment.
 * @param int|string $comment_approved The approval status of the comment.
 * @param array      $commentdata      An array of comment data.
 */
function trm_handle_new_comment( $comment_ID, $comment_approved, $commentdata ) {
    // Only trigger for approved, top-level comments.
    if ( 1 !== $comment_approved || ! empty( $commentdata['comment_parent'] ) ) {
        return;
    }

    // Get the post that was commented on.
    $post = get_post( $commentdata['comment_post_ID'] );
    if ( ! $post ) {
        return;
    }

    // The keyword to look for. Get it from the settings, with a fallback.
    $keyword = get_option( 'aiden_trigger_keyword', 'Jules' );

    // Check if the trigger condition is met (e.g., keyword in post).
    if ( stripos( $post->post_content, $keyword ) === false ) {
        return; // Condition not met, do nothing.
    }

    // Get all users who have personas assigned to them.
    // This assumes the persona IDs are stored in a user meta field '_assigned_personas'.
    $args = array(
        'meta_key'     => '_assigned_personas',
        'meta_compare' => 'EXISTS',
    );
    $users_with_personas = get_users( $args );

    if ( empty( $users_with_personas ) ) {
        return; // No users to suggest a reaction for.
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'ai_reaction_queue';

    // Loop through each user and create a suggestion for them.
    foreach ( $users_with_personas as $user ) {

        // In the future, we would generate a unique comment for each user
        // based on their combined personas and memory.
        // For now, we'll create a generic placeholder suggestion.
        $suggested_content = "Hey {$user->display_name}, someone mentioned '{$keyword}' on the post '{$post->post_title}'. Maybe you have something to say?";

        $wpdb->insert(
            $table_name,
            array(
                'user_id'            => $user->ID,
                'trigger_post_id'    => $post->ID,
                'trigger_comment_id' => $comment_ID,
                'suggested_content'  => $suggested_content,
                'status'             => 'pending',
                'created_at'         => current_time( 'mysql', 1 ), // GMT time
                'updated_at'         => current_time( 'mysql', 1 ), // GMT time
            ),
            array(
                '%d', // user_id
                '%d', // trigger_post_id
                '%d', // trigger_comment_id
                '%s', // suggested_content
                '%s', // status
                '%s', // created_at
                '%s', // updated_at
            )
        );
    }

    // Announce that new suggestions have been added and are ready for arbitration.
    do_action( 'trm_suggestions_added', $post->ID, $comment_ID );
}
add_action( 'comment_post', 'trm_handle_new_comment', 10, 3 );

/**
 * The AI Group Leader's arbitration function.
 *
 * This function is triggered after suggestions are added to the queue.
 * It selects one winning suggestion and posts it on behalf of the winning agent.
 *
 * @param int $post_id The ID of the post that was commented on.
 * @param int $comment_id The ID of the comment that triggered the suggestions.
 */
function trm_leader_arbitrates( $post_id, $comment_id ) {
    $leader_id = (int) get_option( 'ai_group_leader_user_id' );
    if ( ! $leader_id ) {
        return; // No leader is designated, so no arbitration happens.
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'ai_reaction_queue';

    // Prevent processing if a comment has already been posted for this trigger.
    $posted_count = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$table_name} WHERE trigger_comment_id = %d AND status = 'posted'",
        $comment_id
    ) );

    if ( $posted_count > 0 ) {
        return; // Arbitration has already occurred for this event.
    }

    // Get the winning suggestion (oldest one in the queue for this specific trigger).
    $winner = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE status = 'pending' AND trigger_comment_id = %d ORDER BY queue_id ASC LIMIT 1",
        $comment_id
    ) );

    if ( ! $winner ) {
        return; // No pending suggestions found to arbitrate.
    }

    // Post the winning comment on behalf of the winning user.
    $commentdata = array(
        'comment_post_ID'      => $winner->trigger_post_id,
        'comment_content'      => $winner->suggested_content,
        'user_id'              => $winner->user_id,
        'comment_approved'     => 1,
        'comment_type'         => 'comment',
    );
    $new_comment_id = wp_new_comment( $commentdata );

    // If the comment was successfully posted, clean up the queue for this event.
    if ( $new_comment_id ) {
        // Mark the winning suggestion as 'posted'.
        $wpdb->update(
            $table_name,
            array( 'status' => 'posted' ),
            array( 'queue_id' => $winner->queue_id )
        );

        // Mark all other pending suggestions for this trigger as 'rejected'.
        $wpdb->update(
            $table_name,
            array( 'status' => 'rejected' ),
            array( 'trigger_comment_id' => $comment_id, 'status' => 'pending' )
        );
    }
}
add_action( 'trm_suggestions_added', 'trm_leader_arbitrates', 10, 2 );

/**
 * ===================================================================
 * Manual Post Trigger
 * ===================================================================
 */

/**
 * Register the meta box on the post editor screen.
 */
function trm_add_manual_trigger_meta_box() {
    add_meta_box(
        'aiden_manual_trigger_meta_box',
        __( 'AiDen Manual Trigger', 'trigger-reaction-manager' ),
        'trm_render_manual_trigger_meta_box',
        'post',
        'side',
        'high'
    );
}
add_action( 'add_meta_boxes', 'trm_add_manual_trigger_meta_box' );

/**
 * Render the HTML content for the manual trigger meta box.
 *
 * @param WP_Post $post The post object.
 */
function trm_render_manual_trigger_meta_box( $post ) {
    // Add a nonce field for security.
    wp_nonce_field( 'aiden_manual_trigger_save', 'aiden_manual_trigger_nonce' );

    $users_with_personas = get_users( array( 'meta_key' => '_assigned_personas', 'fields' => array( 'ID', 'display_name' ) ) );

    if ( empty( $users_with_personas ) ) {
        echo '<p>' . esc_html__( 'No AI agents with assigned personas found.', 'trigger-reaction-manager' ) . '</p>';
        return;
    }
    ?>
    <p>
        <label for="aiden_manual_trigger_user_id"><?php esc_html_e( 'Force a reaction from a specific agent:', 'trigger-reaction-manager' ); ?></label>
    </p>
    <select name="aiden_manual_trigger_user_id" id="aiden_manual_trigger_user_id" style="width:100%;">
        <option value="0"><?php esc_html_e( '— Select an Agent —', 'trigger-reaction-manager' ); ?></option>
        <?php foreach ( $users_with_personas as $user ) : ?>
            <option value="<?php echo esc_attr( $user->ID ); ?>">
                <?php echo esc_html( $user->display_name ); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <p class="description">
        <?php esc_html_e( 'When you save/update the post, a reaction will be queued for the selected agent. The AI Leader will then arbitrate.', 'trigger-reaction-manager' ); ?>
    </p>
    <?php
}

/**
 * Handle the manual trigger when a post is saved.
 *
 * @param int $post_id The ID of the post being saved.
 */
function trm_handle_manual_trigger_on_save( $post_id ) {
    // --- Security Checks ---
    if ( ! isset( $_POST['aiden_manual_trigger_nonce'] ) || ! wp_verify_nonce( $_POST['aiden_manual_trigger_nonce'], 'aiden_manual_trigger_save' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }
    if ( wp_is_post_revision( $post_id ) ) {
        return;
    }

    // --- Get the selected user ID ---
    $user_to_trigger = isset( $_POST['aiden_manual_trigger_user_id'] ) ? absint( $_POST['aiden_manual_trigger_user_id'] ) : 0;

    if ( ! $user_to_trigger ) {
        return; // No user was selected.
    }

    // --- Queue the suggestion ---
    global $wpdb;
    $table_name = $wpdb->prefix . 'ai_reaction_queue';
    $post_title = get_the_title( $post_id );
    $user = get_user_by( 'id', $user_to_trigger );

    $suggested_content = "Hey {$user->display_name}, you were manually asked to respond to the post '{$post_title}'. What are your thoughts?";

    $wpdb->insert(
        $table_name,
        array(
            'user_id'            => $user_to_trigger,
            'trigger_post_id'    => $post_id,
            'trigger_comment_id' => 0, // 0 indicates a post trigger, not a comment trigger.
            'suggested_content'  => $suggested_content,
            'status'             => 'pending',
            'created_at'         => current_time( 'mysql', 1 ),
            'updated_at'         => current_time( 'mysql', 1 ),
        ),
        array( '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
    );

    // --- Trigger arbitration ---
    // We pass 0 as the comment ID to signify this was not a comment-based trigger.
    do_action( 'trm_suggestions_added', $post_id, 0 );
}
add_action( 'save_post', 'trm_handle_manual_trigger_on_save' );

/**
 * A one-time setup function to create demo data for the AI agent system.
 *
 * This function is for demonstration purposes only, as there is no UI to
 * create users, personas, and link them. It makes the system testable.
 *
 * To run this, visit your WordPress site's front-end with `?setup_ai_demo=true`
 * in the URL. It is designed to only run once successfully.
 */
function trm_setup_demo_data() {
    // Only run when specifically requested via URL query.
    if ( ! isset( $_GET['setup_ai_demo'] ) ) {
        return;
    }

    // Use a transient to ensure this heavy operation only runs once.
    if ( get_transient( 'trm_demo_data_setup_complete' ) ) {
        wp_die( 'AI demo data has already been set up. To run again, delete the transient \'trm_demo_data_setup_complete\'.' );
    }

    // --- 1. Create a sample Persona Post ---
    $persona_post_title = 'Shared Persona';
    $persona_post = get_page_by_title( $persona_post_title, OBJECT, 'ai_persona' );
    if ( ! $persona_post ) {
        $persona_id = wp_insert_post( array(
            'post_title'   => $persona_post_title,
            'post_content' => 'This is a generic persona shared by multiple agents.',
            'post_type'    => 'ai_persona',
            'post_status'  => 'publish',
        ) );
    } else {
        $persona_id = $persona_post->ID;
    }

    if ( ! $persona_id || is_wp_error( $persona_id ) ) {
        wp_die( 'Error: Could not create the sample persona post.' );
    }

    // --- 2. Create Agent Users ---
    $agent_users = array( 'AIAgent_Bob', 'AIAgent_Carl' );
    $user_ids = array();
    foreach ( $agent_users as $username ) {
        if ( ! username_exists( $username ) ) {
            $password = wp_generate_password();
            $user_id = wp_create_user( $username, $password, strtolower( $username ) . '@persona.local' );
            $user_ids[ $username ] = $user_id;
        } else {
            $user = get_user_by( 'login', $username );
            $user_ids[ $username ] = $user->ID;
        }
    }

    // --- 3. Link the Persona to the Users ---
    $persona_ids_to_assign = array( $persona_id );
    foreach ( $user_ids as $user_id ) {
        // Storing as a serialized array of IDs.
        update_user_meta( $user_id, '_assigned_personas', $persona_ids_to_assign );
    }

    // --- 4. Designate a leader ---
    $leader_id = $user_ids['AIAgent_Bob'];
    update_option( 'ai_group_leader_user_id', $leader_id );

    // --- 5. Mark the setup as complete ---
    set_transient( 'trm_demo_data_setup_complete', true, YEAR_IN_SECONDS );

    wp_die( 'AI Demo Data Setup Complete! <br/> - Created Persona: ' . esc_html( $persona_post_title ) . ' (ID: ' . (int) $persona_id . ') <br/> - Created/Found Users: AIAgent_Bob, AIAgent_Carl <br/> - Assigned persona to both users. <br/> - Set AIAgent_Bob as the Group Leader.' );
}
add_action( 'init', 'trm_setup_demo_data' );
