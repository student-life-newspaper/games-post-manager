<?php
/* Custom Post Type and Taxonomy Start */
function create_posttype() {
    // Register the custom post type with 'news' slug but label it as 'Games'
    register_post_type('games',
        array(
            'labels' => array(
                'name' => __('Games'),
                'singular_name' => __('Game')
            ),
            'public' => true,
            'has_archive' => false,
            'rewrite' => array('slug' => 'games'), // Customize the slug
            'supports' => array('title', 'editor', 'thumbnail'),
            'taxonomies' => array('game_type'), // Declare the custom taxonomy here
        )
    );

    // Register custom taxonomy for game types
    register_taxonomy(
        'game_type',  // Taxonomy name (slug)
        'games',      // Attach this taxonomy to the 'news' post type (which is labeled as 'games')
        array(
            'labels' => array(
                'name' => __('Game Types'),
                'singular_name' => __('Game Type'),
            ),
            'public' => true,
            'hierarchical' => true, // Set to true for category-like behavior
            'rewrite' => array('slug' => 'game_type'), // Slug for the taxonomy
        )
    );

    // Add predefined game type terms
    wp_insert_term('Crossword', 'game_type');
}


function register_embed_code_meta() {
    register_post_meta('games', 'embed_code', array(
        'type'         => 'string',
        'description'  => 'Embed code for the game',
        'single'       => true,
        'show_in_rest' => true, // Set to true to make it available in the REST API
    ));
}
add_action('init', 'register_embed_code_meta');
// Hooking up our function to theme setup
add_action('init', 'create_posttype');
