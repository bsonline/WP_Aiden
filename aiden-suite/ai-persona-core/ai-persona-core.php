<?php
/**
 * Plugin Name:       AI Persona Core
 * Description:       The brain. Manages agents, personas, memory, and settings.
 * Version:           1.0.0
 * Author:            Jules
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * ===================================================================
 * Settings API Registration
 * ===================================================================
 */
function aipc_register_settings() {
    register_setting('aiden_general_settings', 'ai_group_leader_user_id', ['type' => 'integer', 'sanitize_callback' => 'absint', 'default' => 0]);
    register_setting('aiden_general_settings', 'aiden_pollinations_api_key', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '']);
    register_setting('aiden_general_settings', 'aiden_github_repo', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '']);
    add_settings_section('aiden_general_section', 'Core Settings', 'aipc_render_general_section_callback', 'aiden-settings-general');
    add_settings_field('ai_group_leader_user_id_field', 'AI Group Leader', 'aipc_render_leader_select_field', 'aiden-settings-general', 'aiden_general_section');
    add_settings_field('aiden_pollinations_api_key_field', 'Pollinations.ai API Key', 'aipc_render_api_key_field', 'aiden-settings-general', 'aiden_general_section');
    add_settings_field('aiden_github_repo_field', 'GitHub Repository for Updates', 'aipc_render_github_repo_field', 'aiden-settings-general', 'aiden_general_section');
    register_setting('aiden_trigger_settings', 'aiden_trigger_keyword', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => 'Jules']);
    add_settings_section('aiden_trigger_section', 'Keyword Trigger Settings', 'aipc_render_trigger_section_callback', 'aiden-settings-triggers');
    add_settings_field('aiden_trigger_keyword_field', 'Trigger Keyword', 'aipc_render_trigger_keyword_field', 'aiden-settings-triggers', 'aiden_trigger_section');

    // Register setting for enabling scheduled posting
    register_setting(
        'aiden_trigger_settings',
        'aiden_enable_scheduled_posting',
        array(
            'type'              => 'string', // Stored as 'on' or empty string.
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        )
    );

    add_settings_field(
        'aiden_enable_scheduled_posting_field',
        __( 'Scheduled Posting', 'ai-persona-core' ),
        'aipc_render_scheduled_posting_field',
        'aiden-settings-triggers',
        'aiden_trigger_section'
    );
}
add_action( 'admin_init', 'aipc_register_settings' );

/**
 * ===================================================================
 * CPT and Taxonomy Registration
 * ===================================================================
 */
function aipc_register_post_types_and_taxonomies() {
    $persona_labels = array('name' => 'Personas', 'singular_name' => 'Persona', 'menu_name' => 'Personas', 'add_new_item' => 'Add New Persona', 'edit_item' => 'Edit Persona', 'new_item' => 'New Persona');
    $persona_args = array('labels' => $persona_labels, 'public' => true, 'show_ui' => true, 'show_in_menu' => false, 'supports' => ['title', 'editor', 'thumbnail', 'custom-fields'], 'show_in_rest' => true);
    register_post_type( 'ai_persona', $persona_args );
    $agent_labels = array('name' => 'Agents', 'singular_name' => 'Agent', 'menu_name' => 'Agents', 'add_new_item' => 'Add New Agent', 'edit_item' => 'Edit Agent', 'new_item' => 'New Agent');
    $agent_args = array('labels' => $agent_labels, 'public' => false, 'show_ui' => true, 'show_in_menu' => false, 'menu_icon' => 'dashicons-groups', 'supports' => ['title', 'editor', 'thumbnail', 'custom-fields'], 'show_in_rest' => true);
    register_post_type( 'aiden_agent', $agent_args );
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
    add_menu_page('AiDen', 'AiDen', 'manage_options', 'aiden_dashboard', 'aipc_render_dashboard_page', 'dashicons-brain', 25);
    add_submenu_page('aiden_dashboard', 'Dashboard', 'Dashboard', 'manage_options', 'aiden_dashboard', 'aipc_render_dashboard_page');
    add_submenu_page('aiden_dashboard', 'Agents', 'Agents', 'manage_options', 'edit.php?post_type=aiden_agent');
    add_submenu_page('aiden_dashboard', 'Personas', 'Personas', 'manage_options', 'edit.php?post_type=ai_persona');
    add_submenu_page('aiden_dashboard', 'Settings', 'Settings', 'manage_options', 'aiden_settings', 'aipc_render_settings_page');
}
add_action( 'admin_menu', 'aipc_add_admin_menu' );

function aipc_render_dashboard_page() {
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'AiDen Dashboard', 'ai-persona-core' ); ?></h1>
        <?php if ( isset( $_GET['setup_status'] ) && $_GET['setup_status'] == 'success' ) { echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Rich demo content has been successfully created!', 'ai-persona-core' ) . '</p></div>'; } ?>
        <p><?php esc_html_e( 'This is the control panel for your autonomous AI agent ecosystem.', 'ai-persona-core' ); ?></p>

        <?php
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_reaction_queue';
        $num_agents = wp_count_posts('aiden_agent')->publish;
        $num_personas = wp_count_posts('ai_persona')->publish;
        $num_pending = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} WHERE status = %s", 'pending' ) );
        ?>

        <h3><?php esc_html_e( 'At a Glance', 'ai-persona-core' ); ?></h3>
        <div style="display: flex; gap: 20px; margin-bottom: 20px; padding: 10px; background: #fff; border: 1px solid #c3c4c7;">
            <div style="text-align: center;"><h2><?php echo (int)$num_agents; ?></h2><?php esc_html_e( 'Agents', 'ai-persona-core' ); ?></div>
            <div style="margin-left: 20px; padding-left: 20px; border-left: 1px solid #ddd; text-align: center;"><h2><?php echo (int)$num_personas; ?></h2><?php esc_html_e( 'Personas', 'ai-persona-core' ); ?></div>
            <div style="margin-left: 20px; padding-left: 20px; border-left: 1px solid #ddd; text-align: center;"><h2><?php echo (int)$num_pending; ?></h2><?php esc_html_e( 'Pending Reactions', 'ai-persona-core' ); ?></div>
        </div>

        <?php if ( ! get_transient( 'trm_demo_data_setup_complete' ) ) : ?>
            <div>
                <h3><?php esc_html_e( 'Get Started: Setup Demo Content', 'ai-persona-core' ); ?></h3>
                <p><?php esc_html_e( 'To see AiDen in action, you can create a rich set of sample users and personas. This will only run once.', 'ai-persona-core' ); ?></p>
                <form method="post">
                    <input type="hidden" name="aiden_action" value="setup_demo_data" />
                    <?php wp_nonce_field( 'aiden_setup_demo_data_nonce' ); ?>
                    <?php submit_button( 'Create Demo Content', 'primary', 'aiden_setup_submit', false ); ?>
                </form>
            </div>
        <?php endif; ?>

    </div>
    <?php
}

function aipc_render_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'AiDen Settings', 'ai-persona-core' ); ?></h1>
        <?php $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general'; ?>
        <h2 class="nav-tab-wrapper">
            <a href="?page=aiden_settings&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'General', 'ai-persona-core' ); ?></a>
            <a href="?page=aiden_settings&tab=triggers" class="nav-tab <?php echo $active_tab == 'triggers' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Triggers & Reactions', 'ai-persona-core' ); ?></a>
        </h2>
        <form action="options.php" method="post">
            <?php
            if ( $active_tab == 'general' ) {
                settings_fields( 'aiden_general_settings' );
                do_settings_sections( 'aiden-settings-general' );
            } else { // 'triggers'
                settings_fields( 'aiden_trigger_settings' );
                do_settings_sections( 'aiden-settings-triggers' );
            }
            submit_button( 'Save Settings' );
            ?>
        </form>
    </div>
    <?php
}

function aipc_render_general_section_callback() { echo '<p>' . esc_html__( 'These are the main settings for the AiDen suite. Designating a Group Leader is required to enable autonomous agent posting.', 'ai-persona-core' ) . '</p>'; }
function aipc_render_leader_select_field() {
    $current_leader_id = get_option( 'ai_group_leader_user_id', 0 );
    $all_agents = get_posts( array( 'post_type' => 'aiden_agent', 'posts_per_page' => -1, 'post_status' => 'publish' ) );
    ?>
    <select name="ai_group_leader_user_id" id="ai_group_leader_user_id" class="postform"><option value="0" <?php selected( $current_leader_id, 0 ); ?>><?php esc_html_e( '— None —', 'ai-persona-core' ); ?></option>
        <?php if ( ! empty( $all_agents ) ) { foreach ( $all_agents as $agent ) { echo '<option value="' . esc_attr( $agent->ID ) . '" ' . selected( $current_leader_id, $agent->ID, false ) . '>' . esc_html( $agent->post_title ) . '</option>'; } } ?>
    </select>
    <?php
}
function aipc_render_api_key_field() { echo '<input type="password" name="aiden_pollinations_api_key" value="' . esc_attr( get_option( 'aiden_pollinations_api_key', '' ) ) . '" class="regular-text" />'; }
function aipc_render_github_repo_field() { echo '<input type="text" name="aiden_github_repo" value="' . esc_attr( get_option( 'aiden_github_repo', '' ) ) . '" class="regular-text" placeholder="owner/repository" />'; }
function aipc_render_trigger_section_callback() { echo '<p>' . esc_html__( 'Configure reaction triggers.', 'ai-persona-core' ) . '</p>'; }
function aipc_render_trigger_keyword_field() { echo '<input type="text" name="aiden_trigger_keyword" value="' . esc_attr( get_option( 'aiden_trigger_keyword', 'Jules' ) ) . '" class="regular-text" />'; }

function aipc_render_scheduled_posting_field() {
    $enabled = get_option( 'aiden_enable_scheduled_posting' );
    ?>
    <label for="aiden_enable_scheduled_posting">
        <input type="checkbox" name="aiden_enable_scheduled_posting" id="aiden_enable_scheduled_posting" value="1" <?php checked( $enabled, 1 ); ?> />
        <?php esc_html_e( 'Allow AI agents to proactively create new blog posts on a recurring schedule.', 'ai-persona-core' ); ?>
    </label>
    <?php
}

/**
 * ===================================================================
 * Meta Box Registration and Rendering
 * ===================================================================
 */
function aipc_add_agent_meta_boxes() {
    add_meta_box('aiden_persona_assignment_meta_box', 'Assigned Personas', 'aipc_render_persona_assignment_meta_box', 'aiden_agent', 'side', 'high');
    add_meta_box('aiden_avatar_generation_meta_box', __( 'Generate Avatar', 'ai-persona-core' ), 'aipc_render_avatar_generation_meta_box', 'aiden_agent', 'side', 'default');
    add_meta_box('aiden_manual_post_generation_meta_box', __( 'Generate Post', 'ai-persona-core' ), 'aipc_render_manual_post_generation_meta_box', 'aiden_agent', 'side', 'default');
}
add_action( 'add_meta_boxes', 'aipc_add_agent_meta_boxes' );

function aipc_render_persona_assignment_meta_box( $post ) {
    wp_nonce_field( 'aiden_agent_persona_save', 'aiden_agent_persona_nonce' );
    $all_personas = get_posts(['post_type' => 'ai_persona', 'posts_per_page' => -1, 'post_status' => 'publish']);
    if ( empty( $all_personas ) ) { echo '<p>' . esc_html__( 'No personas found.', 'ai-persona-core' ) . '</p>'; return; }
    $assigned_persona_ids = get_post_meta( $post->ID, '_assigned_personas', true );
    if ( ! is_array( $assigned_persona_ids ) ) { $assigned_persona_ids = array(); }
    echo '<div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 5px;">';
    foreach ( $all_personas as $persona ) { echo '<label style="display: block;"><input type="checkbox" name="assigned_personas[]" value="' . esc_attr( $persona->ID ) . '" ' . checked( in_array( $persona->ID, $assigned_persona_ids ), true, false ) . ' /> ' . esc_html( $persona->post_title ) . '</label>'; }
    echo '</div>';
}

function aipc_render_avatar_generation_meta_box( $post ) {
    $api_key = get_option( 'aiden_pollinations_api_key' );
    ?>
    <form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
        <input type="hidden" name="action" value="aiden_generate_avatar">
        <input type="hidden" name="agent_id" value="<?php echo esc_attr( $post->ID ); ?>">
        <?php wp_nonce_field( 'aiden_generate_avatar_' . $post->ID, 'aiden_generate_avatar_nonce' ); ?>

        <?php if ( empty( $api_key ) ) : ?>
            <p><?php esc_html_e( 'Please add your Pollinations.ai API key in the', 'ai-persona-core' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=aiden-settings&tab=general_settings' ) ); ?>"><?php esc_html_e( 'General Settings', 'ai-persona-core' ); ?></a> <?php esc_html_e( 'to enable this feature.', 'ai-persona-core' ); ?></p>
            <?php submit_button( __( 'Generate New Avatar', 'ai-persona-core' ), 'primary', 'submit', true, [ 'disabled' => 'disabled' ] ); ?>
        <?php else : ?>
            <p><?php esc_html_e( 'Uses the agent\'s name, description, and assigned persona traits/interests to generate a unique avatar via Pollinations.ai.', 'ai-persona-core' ); ?></p>
            <?php submit_button( __( 'Generate New Avatar', 'ai-persona-core' ), 'primary', 'submit', true ); ?>
        <?php endif; ?>
    </form>
    <?php
}

function aipc_render_manual_post_generation_meta_box( $post ) {
    $api_key = get_option( 'aiden_pollinations_api_key' );
    ?>
    <form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
        <input type="hidden" name="action" value="aiden_generate_post">
        <input type="hidden" name="agent_id" value="<?php echo esc_attr( $post->ID ); ?>">
        <?php wp_nonce_field( 'aiden_generate_post_' . $post->ID, 'aiden_generate_post_nonce' ); ?>

        <?php if ( empty( $api_key ) ) : ?>
            <p><?php esc_html_e( 'Please add your Pollinations.ai API key in the Settings to enable this feature.', 'ai-persona-core' ); ?></p>
            <?php submit_button( __( 'Generate New Post Now', 'ai-persona-core' ), 'primary', 'submit', true, [ 'disabled' => 'disabled' ] ); ?>
        <?php else : ?>
            <p><?php esc_html_e( 'Click to have this agent immediately generate and publish a new blog post based on its persona.', 'ai-persona-core' ); ?></p>
            <?php submit_button( __( 'Generate New Post Now', 'ai-persona-core' ), 'primary', 'submit', true ); ?>
        <?php endif; ?>
    </form>
    <?php
}

function aipc_save_agent_meta_box_data( $post_id ) {
    if ( ! isset( $_POST['aiden_agent_persona_nonce'] ) || ! wp_verify_nonce( $_POST['aiden_agent_persona_nonce'], 'aiden_agent_persona_save' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;
    if ( isset( $_POST['assigned_personas'] ) && is_array( $_POST['assigned_personas'] ) ) {
        update_post_meta( $post_id, '_assigned_personas', array_map( 'absint', $_POST['assigned_personas'] ) );
    } else {
        delete_post_meta( $post_id, '_assigned_personas' );
    }
}
add_action( 'save_post_aiden_agent', 'aipc_save_agent_meta_box_data' );

/**
 * ===================================================================
 * Admin Notices
 * ===================================================================
 */
function aipc_display_avatar_generation_notices() {
    $screen = get_current_screen();
    if ( ! $screen || $screen->id !== 'aiden_agent' || ! isset( $_GET['avatar_status'] ) ) return;
    $status = sanitize_key( $_GET['avatar_status'] );
    $message = ''; $type = 'info';
    switch ( $status ) {
        case 'success': $message = __( 'New agent avatar generated and set successfully!', 'ai-persona-core' ); $type = 'success'; break;
        case 'no_key': $message = __( 'Error: Pollinations.ai API key is not set.', 'ai-persona-core' ); $type = 'error'; break;
        case 'api_error': $message = __( 'Error: Could not connect to the Pollinations.ai API.', 'ai-persona-core' ); $type = 'error'; break;
        case 'url_error': $message = __( 'Error: The API response did not contain a valid image URL.', 'ai-persona-core' ); $type = 'error'; break;
        case 'download_error': $message = __( 'Error: Could not download the generated image.', 'ai-persona-core' ); $type = 'error'; break;
    }
    if ( ! empty( $message ) ) { echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>'; }
}
add_action( 'admin_notices', 'aipc_display_avatar_generation_notices' );

function aipc_display_post_generation_notices() {
    $screen = get_current_screen();
    if ( ! $screen || $screen->id !== 'aiden_agent' || ! isset( $_GET['post_status'] ) ) return;
    $status = sanitize_key( $_GET['post_status'] );
    $message = ''; $type = 'info';
    switch ( $status ) {
        case 'success': $message = __( 'New post generated and published successfully!', 'ai-persona-core' ); $type = 'success'; break;
        case 'no_key': $message = __( 'Error: Pollinations.ai API key is not set.', 'ai-persona-core' ); $type = 'error'; break;
        case 'api_error': $message = __( 'Error: Could not connect to the API to generate post.', 'ai-persona-core' ); $type = 'error'; break;
        case 'content_error': $message = __( 'Error: The API response did not contain valid content.', 'ai-persona-core' ); $type = 'error'; break;
        case 'publish_error': $message = __( 'Error: The generated post could not be published.', 'ai-persona-core' ); $type = 'error'; break;
    }
    if ( ! empty( $message ) ) { echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>'; }
}
add_action( 'admin_notices', 'aipc_display_post_generation_notices' );
?>
