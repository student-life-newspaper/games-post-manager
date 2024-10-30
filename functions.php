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


function register_crossword_size_meta() {
    register_post_meta('games', 'crossword_size', array(
        // type is an emum (small, medium, large)
        'type' => 'string',
        'description' => 'Size of the crossword puzzle',
        'single' => true,
        'show_in_rest' => true,
    ));
}


function crossword_size_custom_column($columns) {
    $columns['crossword_size'] = 'Crossword Size';
    return $columns;
}


function display_crossword_size_column($column, $post_id) {
    if ($column == 'crossword_size') {
        $value = get_post_meta($post_id, 'crossword_size', true);
        echo esc_html($value ?: 'N/A');
    }
}


function crossword_size_quick_edit_script() {
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const wp_inline_edit = inlineEditPost.edit;
        inlineEditPost.edit = function(post_id) {
            wp_inline_edit.apply(this, arguments);
            const postId = typeof(post_id) === 'object' ? parseInt(this.getId(post_id)) : post_id;
            if (postId > 0) {
                const crosswordSize = document.querySelector(`#post-${postId} .column-crossword_size`).textContent.trim();
                document.querySelector('select[name="crossword_size"]').value = crosswordSize;
            }
        };
    });
    </script>
    <?php
}

add_action('admin_footer', 'crossword_size_quick_edit_script');

function add_quick_edit_crossword_size($column_name) {
    if ($column_name == 'crossword_size') {
        ?>
        <fieldset class="inline-edit-col-left">
            <div class="inline-edit-col">
                <label>
                    <span class="title">Crossword Size</span>
                    <select name="crossword_size">
                        <option value="small">Small</option>
                        <option value="medium">Medium</option>
                        <option value="large">Large</option>
                    </select>
                </label>
            </div>
        </fieldset>
        <?php
    }
}



function save_crossword_size_quick_edit($post_id) {
    if (isset($_POST['crossword_size'])) {
        update_post_meta($post_id, 'crossword_size', sanitize_text_field($_POST['crossword_size']));
    }
}

add_action('save_post', 'save_crossword_size_quick_edit');
add_action('quick_edit_custom_box', 'add_quick_edit_crossword_size', 10, 2);
add_action('manage_games_posts_custom_column', 'display_crossword_size_column', 10, 2);

add_filter('manage_games_posts_columns', 'crossword_size_custom_column');
add_action('init', 'register_embed_code_meta');
// Hooking up our function to theme setup
add_action('init', 'create_posttype');
