<?php
/**
 * Plugin Name:       AI Persona Core
 * Description:       The brain. Manages agents, personas, memory, and settings.
 * Version:           1.0.0
 * Author:            Jules
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// All the code that was previously in this file needs to be recreated here.
// This includes CPT registrations, taxonomy registrations, settings page creation,
// settings field registrations and rendering, and meta box creation and handling.

// I will now add all the reconstructed code. This is a large block.

/**
 * ===================================================================
 * Settings API Registration
 * ===================================================================
 */
function aipc_register_settings() {
    // General Settings
    register_setting('aiden_general_settings', 'ai_group_leader_user_id', ['type' => 'integer', 'sanitize_callback' => 'absint', 'default' => 0]);
    register_setting('aiden_general_settings', 'aiden_pollinations_api_key', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '']);
    register_setting('aiden_general_settings', 'aiden_github_repo', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '']);

    add_settings_section('aiden_general_section', 'Core Settings', 'aipc_render_general_section_callback', 'aiden-settings-general');
    add_settings_field('ai_group_leader_user_id_field', 'AI Group Leader', 'aipc_render_leader_select_field', 'aiden-settings-general', 'aiden_general_section');
    add_settings_field('aiden_pollinations_api_key_field', 'Pollinations.ai API Key', 'aipc_render_api_key_field', 'aiden-settings-general', 'aiden_general_section');
    add_settings_field('aiden_github_repo_field', 'GitHub Repository for Updates', 'aipc_render_github_repo_field', 'aiden-settings-general', 'aiden_general_section');

    // Trigger Settings
    register_setting('aiden_trigger_settings', 'aiden_trigger_keyword', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => 'Jules']);
    add_settings_section('aiden_trigger_section', 'Keyword Trigger Settings', 'aipc_render_trigger_section_callback', 'aiden-settings-triggers');
    add_settings_field('aiden_trigger_keyword_field', 'Trigger Keyword', 'aipc_render_trigger_keyword_field', 'aiden-settings-triggers', 'aiden_trigger_section');
}
add_action( 'admin_init', 'aipc_register_settings' );

/**
 * ===================================================================
 * CPT and Taxonomy Registration
 * ===================================================================
 */
function aipc_register_post_types_and_taxonomies() {
    // Register the 'ai_persona' custom post type.
    $persona_labels = array('name' => 'Personas', 'singular_name' => 'Persona', 'menu_name' => 'Personas');
    $persona_args = array('labels' => $persona_labels, 'public' => true, 'show_ui' => true, 'show_in_menu' => 'aiden-settings', 'supports' => ['title', 'editor', 'thumbnail', 'custom-fields'], 'show_in_rest' => true);
    register_post_type( 'ai_persona', $persona_args );

    // Register the 'aiden_agent' custom post type.
    $agent_labels = array('name' => 'Agents', 'singular_name' => 'Agent', 'menu_name' => 'Agents');
    $agent_args = array('labels' => $agent_labels, 'public' => false, 'show_ui' => true, 'show_in_menu' => 'aiden-settings', 'menu_icon' => 'dashicons-groups', 'supports' => ['title', 'editor', 'thumbnail', 'custom-fields'], 'show_in_rest' => true);
    register_post_type( 'aiden_agent', $agent_args );

    // Register custom taxonomies for the 'ai_persona' post type.
    $trait_labels = array('name' => 'Traits', 'singular_name' => 'Trait');
    $trait_args = array('hierarchical' => false, 'labels' => $trait_labels, 'show_ui' => true, 'show_admin_column' => true, 'show_in_rest' => true);
    register_taxonomy( 'persona_trait', 'ai_persona', $trait_args );

    $interest_labels = array('name' => 'Interests', 'singular_name' => 'Interest');
    $interest_args = array('hierarchical' => true, 'labels' => $interest_labels, 'show_ui' => true, 'show_admin_column' => true, 'show_in_rest' => true);
    register_taxonomy( 'persona_interest', 'ai_persona', $interest_args );
}
add_action( 'init', 'aipc_register_post_types_and_taxonomies' );

/**
 * ===================================================================
 * Admin Menu and Page Rendering
 * ===================================================================
 */
function aipc_add_admin_menu() {
    add_menu_page('AiDen Suite', 'AiDen', 'manage_options', 'aiden-settings', 'aipc_render_settings_page', 'dashicons-brain', 25);
    add_submenu_page('aiden-settings', 'Dashboard', 'Dashboard', 'manage_options', 'aiden-settings', 'aipc_render_settings_page');
}
add_action( 'admin_menu', 'aipc_add_admin_menu' );

function aipc_render_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'AiDen Suite', 'ai-persona-core' ); ?></h1>
        <?php
        if ( isset( $_GET['setup_status'] ) && $_GET['setup_status'] == 'success' ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Rich demo content has been successfully created!', 'ai-persona-core' ) . '</p></div>';
        }
        ?>
        <p><?php esc_html_e( 'This is the control panel for your autonomous AI agent ecosystem.', 'ai-persona-core' ); ?></p>
        <?php
        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'dashboard';
        ?>
        <h2 class="nav-tab-wrapper">
            <a href="?page=aiden-settings&tab=dashboard" class="nav-tab <?php echo $active_tab == 'dashboard' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Dashboard', 'ai-persona-core' ); ?></a>
            <a href="?page=aiden-settings&tab=general_settings" class="nav-tab <?php echo $active_tab == 'general_settings' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'General Settings', 'ai-persona-core' ); ?></a>
            <a href="?page=aiden-settings&tab=triggers" class="nav-tab <?php echo $active_tab == 'triggers' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Triggers & Reactions', 'ai-persona-core' ); ?></a>
        </h2>
        <?php
        if ( $active_tab == 'dashboard' ) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'ai_reaction_queue';
            $num_agents = wp_count_posts('aiden_agent')->publish;
            $num_personas = wp_count_posts('ai_persona')->publish;
            $num_pending = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} WHERE status = %s", 'pending' ) );
            echo '<h3>' . esc_html__( 'At a Glance', 'ai-persona-core' ) . '</h3>';
            echo '<div style="display: flex; gap: 20px; margin-bottom: 20px; padding: 10px; background: #fff; border: 1px solid #c3c4c7;">';
            echo '<div><h2>' . (int)$num_agents . '</h2>' . esc_html__( 'Agents', 'ai-persona-core' ) . '</div>';
            echo '<div style="margin-left: 20px; padding-left: 20px; border-left: 1px solid #ddd;"><h2>' . (int)$num_personas . '</h2>' . esc_html__( 'Personas', 'ai-persona-core' ) . '</div>';
            echo '<div style="margin-left: 20px; padding-left: 20px; border-left: 1px solid #ddd;"><h2>' . (int)$num_pending . '</h2>' . esc_html__( 'Pending Reactions', 'ai-persona-core' ) . '</div>';
            echo '</div>';
            if ( ! get_transient( 'trm_demo_data_setup_complete' ) ) {
                echo '<div><h3>' . esc_html__( 'Get Started: Setup Demo Content', 'ai-persona-core' ) . '</h3>';
                echo '<p>' . esc_html__( 'To see AiDen in action, you can create a rich set of sample users and personas. This will only run once.', 'ai-persona-core' ) . '</p>';
                echo '<form method="post"><input type="hidden" name="aiden_action" value="setup_demo_data" />';
                wp_nonce_field( 'aiden_setup_demo_data_nonce' );
                submit_button( 'Create Demo Content', 'primary', 'aiden_setup_submit', false );
                echo '</form></div>';
            }
            // Display reaction tables...
        } else {
            echo '<form action="options.php" method="post">';
            if ( $active_tab == 'general_settings' ) {
                settings_fields( 'aiden_general_settings' );
                do_settings_sections( 'aiden-settings-general' );
            } elseif ( $active_tab == 'triggers' ) {
                settings_fields( 'aiden_trigger_settings' );
                do_settings_sections( 'aiden-settings-triggers' );
            }
            submit_button( 'Save Settings' );
            echo '</form>';
        }
        ?>
    </div>
    <?php
}

function aipc_render_general_section_callback() { echo '<p>' . esc_html__( 'These are the main settings for the AiDen suite.', 'ai-persona-core' ) . '</p>'; }
function aipc_render_leader_select_field() {
    $current_leader_id = get_option( 'ai_group_leader_user_id', 0 );
    $all_agents = get_posts(['post_type' => 'aiden_agent', 'posts_per_page' => -1, 'post_status' => 'publish']);
    echo '<select name="ai_group_leader_user_id" id="ai_group_leader_user_id" class="postform">';
    echo '<option value="0">' . esc_html__( '— None —', 'ai-persona-core' ) . '</option>';
    if ( ! empty( $all_agents ) ) {
        foreach ( $all_agents as $agent ) {
            echo '<option value="' . esc_attr( $agent->ID ) . '" ' . selected( $current_leader_id, $agent->ID, false ) . '>' . esc_html( $agent->post_title ) . '</option>';
        }
    }
    echo '</select>';
}
function aipc_render_api_key_field() {
    $api_key = get_option( 'aiden_pollinations_api_key', '' );
    echo '<input type="password" name="aiden_pollinations_api_key" value="' . esc_attr( $api_key ) . '" class="regular-text" />';
}
function aipc_render_github_repo_field() {
    $repo_slug = get_option( 'aiden_github_repo', '' );
    echo '<input type="text" name="aiden_github_repo" value="' . esc_attr( $repo_slug ) . '" class="regular-text" placeholder="owner/repository" />';
}
function aipc_render_trigger_section_callback() { echo '<p>' . esc_html__( 'Configure reaction triggers.', 'ai-persona-core' ) . '</p>'; }
function aipc_render_trigger_keyword_field() {
    $keyword = get_option( 'aiden_trigger_keyword', 'Jules' );
    echo '<input type="text" name="aiden_trigger_keyword" value="' . esc_attr( $keyword ) . '" class="regular-text" />';
}

/**
 * ===================================================================
 * Meta Box Registration and Rendering
 * ===================================================================
 */
function aipc_add_agent_meta_boxes() {
    add_meta_box('aiden_persona_assignment_meta_box', 'Assigned Personas', 'aipc_render_persona_assignment_meta_box', 'aiden_agent', 'side', 'high');
    add_meta_box('aiden_agent_avatar_meta_box', 'Agent Avatar', 'aipc_render_agent_avatar_meta_box', 'aiden_agent', 'side', 'default');
}
add_action( 'add_meta_boxes', 'aipc_add_agent_meta_boxes' );

function aipc_render_persona_assignment_meta_box( $post ) {
    wp_nonce_field( 'aiden_agent_persona_save', 'aiden_agent_persona_nonce' );
    $all_personas = get_posts(['post_type' => 'ai_persona', 'posts_per_page' => -1, 'post_status' => 'publish']);
    if ( empty( $all_personas ) ) {
        echo '<p>' . esc_html__( 'No personas found.', 'ai-persona-core' ) . '</p>';
        return;
    }
    $assigned_persona_ids = get_post_meta( $post->ID, '_assigned_personas', true );
    if ( ! is_array( $assigned_persona_ids ) ) {
        $assigned_persona_ids = array();
    }
    echo '<div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 5px;">';
    foreach ( $all_personas as $persona ) {
        echo '<label style="display: block;"><input type="checkbox" name="assigned_personas[]" value="' . esc_attr( $persona->ID ) . '" ' . checked( in_array( $persona->ID, $assigned_persona_ids ), true, false ) . ' /> ' . esc_html( $persona->post_title ) . '</label>';
    }
    echo '</div>';
}

function aipc_save_agent_meta_box_data( $post_id ) {
    if ( ! isset( $_POST['aiden_agent_persona_nonce'] ) || ! wp_verify_nonce( $_POST['aiden_agent_persona_nonce'], 'aiden_agent_persona_save' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    if ( isset( $_POST['assigned_personas'] ) && is_array( $_POST['assigned_personas'] ) ) {
        $sanitized_personas = array_map( 'absint', $_POST['assigned_personas'] );
        update_post_meta( $post_id, '_assigned_personas', $sanitized_personas );
    } else {
        delete_post_meta( $post_id, '_assigned_personas' );
    }
}
add_action( 'save_post_aiden_agent', 'aipc_save_agent_meta_box_data' );

/**
 * Render the HTML content for the agent avatar meta box.
 *
 * @param WP_Post $post The post object for the current agent.
 */
function aipc_render_agent_avatar_meta_box( $post ) {
    echo '<div style="text-align: center; margin-bottom: 10px;">';
    if ( has_post_thumbnail( $post->ID ) ) {
        the_post_thumbnail( $post->ID, 'medium' );
    } else {
        echo '<p>' . esc_html__( 'No avatar has been set for this agent.', 'ai-persona-core' ) . '</p>';
    }
    echo '</div>';

    // The form submits to admin-post.php, a standard way to handle custom actions.
    echo '<form action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" method="post">';
    wp_nonce_field( 'aiden_generate_avatar_' . $post->ID, 'aiden_generate_avatar_nonce' );
    echo '<input type="hidden" name="action" value="aiden_generate_avatar">';
    echo '<input type="hidden" name="agent_id" value="' . esc_attr( $post->ID ) . '">';
    submit_button( 'Generate New Avatar', 'primary', 'generate_avatar', false, array( 'style' => 'width: 100%;' ) );
    echo '</form>';
    echo '<p class="description">' . esc_html__( 'Generates a new avatar using the Pollinations.ai API based on the agent\'s profile.', 'ai-persona-core' ) . '</p>';
}

// This is a simplified reconstruction. The full file was much larger.
// I will need to fill in the details for each of these functions.
// For now, this creates the file and the basic structure.

// To save space and avoid tool errors, I've used placeholders for the full function bodies.
// I will flesh these out in subsequent steps. This is just to recover the file.

/**
 * ===================================================================
 * Admin Notices
 * ===================================================================
 */

/**
 * Display admin notices for avatar generation status on the agent edit screen.
 */
function aipc_display_avatar_generation_notices() {
    $screen = get_current_screen();
    if ( ! $screen || $screen->id !== 'aiden_agent' || ! isset( $_GET['avatar_status'] ) ) {
        return;
    }

    $status = sanitize_key( $_GET['avatar_status'] );
    $message = '';
    $type = 'info';

    switch ( $status ) {
        case 'success':
            $message = __( 'New agent avatar generated and set successfully!', 'ai-persona-core' );
            $type = 'success';
            break;
        case 'no_key':
            $message = __( 'Error: Pollinations.ai API key is not set. Please set it in the AiDen General Settings.', 'ai-persona-core' );
            $type = 'error';
            break;
        case 'api_error':
            $message = __( 'Error: Could not connect to the Pollinations.ai API. Please try again later.', 'ai-persona-core' );
            $type = 'error';
            break;
        case 'url_error':
            $message = __( 'Error: The API response did not contain a valid image URL.', 'ai-persona-core' );
            $type = 'error';
            break;
        case 'download_error':
            $message = __( 'Error: Could not download the generated image. Please check server permissions.', 'ai-persona-core' );
            $type = 'error';
            break;
    }

    if ( ! empty( $message ) ) {
        echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
    }
}
add_action( 'admin_notices', 'aipc_display_avatar_generation_notices' );
?>
