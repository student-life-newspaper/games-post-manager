
<?php
/*
Plugin Name: Games Post Manager
Description: A simple plugin to create and manager games posts from the admin interface
Version: 1.0
Author: Zachary Cohn
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Hook to add admin menu
add_action('admin_menu', 'games_post_creator_menu');

// Add menu item in the admin panel
function games_post_creator_menu() {
    add_menu_page(
        'Add Games Post',             // Page title
        'Games Post Creator',             // Menu title
        'manage_options',           // Capability
        'games-post-maanger',      // Menu slug
        'games_post_creator_form', // Callback function
        'dashicons-admin-post',     // Icon
        20                          // Position
    );
}

// Form rendering and submission handling
function games_post_creator_form() {
    // Check if form is submitted
    if (isset($_POST['games_post_creator_submit'])) {
        $title = sanitize_text_field($_POST['games_post_creator_title']);
        $date = sanitize_text_field($_POST['games_post_creator_date']);
        $game_type = sanitize_text_field($_POST['games_post_creator_game_type']);
        $embed_code = $_POST['games_post_creator_embed_code'];

        // Create a new post programmatically
        $new_post = array(
            'post_title'    => $title,
            'post_date'     => $date,
            'post_status'   => 'publish', // or 'draft'
            'post_type'     => 'games',
            'post_content' => $embed_code,
            'meta_input' => array(
                'embed_code' => $embed_code,
            ),

        );

        // Insert the post into the database
        $post_id = wp_insert_post($new_post);

        // Display success message if post is created
        if ($post_id) {
            // set taxonomies
            wp_set_object_terms($post_id, $game_type, 'game_type');
            echo '<div class="notice notice-success is-dismissible"><p>Post created successfully!</p><a href="' . get_permalink($post_id) . '">View Post</a></div>';
            echo "<script>console.log('GAME TYPE $game_type');</script>";
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>Error creating post!</p></div>';
        }
    }

    // Form HTML
    ?>
    <div class="wrap">
        <h1>Create a New Post</h1>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="games_post_creator_title">Title</label></th>
                    <td><input type="text" name="games_post_creator_title" id="games_post_creator_title" class="regular-text" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="games_post_creator_date">Date</label></th>
                    <td><input type="date" name="games_post_creator_date" id="games_post_creator_date" class="regular-text" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="games_post_creator_game_tyep">Game Type</label></th>
                    <td>
                        <select name="games_post_creator_game_type" id="games_post_creator_game_type">
                            <option value="Crossword">Crossword</option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for"games_post_creator_embed_cde">Embed Code</label></th>
                    <td><textarea name="games_post_creator_embed_code" id="games_post_creator_embed_code" class="large-text" rows="5"></textarea></td>
                </tr>
            </table>
            <?php submit_button('Create Post', 'primary', 'games_post_creator_submit'); ?>
        </form>
    </div>
    <?php
}
