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
        'show_in_menu'       => true,
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
