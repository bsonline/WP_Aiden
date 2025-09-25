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

    // --- Create a dedicated "Bot" user for post authorship ---
    $bot_username = 'AiDen Bot';
    if ( ! username_exists( $bot_username ) ) {
        $bot_user_id = wp_create_user( $bot_username, wp_generate_password(), 'bot@aiden.local' );
        wp_update_user(['ID' => $bot_user_id, 'display_name' => $bot_username]);
    } else {
        $bot_user = get_user_by( 'login', $bot_username );
        $bot_user_id = $bot_user->ID;
    }
    update_option( 'aiden_bot_user_id', $bot_user_id );

    $leader_id = $agent_name_to_id['Ben'];
    update_option( 'ai_group_leader_user_id', $leader_id );

    set_transient( 'trm_demo_data_setup_complete', true, YEAR_IN_SECONDS );
    wp_safe_redirect( admin_url( 'admin.php?page=aiden-settings&setup_status=success' ) );
    exit;
}
add_action( 'init', 'trm_setup_demo_data', 20 );

/**
 * ===================================================================
 * Proactive Scheduled Posting
 * ===================================================================
 */

/**
 * Executes on the WP-Cron schedule to have agents create new posts.
 */
function trm_execute_proactive_posting() {
    // 1. Check if the feature is enabled in settings.
    $is_enabled = get_option( 'aiden_enable_scheduled_posting' );
    if ( ! $is_enabled ) {
        return;
    }

    // 2. Get all published agents.
    $all_agents = get_posts( array(
        'post_type'      => 'aiden_agent',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'fields'         => 'ids', // We only need the IDs.
    ) );

    if ( count( $all_agents ) < 2 ) {
        return; // Not enough agents to do anything interesting.
    }

    // 3. Randomly select 2 or 3 agents.
    $num_to_select = rand( 2, 3 );
    if ( count( $all_agents ) < $num_to_select ) {
        $num_to_select = count( $all_agents );
    }
    $selected_keys = array_rand( $all_agents, $num_to_select );

    if ( ! is_array( $selected_keys ) ) {
        $selected_keys = array( $selected_keys );
    }

    // 4. Get the Bot User ID for authorship.
    $bot_user_id = get_option( 'aiden_bot_user_id', 0 );
    if ( ! $bot_user_id ) {
        $admins = get_users( ['role' => 'administrator', 'number' => 1] );
        $bot_user_id = ! empty($admins) ? $admins[0]->ID : 1;
    }

    // 5. Create a draft post for each selected agent.
    foreach ( $selected_keys as $key ) {
        $agent_id = $all_agents[$key];
        $agent_name = get_the_title( $agent_id );

        $api_key = get_option( 'aiden_pollinations_api_key' );
        $post_title = "A Musin' by " . $agent_name; // Fallback title
        $post_content = "This is a new post proactively created by the agent, {$agent_name}. In the future, this content will be generated by an LLM based on trending topics."; // Fallback content

        if ( ! empty( $api_key ) ) {
            $agent_post = get_post( $agent_id );
            $assigned_persona_ids = get_post_meta( $agent_id, '_assigned_personas', true );
            $prompt_parts = [ "You are an AI agent named {$agent_name}." ];
            if ( ! empty( $agent_post->post_content ) ) {
                $prompt_parts[] = $agent_post->post_content;
            }
            if ( ! empty( $assigned_persona_ids ) ) {
                $traits = wp_get_object_terms( $assigned_persona_ids, 'persona_trait', ['fields' => 'names'] );
                if ( ! is_wp_error( $traits ) && ! empty( $traits ) ) {
                    $prompt_parts[] = 'Your personality traits include: ' . implode( ', ', $traits );
                }
                $interests = wp_get_object_terms( $assigned_persona_ids, 'persona_interest', ['fields' => 'names'] );
                if ( ! is_wp_error( $interests ) && ! empty( $interests ) ) {
                    $prompt_parts[] = 'You are interested in ' . implode( ', ', $interests );
                }
            }
            $prompt_parts[] = "Write a short, engaging blog post (around 3-4 paragraphs) about a topic that interests you. The post should have a clear, clickable title. Format the output as a JSON object with two keys: 'title' and 'content'.";
            $full_prompt = implode( ' ', $prompt_parts );

            $api_url = 'https://pollinations.ai/api/v1/text'; // Placeholder URL
            $response = wp_remote_post( $api_url, [
                'method'    => 'POST',
                'headers'   => [ 'Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $api_key ],
                'body'      => json_encode( [ 'prompt' => $full_prompt, 'model' => 'gpt-3.5-turbo' ] ), // Educated guess on model
                'timeout'   => 90,
            ] );

            if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
                $body = json_decode( wp_remote_retrieve_body( $response ), true );
                if ( ! empty( $body['title'] ) && ! empty( $body['content'] ) ) {
                    $post_title = sanitize_text_field( $body['title'] );
                    $post_content = wp_kses_post( $body['content'] );
                }
            }
        }

        wp_insert_post( [
            'post_title'   => $post_title,
            'post_content' => $post_content,
            'post_status'  => 'publish',
            'post_author'  => $bot_user_id,
            'post_type'    => 'post',
        ] );
    }
}
add_action( 'aiden_proactive_post_hook', 'trm_execute_proactive_posting' );

/**
 * ===================================================================
 * Avatar Generation
 * ===================================================================
 */

/**
 * Handle the 'Generate New Avatar' button submission from the agent editor.
 */
function trm_handle_avatar_generation() {
    // --- Security and Data Validation ---
    if ( ! isset( $_POST['agent_id'] ) || ! isset( $_POST['aiden_generate_avatar_nonce'] ) ) {
        wp_die( 'Invalid request.', 'Security Error' );
    }

    $agent_id = absint( $_POST['agent_id'] );

    if ( ! wp_verify_nonce( $_POST['aiden_generate_avatar_nonce'], 'aiden_generate_avatar_' . $agent_id ) || ! current_user_can( 'edit_post', $agent_id ) ) {
        wp_die( 'You are not authorized to perform this action.', 'Security Error' );
    }

    $api_key = get_option( 'aiden_pollinations_api_key' );
    if ( empty( $api_key ) ) {
        // Redirect back with an error message to be displayed as an admin notice.
        wp_safe_redirect( add_query_arg( 'avatar_status', 'no_key', get_edit_post_link( $agent_id, 'raw' ) ) );
        exit;
    }

    $agent = get_post( $agent_id );
    if ( ! $agent || $agent->post_type !== 'aiden_agent' ) {
        wp_die( 'Invalid agent specified.', 'Error' );
    }

    // --- Construct the Prompt ---
    $assigned_persona_ids = get_post_meta( $agent_id, '_assigned_personas', true );
    $prompt_parts = ['photorealistic portrait of an AI agent named ' . $agent->post_title];
    if ( ! empty( $agent->post_content ) ) {
        $prompt_parts[] = $agent->post_content;
    }

    if ( ! empty( $assigned_persona_ids ) ) {
        $traits = wp_get_object_terms( $assigned_persona_ids, 'persona_trait', ['fields' => 'names'] );
        if ( ! is_wp_error( $traits ) && ! empty( $traits ) ) {
            $prompt_parts[] = 'Their personality traits include: ' . implode( ', ', $traits );
        }
        $interests = wp_get_object_terms( $assigned_persona_ids, 'persona_interest', ['fields' => 'names'] );
        if ( ! is_wp_error( $interests ) && ! empty( $interests ) ) {
            $prompt_parts[] = 'They are interested in ' . implode( ', ', $interests );
        }
    }
    $prompt = implode( '. ', $prompt_parts );

    // --- Make the API Call ---
    // NOTE: The exact API endpoint and request format are an educated guess based on common API patterns.
    // This may need to be adjusted based on the official Pollinations.ai documentation.
    $api_url = 'https://pollinations.ai/api/v1/image';
    $response = wp_remote_post( $api_url, [
        'method'    => 'POST',
        'headers'   => [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        ],
        'body'      => json_encode( [
            'prompt' => $prompt,
            'model'  => 'dall-e-3', // A reasonable default for high-quality portraits
            'width'  => 1024,
            'height' => 1024,
        ] ),
        'timeout'   => 60, // Increased timeout for image generation
    ] );

    // --- Process the API Response ---
    if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
        // API call failed.
        wp_safe_redirect( add_query_arg( 'avatar_status', 'api_error', get_edit_post_link( $agent_id, 'raw' ) ) );
        exit;
    }

    $body = json_decode( wp_remote_retrieve_body( $response ) );
    // Assuming the image URL is in a property named 'url' or 'output'. This may need adjustment.
    $image_url = $body->url ?? $body->output ?? '';

    if ( empty( $image_url ) ) {
        // Could not find image URL in response.
        wp_safe_redirect( add_query_arg( 'avatar_status', 'url_error', get_edit_post_link( $agent_id, 'raw' ) ) );
        exit;
    }

    // We need these files to sideload the image.
    require_once( ABSPATH . 'wp-admin/includes/media.php' );
    require_once( ABSPATH . 'wp-admin/includes/file.php' );
    require_once( ABSPATH . 'wp-admin/includes/image.php' );

    // Sideload the image from the URL to the media library.
    $attachment_id = media_sideload_image( $image_url, $agent_id, $agent->post_title, 'id' );

    if ( is_wp_error( $attachment_id ) ) {
        // Image download failed.
        wp_safe_redirect( add_query_arg( 'avatar_status', 'download_error', get_edit_post_link( $agent_id, 'raw' ) ) );
        exit;
    }

    // Set the downloaded image as the agent's featured image (avatar).
    set_post_thumbnail( $agent_id, $attachment_id );

    // Redirect back with a success message.
    wp_safe_redirect( add_query_arg( 'avatar_status', 'success', get_edit_post_link( $agent_id, 'raw' ) ) );
    exit;
}
add_action( 'admin_post_aiden_generate_avatar', 'trm_handle_avatar_generation' );

/**
 * Handle the 'Generate New Post Now' button submission from the agent editor.
 */
function trm_handle_manual_post_generation() {
    // --- Security and Data Validation ---
    if ( ! isset( $_POST['agent_id'] ) || ! isset( $_POST['aiden_generate_post_nonce'] ) ) {
        wp_die( 'Invalid request.', 'Security Error' );
    }

    $agent_id = absint( $_POST['agent_id'] );

    if ( ! wp_verify_nonce( $_POST['aiden_generate_post_nonce'], 'aiden_generate_post_' . $agent_id ) || ! current_user_can( 'edit_post', $agent_id ) ) {
        wp_die( 'You are not authorized to perform this action.', 'Security Error' );
    }

    $api_key = get_option( 'aiden_pollinations_api_key' );
    if ( empty( $api_key ) ) {
        wp_safe_redirect( add_query_arg( 'post_status', 'no_key', get_edit_post_link( $agent_id, 'raw' ) ) );
        exit;
    }

    $agent_post = get_post( $agent_id );
    if ( ! $agent_post || $agent_post->post_type !== 'aiden_agent' ) {
        wp_die( 'Invalid agent specified.', 'Error' );
    }

    // --- Generate Content ---
    $agent_name = $agent_post->post_title;
    $post_title = "A Musin' by " . $agent_name; // Fallback title
    $post_content = "This is a new post proactively created by the agent, {$agent_name}."; // Fallback content

    $assigned_persona_ids = get_post_meta( $agent_id, '_assigned_personas', true );
    $prompt_parts = [ "You are an AI agent named {$agent_name}." ];
    if ( ! empty( $agent_post->post_content ) ) {
        $prompt_parts[] = $agent_post->post_content;
    }
    if ( ! empty( $assigned_persona_ids ) ) {
        $traits = wp_get_object_terms( $assigned_persona_ids, 'persona_trait', ['fields' => 'names'] );
        if ( ! is_wp_error( $traits ) && ! empty( $traits ) ) {
            $prompt_parts[] = 'Your personality traits include: ' . implode( ', ', $traits );
        }
        $interests = wp_get_object_terms( $assigned_persona_ids, 'persona_interest', ['fields' => 'names'] );
        if ( ! is_wp_error( $interests ) && ! empty( $interests ) ) {
            $prompt_parts[] = 'You are interested in ' . implode( ', ', $interests );
        }
    }
    $prompt_parts[] = "Write a short, engaging blog post (around 3-4 paragraphs) about a topic that interests you. The post should have a clear, clickable title. Format the output as a JSON object with two keys: 'title' and 'content'.";
    $full_prompt = implode( ' ', $prompt_parts );

    $api_url = 'https://pollinations.ai/api/v1/text'; // Placeholder URL
    $response = wp_remote_post( $api_url, [
        'method'    => 'POST',
        'headers'   => [ 'Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $api_key ],
        'body'      => json_encode( [ 'prompt' => $full_prompt, 'model' => 'gpt-3.5-turbo' ] ),
        'timeout'   => 90,
    ] );

    if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
        wp_safe_redirect( add_query_arg( 'post_status', 'api_error', get_edit_post_link( $agent_id, 'raw' ) ) );
        exit;
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( ! empty( $body['title'] ) && ! empty( $body['content'] ) ) {
        $post_title = sanitize_text_field( $body['title'] );
        $post_content = wp_kses_post( $body['content'] );
    } else {
        wp_safe_redirect( add_query_arg( 'post_status', 'content_error', get_edit_post_link( $agent_id, 'raw' ) ) );
        exit;
    }

    // --- Create the Post ---
    $bot_user_id = get_option( 'aiden_bot_user_id', 0 );
    if ( ! $bot_user_id ) {
        $admins = get_users( ['role' => 'administrator', 'number' => 1] );
        $bot_user_id = ! empty($admins) ? $admins[0]->ID : 1;
    }

    $new_post_id = wp_insert_post( [
        'post_title'   => $post_title,
        'post_content' => $post_content,
        'post_status'  => 'publish',
        'post_author'  => $bot_user_id,
        'post_type'    => 'post',
    ] );

    if ( $new_post_id ) {
        wp_safe_redirect( add_query_arg( 'post_status', 'success', get_edit_post_link( $agent_id, 'raw' ) ) );
    } else {
        wp_safe_redirect( add_query_arg( 'post_status', 'publish_error', get_edit_post_link( $agent_id, 'raw' ) ) );
    }
    exit;
}
add_action( 'admin_post_aiden_generate_post', 'trm_handle_manual_post_generation' );
