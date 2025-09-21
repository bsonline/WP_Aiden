<?php
/**
 * Plugin Name:       AI Persona Core
 * Plugin URI:        https://example.com/plugins/the-basics/
 * Description:       The brain. Manages personas, memory, personality traits, and state.
 * Version:           0.1.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Jules
 * Author URI:        https://example.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ai-persona-core
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Register settings and fields for the AiDen settings page.
 */
function aipc_register_settings() {
    // Register the setting for the AI Group Leader
    register_setting(
        'aiden_general_settings',
        'ai_group_leader_user_id',
        array(
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 0,
        )
    );

    // Add a section to the 'General' tab
    add_settings_section(
        'aiden_general_section',
        __( 'Core Settings', 'ai-persona-core' ),
        'aipc_render_general_section_callback',
        'aiden-settings-general'
    );

    // Add the field for selecting the Group Leader
    add_settings_field(
        'ai_group_leader_user_id_field',
        __( 'AI Group Leader', 'ai-persona-core' ),
        'aipc_render_leader_select_field',
        'aiden-settings-general',
        'aiden_general_section'
    );

    // Register the setting for the Pollinations API Key
    register_setting(
        'aiden_general_settings',
        'aiden_pollinations_api_key',
        array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        )
    );

    // Add the field for the API Key
    add_settings_field(
        'aiden_pollinations_api_key_field',
        __( 'Pollinations.ai API Key', 'ai-persona-core' ),
        'aipc_render_api_key_field',
        'aiden-settings-general',
        'aiden_general_section'
    );

    // Register the setting for the GitHub repository slug
    register_setting(
        'aiden_general_settings',
        'aiden_github_repo',
        array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        )
    );

    // Add the field for the GitHub repo
    add_settings_field(
        'aiden_github_repo_field',
        __( 'GitHub Repository for Updates', 'ai-persona-core' ),
        'aipc_render_github_repo_field',
        'aiden-settings-general',
        'aiden_general_section'
    );

    // Register settings for the 'Triggers' tab
    register_setting(
        'aiden_trigger_settings',
        'aiden_trigger_keyword',
        array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'Jules',
        )
    );

    add_settings_section(
        'aiden_trigger_section',
        __( 'Keyword Trigger Settings', 'ai-persona-core' ),
        'aipc_render_trigger_section_callback',
        'aiden-settings-triggers'
    );

    add_settings_field(
        'aiden_trigger_keyword_field',
        __( 'Trigger Keyword', 'ai-persona-core' ),
        'aipc_render_trigger_keyword_field',
        'aiden-settings-triggers',
        'aiden_trigger_section'
    );
}
add_action( 'admin_init', 'aipc_register_settings' );

/**
 * Callback function to render the introductory text for the general settings section.
 */
function aipc_render_general_section_callback() {
    echo '<p>' . esc_html__( 'These are the main settings for the AiDen suite. Designating a Group Leader is required to enable autonomous agent posting.', 'ai-persona-core' ) . '</p>';
}

/**
 * Callback function to render the dropdown select field for the AI Group Leader.
 */
function aipc_render_leader_select_field() {
    $current_leader_id = get_option( 'ai_group_leader_user_id', 0 );
    $all_agents = get_posts( array(
        'post_type'      => 'aiden_agent',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
    ) );
    ?>
    <select name="ai_group_leader_user_id" id="ai_group_leader_user_id" class="postform">
        <option value="0" <?php selected( $current_leader_id, 0 ); ?>><?php esc_html_e( '— None (Autonomous Posting Disabled) —', 'ai-persona-core' ); ?></option>
        <?php if ( ! empty( $all_agents ) ) : ?>
            <?php foreach ( $all_agents as $agent ) : ?>
                <option value="<?php echo esc_attr( $agent->ID ); ?>" <?php selected( $current_leader_id, $agent->ID ); ?>>
                    <?php echo esc_html( $agent->post_title ); ?>
                </option>
            <?php endforeach; ?>
        <?php endif; ?>
    </select>
    <p class="description">
        <?php esc_html_e( 'Select the Agent that will act as the AI Group Leader. The leader arbitrates which agent gets to respond to a trigger.', 'ai-persona-core' ); ?>
    </p>
    <?php
}

/**
 * Callback function to render the introductory text for the trigger settings section.
 */
function aipc_render_trigger_section_callback() {
    echo '<p>' . esc_html__( 'Configure the conditions that trigger an AI agent reaction.', 'ai-persona-core' ) . '</p>';
}

/**
 * Callback function to render the text input field for the trigger keyword.
 */
function aipc_render_trigger_keyword_field() {
    $keyword = get_option( 'aiden_trigger_keyword', 'Jules' );
    ?>
    <input type="text" name="aiden_trigger_keyword" id="aiden_trigger_keyword" value="<?php echo esc_attr( $keyword ); ?>" class="regular-text" />
    <p class="description">
        <?php esc_html_e( 'The primary keyword that agents will look for in post content to trigger a reaction.', 'ai-persona-core' ); ?>
    </p>
    <?php
}

/**
 * Callback function to render the password input field for the Pollinations.ai API Key.
 */
function aipc_render_api_key_field() {
    $api_key = get_option( 'aiden_pollinations_api_key', '' );
    ?>
    <input type="password" name="aiden_pollinations_api_key" id="aiden_pollinations_api_key" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text" />
    <p class="description">
        <?php esc_html_e( 'Enter your API key for Pollinations.ai to enable AI image generation for agents.', 'ai-persona-core' ); ?>
        <a href="https://pollinations.ai/" target="_blank"><?php esc_html_e( 'Find your key here.', 'ai-persona-core' ); ?></a>
    </p>
    <?php
}

/**
 * Callback function to render the text input field for the GitHub repository slug.
 */
function aipc_render_github_repo_field() {
    $repo_slug = get_option( 'aiden_github_repo', '' );
    ?>
    <input type="text" name="aiden_github_repo" id="aiden_github_repo" value="<?php echo esc_attr( $repo_slug ); ?>" class="regular-text" placeholder="owner/repository" />
    <p class="description">
        <?php esc_html_e( 'Enter the public GitHub repository slug to enable automatic updates (e.g., "YourUsername/aiden-suite").', 'ai-persona-core' ); ?>
    </p>
    <?php
}

/**
 * ===================================================================
 * Agent to Persona Linking
 * ===================================================================
 */

/**
 * Register the meta box for assigning personas to an agent.
 */
function aipc_add_agent_meta_boxes() {
    add_meta_box(
        'aiden_persona_assignment_meta_box',
        __( 'Assigned Personas', 'ai-persona-core' ),
        'aipc_render_persona_assignment_meta_box',
        'aiden_agent',
        'side',
        'high'
    );
}
add_action( 'add_meta_boxes', 'aipc_add_agent_meta_boxes' );

/**
 * Render the HTML content for the persona assignment meta box.
 *
 * @param WP_Post $post The post object for the current agent.
 */
function aipc_render_persona_assignment_meta_box( $post ) {
    wp_nonce_field( 'aiden_agent_persona_save', 'aiden_agent_persona_nonce' );

    $all_personas = get_posts( array(
        'post_type'      => 'ai_persona',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'orderby'        => 'title',
        'order'          => 'ASC',
    ) );

    if ( empty( $all_personas ) ) {
        echo '<p>' . esc_html__( 'No personas found. Please create some first.', 'ai-persona-core' ) . '</p>';
        return;
    }

    $assigned_persona_ids = get_post_meta( $post->ID, '_assigned_personas', true );
    if ( ! is_array( $assigned_persona_ids ) ) {
        $assigned_persona_ids = array();
    }

    echo '<div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 5px;">';
    foreach ( $all_personas as $persona ) {
        ?>
        <label for="persona-<?php echo esc_attr( $persona->ID ); ?>" style="display: block;">
            <input
                type="checkbox"
                name="assigned_personas[]"
                id="persona-<?php echo esc_attr( $persona->ID ); ?>"
                value="<?php echo esc_attr( $persona->ID ); ?>"
                <?php checked( in_array( $persona->ID, $assigned_persona_ids ) ); ?>
            />
            <?php echo esc_html( $persona->post_title ); ?>
        </label>
        <?php
    }
    echo '</div>';
    echo '<p class="description">' . esc_html__( 'Select one or more personas for this agent.', 'ai-persona-core' ) . '</p>';
}

/**
 * Save the persona assignment data from the meta box.
 *
 * @param int $post_id The ID of the post being saved.
 */
function aipc_save_agent_meta_box_data( $post_id ) {
    if ( ! isset( $_POST['aiden_agent_persona_nonce'] ) || ! wp_verify_nonce( $_POST['aiden_agent_persona_nonce'], 'aiden_agent_persona_save' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    if ( isset( $_POST['assigned_personas'] ) && is_array( $_POST['assigned_personas'] ) ) {
        $sanitized_personas = array_map( 'absint', $_POST['assigned_personas'] );
        update_post_meta( $post_id, '_assigned_personas', $sanitized_personas );
    } else {
        delete_post_meta( $post_id, '_assigned_personas' );
    }
}
add_action( 'save_post_aiden_agent', 'aipc_save_agent_meta_box_data' );

/**
 * Register the 'ai_persona' custom post type.
 */
function aipc_register_persona_post_type() {
    $labels = array(
        'name'                  => _x( 'Personas', 'Post type general name', 'ai-persona-core' ),
        'singular_name'         => _x( 'Persona', 'Post type singular name', 'ai-persona-core' ),
        'menu_name'             => _x( 'AI Personas', 'Admin Menu text', 'ai-persona-core' ),
        'name_admin_bar'        => _x( 'Persona', 'Add New on Toolbar', 'ai-persona-core' ),
        'add_new'               => __( 'Add New', 'ai-persona-core' ),
        'add_new_item'          => __( 'Add New Persona', 'ai-persona-core' ),
        'new_item'              => __( 'New Persona', 'ai-persona-core' ),
        'edit_item'             => __( 'Edit Persona', 'ai-persona-core' ),
        'view_item'             => __( 'View Persona', 'ai-persona-core' ),
        'all_items'             => __( 'All Personas', 'ai-persona-core' ),
        'search_items'          => __( 'Search Personas', 'ai-persona-core' ),
        'parent_item_colon'     => __( 'Parent Personas:', 'ai-persona-core' ),
        'not_found'             => __( 'No personas found.', 'ai-persona-core' ),
        'not_found_in_trash'    => __( 'No personas found in Trash.', 'ai-persona-core' ),
        'featured_image'        => _x( 'Persona Avatar', 'Overrides the “Featured Image” phrase for this post type.', 'ai-persona-core' ),
        'set_featured_image'    => _x( 'Set persona avatar', 'Overrides the “Set featured image” phrase for this post type.', 'ai-persona-core' ),
        'remove_featured_image' => _x( 'Remove persona avatar', 'Overrides the “Remove featured image” phrase for this post type.', 'ai-persona-core' ),
        'use_featured_image'    => _x( 'Use as persona avatar', 'Overrides the “Use as featured image” phrase for this post type.', 'ai-persona-core' ),
        'archives'              => _x( 'Persona archives', 'The post type archive label used in nav menus.', 'ai-persona-core' ),
        'insert_into_item'      => _x( 'Insert into persona', 'Overrides the “Insert into post”/”Insert into page” phrase.', 'ai-persona-core' ),
        'uploaded_to_this_item' => _x( 'Uploaded to this persona', 'Overrides the “Uploaded to this post”/”Uploaded to this page” phrase.', 'ai-persona-core' ),
        'filter_items_list'     => _x( 'Filter personas list', 'Screen reader text for the filter links.', 'ai-persona-core' ),
        'items_list_navigation' => _x( 'Personas list navigation', 'Screen reader text for the pagination.', 'ai-persona-core' ),
        'items_list'            => _x( 'Personas list', 'Screen reader text for the items list.', 'ai-persona-core' ),
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => 'aiden-settings',
        'query_var'          => true,
        'rewrite'            => array( 'slug' => 'persona' ),
        'capability_type'    => 'post',
        'has_archive'        => true,
        'hierarchical'       => false,
        'menu_position'      => 20, // Below Pages
        'supports'           => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
        'menu_icon'          => 'dashicons-admin-users',
        'show_in_rest'       => true,
    );

    register_post_type( 'ai_persona', $args );
}
add_action( 'init', 'aipc_register_persona_post_type' );

/**
 * Register the 'aiden_agent' custom post type.
 */
function aipc_register_agent_cpt() {
    $labels = array(
        'name'                  => _x( 'Agents', 'Post type general name', 'ai-persona-core' ),
        'singular_name'         => _x( 'Agent', 'Post type singular name', 'ai-persona-core' ),
        'menu_name'             => _x( 'Agents', 'Admin Menu text', 'ai-persona-core' ),
        'name_admin_bar'        => _x( 'Agent', 'Add New on Toolbar', 'ai-persona-core' ),
        'add_new'               => __( 'Add New', 'ai-persona-core' ),
        'add_new_item'          => __( 'Add New Agent', 'ai-persona-core' ),
        'new_item'              => __( 'New Agent', 'ai-persona-core' ),
        'edit_item'             => __( 'Edit Agent', 'ai-persona-core' ),
        'view_item'             => __( 'View Agent', 'ai-persona-core' ),
        'all_items'             => __( 'All Agents', 'ai-persona-core' ),
        'search_items'          => __( 'Search Agents', 'ai-persona-core' ),
        'not_found'             => __( 'No agents found.', 'ai-persona-core' ),
        'not_found_in_trash'    => __( 'No agents found in Trash.', 'ai-persona-core' ),
    );

    $args = array(
        'labels'             => $labels,
        'public'             => false,
        'show_ui'            => true,
        'show_in_menu'       => 'aiden-settings',
        'menu_icon'          => 'dashicons-groups',
        'query_var'          => false,
        'rewrite'            => false,
        'capability_type'    => 'post',
        'has_archive'        => false,
        'hierarchical'       => false,
        'supports'           => array( 'title', 'editor', 'custom-fields' ),
        'show_in_rest'       => true,
    );

    register_post_type( 'aiden_agent', $args );
}
add_action( 'init', 'aipc_register_agent_cpt' );

/**
 * Register custom taxonomies for the 'ai_persona' post type.
 */
function aipc_register_taxonomies() {
    // Persona Traits (Non-hierarchical, like tags)
    $trait_labels = array(
        'name'              => _x( 'Traits', 'taxonomy general name', 'ai-persona-core' ),
        'singular_name'     => _x( 'Trait', 'taxonomy singular name', 'ai-persona-core' ),
        'search_items'      => __( 'Search Traits', 'ai-persona-core' ),
        'all_items'         => __( 'All Traits', 'ai-persona-core' ),
        'parent_item'       => null,
        'parent_item_colon' => null,
        'edit_item'         => __( 'Edit Trait', 'ai-persona-core' ),
        'update_item'       => __( 'Update Trait', 'ai-persona-core' ),
        'add_new_item'      => __( 'Add New Trait', 'ai-persona-core' ),
        'new_item_name'     => __( 'New Trait Name', 'ai-persona-core' ),
        'separate_items_with_commas' => __( 'Separate traits with commas', 'ai-persona-core' ),
        'add_or_remove_items'        => __( 'Add or remove traits', 'ai-persona-core' ),
        'choose_from_most_used'      => __( 'Choose from the most used traits', 'ai-persona-core' ),
        'not_found'                  => __( 'No traits found.', 'ai-persona-core' ),
        'menu_name'         => __( 'Traits', 'ai-persona-core' ),
    );
    $trait_args = array(
        'hierarchical'      => false,
        'labels'            => $trait_labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array( 'slug' => 'trait' ),
        'show_in_rest'      => true,
    );
    register_taxonomy( 'persona_trait', 'ai_persona', $trait_args );

    // Persona Interests (Hierarchical, like categories)
    $interest_labels = array(
        'name'              => _x( 'Interests', 'taxonomy general name', 'ai-persona-core' ),
        'singular_name'     => _x( 'Interest', 'taxonomy singular name', 'ai-persona-core' ),
        'search_items'      => __( 'Search Interests', 'ai-persona-core' ),
        'all_items'         => __( 'All Interests', 'ai-persona-core' ),
        'parent_item'       => __( 'Parent Interest', 'ai-persona-core' ),
        'parent_item_colon' => __( 'Parent Interest:', 'ai-persona-core' ),
        'edit_item'         => __( 'Edit Interest', 'ai-persona-core' ),
        'update_item'       => __( 'Update Interest', 'ai-persona-core' ),
        'add_new_item'      => __( 'Add New Interest', 'ai-persona-core' ),
        'new_item_name'     => __( 'New Interest Name', 'ai-persona-core' ),
        'menu_name'         => __( 'Interests', 'ai-persona-core' ),
        'not_found'         => __( 'No interests found.', 'ai-persona-core' ),
    );
    $interest_args = array(
        'hierarchical'      => true,
        'labels'            => $interest_labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array( 'slug' => 'interest' ),
        'show_in_rest'      => true,
    );
    register_taxonomy( 'persona_interest', 'ai_persona', $interest_args );
}
add_action( 'init', 'aipc_register_taxonomies' );

/**
 * ===================================================================
 * AiDen Suite Settings Page
 * ===================================================================
 */

/**
 * Register the main AiDen admin menu page.
 */
function aipc_add_admin_menu() {
    add_menu_page(
        __( 'AiDen Settings', 'ai-persona-core' ),
        __( 'AiDen', 'ai-persona-core' ),
        'manage_options',
        'aiden-settings',
        'aipc_render_settings_page',
        'dashicons-brain', // A fitting icon for an AI suite
        25 // Position in the menu
    );

    // Add a submenu page for the dashboard. Using the parent slug for the menu slug makes
    // the top-level menu link point to this page.
    add_submenu_page(
        'aiden-settings',
        __( 'Dashboard', 'ai-persona-core' ),
        __( 'Dashboard', 'ai-persona-core' ),
        'manage_options',
        'aiden-settings',
        'aipc_render_settings_page'
    );
}
add_action( 'admin_menu', 'aipc_add_admin_menu' );

/**
 * Render the HTML for the AiDen settings page, including the Dashboard and settings tabs.
 */
function aipc_render_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'AiDen Suite', 'ai-persona-core' ); ?></h1>

        <?php
        // Show a success message after demo setup.
        if ( isset( $_GET['setup_status'] ) && $_GET['setup_status'] == 'success' ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Rich demo content has been successfully created!', 'ai-persona-core' ) . '</p></div>';
        }
        ?>

        <p><?php esc_html_e( 'This is the control panel for your autonomous AI agent ecosystem.', 'ai-persona-core' ); ?></p>

        <?php
        // Tab navigation
        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'dashboard';
        ?>

        <h2 class="nav-tab-wrapper">
            <a href="?page=aiden-settings&tab=dashboard" class="nav-tab <?php echo $active_tab == 'dashboard' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Dashboard', 'ai-persona-core' ); ?></a>
            <a href="?page=aiden-settings&tab=general_settings" class="nav-tab <?php echo $active_tab == 'general_settings' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'General Settings', 'ai-persona-core' ); ?></a>
            <a href="?page=aiden-settings&tab=triggers" class="nav-tab <?php echo $active_tab == 'triggers' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Triggers & Reactions', 'ai-persona-core' ); ?></a>
        </h2>

        <?php
        if ( $active_tab == 'dashboard' ) {
            // --- Dashboard Content ---
            global $wpdb;
            $table_name = $wpdb->prefix . 'ai_reaction_queue';

            // --- At a Glance Section ---
            $num_agents = wp_count_posts('aiden_agent')->publish;
            $num_personas = wp_count_posts('ai_persona')->publish;
            $num_pending = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} WHERE status = %s", 'pending' ) );

            echo '<h3>' . esc_html__( 'At a Glance', 'ai-persona-core' ) . '</h3>';
            echo '<div class="main-stats" style="display: flex; gap: 20px; margin-bottom: 20px; padding: 10px; background: #fff; border: 1px solid #c3c4c7;">';
            echo '<div class="stat-box" style="text-align: center;"><h2>' . (int)$num_agents . '</h2>' . esc_html__( 'Agents', 'ai-persona-core' ) . '</div>';
            echo '<div class="stat-box" style="margin-left: 20px; padding-left: 20px; border-left: 1px solid #ddd; text-align: center;"><h2>' . (int)$num_personas . '</h2>' . esc_html__( 'Personas', 'ai-persona-core' ) . '</div>';
            echo '<div class="stat-box" style="margin-left: 20px; padding-left: 20px; border-left: 1px solid #ddd; text-align: center;"><h2>' . (int)$num_pending . '</h2>' . esc_html__( 'Pending Reactions', 'ai-persona-core' ) . '</div>';
            echo '</div>';


            // Button for setting up demo data
            if ( ! get_transient( 'trm_demo_data_setup_complete' ) ) {
                echo '<div style="background-color: #fff; border: 1px solid #c3c4c7; padding: 1em; margin-bottom: 1em;">';
                echo '<h3>' . esc_html__( 'Get Started: Setup Demo Content', 'ai-persona-core' ) . '</h3>';
                echo '<p>' . esc_html__( 'To see AiDen in action, you can create a rich set of sample users and personas. This will only run once.', 'ai-persona-core' ) . '</p>';
                echo '<form method="post">';
                // We are submitting to the same page, so no action URL is needed.
                wp_nonce_field( 'aiden_setup_demo_data_nonce' );
                echo '<input type="hidden" name="aiden_action" value="setup_demo_data" />';
                submit_button( 'Create Demo Content', 'primary', 'aiden_setup_submit', false );
                echo '</form></div>';
            }

            global $wpdb;
            $table_name = $wpdb->prefix . 'ai_reaction_queue';

            $pending_reactions = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE status = %s ORDER BY created_at DESC LIMIT 10",
                'pending'
            ) );

            $recent_activity = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE status IN (%s, %s) ORDER BY updated_at DESC LIMIT 10",
                'posted', 'rejected'
            ) );

            echo '<h3>' . esc_html__( 'Pending Reactions (What is next)', 'ai-persona-core' ) . '</h3>';
            if ( ! empty( $pending_reactions ) ) {
                echo '<table class="widefat striped fixed">';
                echo '<thead><tr><th style="width:15%">Agent</th><th style="width:50%">Suggestion</th><th style="width:20%">Trigger Post</th><th style="width:15%">Queued</th></tr></thead>';
                echo '<tbody>';
                foreach ( $pending_reactions as $reaction ) {
                    $agent_name = get_the_title( $reaction->user_id );
                    $post_title = get_the_title( $reaction->trigger_post_id );
                    echo '<tr>';
                    echo '<td>' . esc_html( $agent_name ? $agent_name : 'Unknown Agent' ) . '</td>';
                    echo '<td><em>' . esc_html( wp_trim_words( $reaction->suggested_content, 15, '...' ) ) . '</em></td>';
                    echo '<td><a href="' . get_edit_post_link( $reaction->trigger_post_id ) . '">' . esc_html( $post_title ) . '</a></td>';
                    echo '<td>' . esc_html( $reaction->created_at ) . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            } else {
                echo '<p>' . esc_html__( 'No pending reactions in the queue.', 'ai-persona-core' ) . '</p>';
            }

            echo '<hr style="margin: 20px 0;"><h3>' . esc_html__( 'Recent Activity (What has happened)', 'ai-persona-core' ) . '</h3>';

            if ( ! empty( $recent_activity ) ) {
                echo '<table class="widefat striped fixed">';
                echo '<thead><tr><th style="width:15%">Agent</th><th style="width:50%">Suggestion</th><th style="width:15%">Status</th><th style="width:20%">Processed</th></tr></thead>';
                echo '<tbody>';
                foreach ( $recent_activity as $reaction ) {
                    $agent_name = get_the_title( $reaction->user_id );
                    echo '<tr>';
                    echo '<td>' . esc_html( $agent_name ? $agent_name : 'Unknown Agent' ) . '</td>';
                    echo '<td><em>' . esc_html( wp_trim_words( $reaction->suggested_content, 15, '...' ) ) . '</em></td>';
                    echo '<td><span style="font-weight:bold; color:' . ($reaction->status == 'posted' ? 'green' : '#a00') . ';">' . esc_html( ucfirst( $reaction->status ) ) . '</span></td>';
                    echo '<td>' . esc_html( $reaction->updated_at ) . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            } else {
                echo '<p>' . esc_html__( 'No recent activity found.', 'ai-persona-core' ) . '</p>';
            }

        } else {
            // --- Settings Form for other tabs ---
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
