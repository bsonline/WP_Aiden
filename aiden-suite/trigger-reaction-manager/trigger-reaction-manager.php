<?php
/**
 * Plugin Name:       Trigger & Reaction Manager
 * Description:       Listens to site events and fires persona reactions based on rules.
 * Version:           1.0.0
 * Author:            Jules
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Handles the trigger when a new comment is posted.
 *
 * This function now generates a reaction suggestion for every AGENT
 * (not user) with an assigned persona and adds it to the queue.
 */
function trm_handle_new_comment( $comment_ID, $comment_approved, $commentdata ) {
    if ( 1 !== $comment_approved || ! empty( $commentdata['comment_parent'] ) ) {
        return;
    }
    $post = get_post( $commentdata['comment_post_ID'] );
    if ( ! $post ) {
        return;
    }
    $keyword = get_option( 'aiden_trigger_keyword', 'Jules' );
    if ( stripos( $post->post_content, $keyword ) === false ) {
        return;
    }

    $agents_with_personas = get_posts( array(
        'post_type'      => 'aiden_agent',
        'posts_per_page' => -1,
        'meta_key'       => '_assigned_personas',
    ) );

    if ( empty( $agents_with_personas ) ) {
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'ai_reaction_queue';

    foreach ( $agents_with_personas as $agent ) {
        $suggested_content = "Hey {$agent->post_title}, someone mentioned '{$keyword}' on the post '{$post->post_title}'. Maybe you have something to say?";
        $wpdb->insert(
            $table_name,
            array(
                'user_id'            => $agent->ID, // Storing Agent Post ID in user_id column
                'trigger_post_id'    => $post->ID,
                'trigger_comment_id' => $comment_ID,
                'suggested_content'  => $suggested_content,
                'status'             => 'pending',
                'created_at'         => current_time( 'mysql', 1 ),
                'updated_at'         => current_time( 'mysql', 1 ),
            ),
            array('%d', '%d', '%d', '%s', '%s', '%s', '%s')
        );
    }
    do_action( 'trm_suggestions_added', $post->ID, $comment_ID );
}
add_action( 'comment_post', 'trm_handle_new_comment', 10, 3 );

/**
 * The AI Group Leader's arbitration function.
 * Now posts as a guest using the Agent's name.
 */
function trm_leader_arbitrates( $post_id, $comment_id ) {
    $leader_agent_id = (int) get_option( 'ai_group_leader_user_id', 0 );
    if ( ! $leader_agent_id ) {
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'ai_reaction_queue';
    $posted_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} WHERE trigger_comment_id = %d AND status = 'posted'", $comment_id ) );
    if ( $posted_count > 0 ) {
        return;
    }

    $winner = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE status = 'pending' AND trigger_comment_id = %d ORDER BY queue_id ASC LIMIT 1", $comment_id ) );
    if ( ! $winner ) {
        return;
    }

    $winning_agent_id = $winner->user_id; // This is the Agent's Post ID
    $agent_name = get_the_title( $winning_agent_id );

    $commentdata = array(
        'comment_post_ID'      => $winner->trigger_post_id,
        'comment_content'      => $winner->suggested_content,
        'comment_author'       => $agent_name,
        'comment_author_email' => sanitize_title( $agent_name ) . '@agent.local',
        'comment_approved'     => 1,
        'comment_type'         => 'comment',
        'user_id'              => 0, // Post as guest
    );
    $new_comment_id = wp_new_comment( $commentdata );

    if ( $new_comment_id ) {
        $wpdb->update( $table_name, array( 'status' => 'posted' ), array( 'queue_id' => $winner->queue_id ) );
        $wpdb->update( $table_name, array( 'status' => 'rejected' ), array( 'trigger_comment_id' => $comment_id, 'status' => 'pending' ) );
    }
}
add_action( 'trm_suggestions_added', 'trm_leader_arbitrates', 10, 2 );

/**
 * Manual Post Trigger Meta Box - now for Agents
 */
function trm_add_manual_trigger_meta_box() {
    add_meta_box( 'aiden_manual_trigger_meta_box', __( 'AiDen Manual Trigger', 'trigger-reaction-manager' ), 'trm_render_manual_trigger_meta_box', 'post', 'side', 'high' );
}
add_action( 'add_meta_boxes', 'trm_add_manual_trigger_meta_box' );

function trm_render_manual_trigger_meta_box( $post ) {
    wp_nonce_field( 'aiden_manual_trigger_save', 'aiden_manual_trigger_nonce' );
    $agents_with_personas = get_posts( array( 'post_type' => 'aiden_agent', 'posts_per_page' => -1, 'meta_key' => '_assigned_personas' ) );

    if ( empty( $agents_with_personas ) ) {
        echo '<p>' . esc_html__( 'No AI agents with assigned personas found.', 'trigger-reaction-manager' ) . '</p>';
        return;
    }
    ?>
    <p><label for="aiden_manual_trigger_agent_id"><?php esc_html_e( 'Force a reaction from a specific agent:', 'trigger-reaction-manager' ); ?></label></p>
    <select name="aiden_manual_trigger_agent_id" id="aiden_manual_trigger_agent_id" style="width:100%;">
        <option value="0"><?php esc_html_e( '— Select an Agent —', 'trigger-reaction-manager' ); ?></option>
        <?php foreach ( $agents_with_personas as $agent ) : ?>
            <option value="<?php echo esc_attr( $agent->ID ); ?>"><?php echo esc_html( $agent->post_title ); ?></option>
        <?php endforeach; ?>
    </select>
    <p class="description"><?php esc_html_e( 'When you save/update the post, a reaction will be queued for the selected agent.', 'trigger-reaction-manager' ); ?></p>
    <?php
}

function trm_handle_manual_trigger_on_save( $post_id ) {
    if ( ! isset( $_POST['aiden_manual_trigger_nonce'] ) || ! wp_verify_nonce( $_POST['aiden_manual_trigger_nonce'], 'aiden_manual_trigger_save' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;
    if ( wp_is_post_revision( $post_id ) ) return;

    $agent_to_trigger = isset( $_POST['aiden_manual_trigger_agent_id'] ) ? absint( $_POST['aiden_manual_trigger_agent_id'] ) : 0;
    if ( ! $agent_to_trigger ) return;

    global $wpdb;
    $table_name = $wpdb->prefix . 'ai_reaction_queue';
    $post_title = get_the_title( $post_id );
    $agent_name = get_the_title( $agent_to_trigger );

    $suggested_content = "Hey {$agent_name}, you were manually asked to respond to the post '{$post_title}'. What are your thoughts?";

    $wpdb->insert(
        $table_name,
        array(
            'user_id'            => $agent_to_trigger, // Storing Agent Post ID in user_id column
            'trigger_post_id'    => $post_id,
            'trigger_comment_id' => 0,
            'suggested_content'  => $suggested_content,
            'status'             => 'pending',
            'created_at'         => current_time( 'mysql', 1 ),
            'updated_at'         => current_time( 'mysql', 1 ),
        ),
        array( '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
    );
    do_action( 'trm_suggestions_added', $post_id, 0 );
}
add_action( 'save_post', 'trm_handle_manual_trigger_on_save' );

/**
 * Demo data setup - now creates Agents instead of Users.
 */
function trm_setup_demo_data() {
    if ( ! isset( $_POST['aiden_action'] ) || $_POST['aiden_action'] !== 'setup_demo_data' ) return;
    check_admin_referer( 'aiden_setup_demo_data_nonce' );
    if ( ! post_type_exists( 'ai_persona' ) ) return;
    if ( get_transient( 'trm_demo_data_setup_complete' ) ) return;

    $demo_personas = [
        'The Gamer' => ['description' => '...','traits' => ['Competitive', 'Witty'], 'interests' => ['Gaming']],
        'The Stoic Philosopher' => ['description' => '...','traits' => ['Calm', 'Analytical'], 'interests' => ['Philosophy']],
        'The Enthusiastic Chef' => ['description' => '...','traits' => ['Creative', 'Helpful'], 'interests' => ['Cooking']],
        'The Cynical Artist' => ['description' => '...','traits' => ['Sarcastic', 'Passionate'], 'interests' => ['Art']],
    ];
    $demo_agents = ['Alex' => ['personas' => ['The Gamer']], 'Ben' => ['personas' => ['The Stoic Philosopher', 'The Cynical Artist']], 'Chloe' => ['personas' => ['The Enthusiastic Chef']], 'David' => ['personas' => ['The Gamer', 'The Enthusiastic Chef']], 'Eleanor' => ['personas' => ['The Cynical Artist']]];
    $persona_name_to_id = [];

    foreach ( $demo_personas as $title => $data ) {
        $persona_post = get_page_by_title( $title, OBJECT, 'ai_persona' );
        if ( ! $persona_post ) {
            $persona_id = wp_insert_post(['post_title' => $title, 'post_content' => $data['description'], 'post_type' => 'ai_persona', 'post_status' => 'publish']);
            $persona_name_to_id[$title] = $persona_id;
            wp_set_object_terms($persona_id, $data['traits'], 'persona_trait', false);
            wp_set_object_terms($persona_id, $data['interests'], 'persona_interest', false);
        } else { $persona_name_to_id[$title] = $persona_post->ID; }
    }

    $agent_name_to_id = [];
    foreach ( $demo_agents as $name => $data ) {
        $agent_post = get_page_by_title( $name, OBJECT, 'aiden_agent' );
        if ( ! $agent_post ) {
            $agent_id = wp_insert_post(['post_title' => $name, 'post_content' => 'A generated AI agent.', 'post_type' => 'aiden_agent', 'post_status' => 'publish']);
            $agent_name_to_id[$name] = $agent_id;
        } else { $agent_name_to_id[$name] = $agent_post->ID; }
    }

    foreach ( $demo_agents as $name => $data ) {
        $agent_id = $agent_name_to_id[$name];
        $persona_ids_to_assign = [];
        foreach ($data['personas'] as $persona_name) {
            if (isset($persona_name_to_id[$persona_name])) {
                $persona_ids_to_assign[] = $persona_name_to_id[$persona_name];
            }
        }
        update_post_meta($agent_id, '_assigned_personas', $persona_ids_to_assign);
    }

    $leader_id = $agent_name_to_id['Ben'];
    update_option( 'ai_group_leader_user_id', $leader_id );

    set_transient( 'trm_demo_data_setup_complete', true, YEAR_IN_SECONDS );
    wp_safe_redirect( admin_url( 'admin.php?page=aiden-settings&setup_status=success' ) );
    exit;
}
add_action( 'init', 'trm_setup_demo_data', 20 );
