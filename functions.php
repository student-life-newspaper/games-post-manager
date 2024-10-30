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

function register_additional_meta() {
    // Editor meta field
    register_post_meta('games', 'editor', array(
        'type'         => 'string',
        'description'  => 'Editor of the game',
        'single'       => true,
        'show_in_rest' => true,
    ));

    // Constructor meta field
    register_post_meta('games', 'constructor', array(
        'type'         => 'string',
        'description'  => 'Constructor of the game',
        'single'       => true,
        'show_in_rest' => true,
    ));

    // Description meta field
    register_post_meta('games', 'description', array(
        'type'         => 'string',
        'description'  => 'Description of the game',
        'single'       => true,
        'show_in_rest' => true,
    ));


    register_post_meta('games', 'crossword_size', array(
        // type is an emum (small, medium, large)
        'type' => 'string',
        'description' => 'Size of the crossword puzzle',
        'single' => true,
        'show_in_rest' => true,
    ));

    register_post_meta('games', 'embed_code', array(
        'type'         => 'string',
        'description'  => 'Embed code for the game',
        'single'       => true,
        'show_in_rest' => true, // Set to true to make it available in the REST API
    ));
}
add_action('init', 'register_additional_meta');



function games_custom_columns($columns) {
    $columns['crossword_size'] = 'Crossword Size';
    $columns['editor'] = 'Editor';
    $columns['constructor'] = 'Constructor';
    $columns['description'] = 'Description';
    return $columns;
}
add_filter('manage_games_posts_columns', 'games_custom_columns');



function display_games_custom_columns($column, $post_id) {
    if ($column == 'crossword_size') {
        $value = get_post_meta($post_id, 'crossword_size', true);
        echo esc_html($value ?: 'N/A');
    } elseif ($column == 'editor') {
        $value = get_post_meta($post_id, 'editor', true);
        echo esc_html($value ?: 'N/A');
    } elseif ($column == 'constructor') {
        $value = get_post_meta($post_id, 'constructor', true);
        echo esc_html($value ?: 'N/A');
    } elseif ($column == 'description') {
        $value = get_post_meta($post_id, 'description', true);
        echo esc_html($value ?: 'N/A');
    }
}
add_action('manage_games_posts_custom_column', 'display_games_custom_columns', 10, 2);

function crossword_size_quick_edit_script() {
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const wp_inline_edit = inlineEditPost.edit;
        inlineEditPost.edit = function(post_id) {
            wp_inline_edit.apply(this, arguments);
            const postId = typeof(post_id) === 'object' ? parseInt(this.getId(post_id)) : post_id;
            if (postId > 0) {
                const row = document.querySelector(`#post-${postId}`);
                if (row) {
                    // Crossword Size
                    const crosswordSize = row.querySelector('.column-crossword_size').textContent.trim();
                    const sizeField = document.querySelector('select[name="crossword_size"]');
                    if (sizeField) sizeField.value = crosswordSize;

                    // Editor
                    const editor = row.querySelector('.column-editor').textContent.trim();
                    const editorField = document.querySelector('input[name="editor"]');
                    if (editorField) editorField.value = editor !== 'N/A' ? editor : '';

                    // Constructor
                    const constructor = row.querySelector('.column-constructor').textContent.trim();
                    const constructorField = document.querySelector('input[name="constructor"]');
                    if (constructorField) constructorField.value = constructor !== 'N/A' ? constructor : '';

                    // Description
                    const description = row.querySelector('.column-description').textContent.trim();
                    const descriptionField = document.querySelector('textarea[name="description"]');
                    if (descriptionField) descriptionField.value = description !== 'N/A' ? description : '';
                }
            }
        };
    });
    </script>
    <?php
}

add_action('admin_footer', 'crossword_size_quick_edit_script');

function add_quick_edit_custom_fields($column_name, $post) {
    $post_id = $post->ID;
    if (in_array($column_name, ['crossword_size', 'editor', 'constructor', 'description'])) {
        ?>
        <fieldset class="inline-edit-col-left">
            <div class="inline-edit-col">
                <?php if ($column_name == 'crossword_size'): ?>
                    <label>
                        <span class="title">Crossword Size</span>
                        <select name="crossword_size">
                            <option value="small">Small</option>
                            <option value="medium">Medium</option>
                            <option value="large">Large</option>
                        </select>
                    </label>
                <?php elseif ($column_name == 'editor'): ?>
                    <label>
                        <span class="title">Editor</span>
                        <input type="text" name="editor" value="">
                    </label>
                <?php elseif ($column_name == 'constructor'): ?>
                    <label>
                        <span class="title">Constructor</span>
                        <input type="text" name="constructor" value="">
                    </label>
                <?php elseif ($column_name == 'description'): ?>
                    <label>
                        <span class="title">Description</span>
                        <textarea name="description"></textarea>
                    </label>
                <?php endif; ?>
            </div>
        </fieldset>
        <?php
    }
}
add_action('quick_edit_custom_box', 'add_quick_edit_custom_fields', 10, 2);


function save_crossword_size_quick_edit($post_id) {
    if (isset($_POST['crossword_size'])) {
        update_post_meta($post_id, 'crossword_size', sanitize_text_field($_POST['crossword_size']));
    }
    if (isset($_POST['editor'])) {
        update_post_meta($post_id, 'editor', sanitize_text_field($_POST['editor']));
    }
    if (isset($_POST['constructor'])) {
        update_post_meta($post_id, 'constructor', sanitize_text_field($_POST['constructor']));
    }
    if (isset($_POST['description'])) {
        update_post_meta($post_id, 'description', $_POST['description']);
    }
}
add_action('save_post', 'save_crossword_size_quick_edit');
// Hooking up our function to theme setup
add_action('init', 'create_posttype');
