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

    // Categories are reusable and can be assigned many-at-a-time. The legacy
    // `category` post meta is kept in sync below for backwards compatibility.
    register_taxonomy(
        'game_category',
        'games',
        array(
            'labels' => array(
                'name'          => __('Game Categories'),
                'singular_name' => __('Game Category'),
            ),
            'public'            => true,
            'hierarchical'      => false,
            // The plugin supplies one consistent searchable picker. Suppress
            // WordPress's separate tag-style metabox and Quick Edit textarea.
            'show_ui'           => false,
            'show_admin_column' => false,
            'show_in_rest'      => true,
            'rewrite'           => array('slug' => 'game-category'),
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

    // Category meta field
    register_post_meta('games', 'category', array(
        'type'         => 'array',
        'description'  => 'Category of the game',
        'single'       => true,
        'show_in_rest' => array(
            'schema' => array(
                'type'  => 'array',
                'items' => array('type' => 'string'),
            ),
        ),
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

function games_metadata_fields() {
    return array(
        'crossword_size' => array(
            'label' => 'Crossword Size',
            'type' => 'select',
            'options' => array(
                'small' => 'Small',
                'medium' => 'Medium',
                'large' => 'Large',
            ),
        ),
        'editor' => array(
            'label' => 'Editor',
            'type' => 'text',
        ),
        'constructor' => array(
            'label' => 'Constructor',
            'type' => 'text',
        ),
        'category' => array(
            'label' => 'Category',
            'type' => 'text',
        ),
        'description' => array(
            'label' => 'Description',
            'type' => 'textarea',
        ),
        'embed_code' => array(
            'label' => 'Puzzle Link',
            'type' => 'url',
        ),
    );
}

function games_parse_categories($value) {
    if (is_array($value)) {
        $values = $value;
    } else {
        // Category names may contain commas. New values are stored as JSON so
        // they can be round-tripped without treating punctuation as a separator.
        $decoded = json_decode((string) $value, true);
        $values = is_array($decoded) ? $decoded : array($value);
    }

    $categories = array();
    foreach ($values as $category) {
        $category = sanitize_text_field(wp_unslash($category));
        if ($category !== '' && !in_array($category, $categories, true)) {
            $categories[] = $category;
        }
    }
    return $categories;
}

function games_serialize_categories($categories) {
    return games_parse_categories($categories);
}

function games_get_post_categories($post_id) {
    $categories = wp_get_object_terms($post_id, 'game_category', array('fields' => 'names'));
    if (is_wp_error($categories) || empty($categories)) {
        $categories = games_parse_categories(get_post_meta($post_id, 'category', true));
    }
    return empty($categories) ? array('Other') : $categories;
}

/**
 * Delete game-category terms after their final relationship is removed.
 *
 * WordPress keeps unused taxonomy terms by default. Restricting this cleanup to
 * game_category prevents an edit to a game from affecting unrelated taxonomies.
 */
function games_delete_empty_category_terms($term_taxonomy_ids) {
    foreach (array_unique(array_map('intval', (array) $term_taxonomy_ids)) as $term_taxonomy_id) {
        $term = get_term_by('term_taxonomy_id', $term_taxonomy_id, 'game_category');
        if ($term && !is_wp_error($term) && (int) $term->count === 0) {
            wp_delete_term($term->term_id, 'game_category');
        }
    }
}

// Covers categories removed or replaced while a game is being edited.
function games_cleanup_categories_after_assignment($object_id, $terms, $term_taxonomy_ids, $taxonomy, $append, $old_term_taxonomy_ids) {
    if ($taxonomy === 'game_category') {
        games_delete_empty_category_terms($old_term_taxonomy_ids);
    }
}
add_action('set_object_terms', 'games_cleanup_categories_after_assignment', 10, 6);

// Covers relationships removed when a game is deleted.
function games_cleanup_categories_after_relationship_deletion($object_id, $term_taxonomy_ids, $taxonomy) {
    if ($taxonomy === 'game_category') {
        games_delete_empty_category_terms($term_taxonomy_ids);
    }
}
add_action('deleted_term_relationships', 'games_cleanup_categories_after_relationship_deletion', 10, 3);

// Remove orphaned terms that existed before automatic cleanup was introduced.
function games_cleanup_existing_empty_category_terms() {
    if (get_option('games_empty_category_cleanup_version') === '1') {
        return;
    }

    $terms = get_terms(array(
        'taxonomy'   => 'game_category',
        'hide_empty' => false,
    ));
    if (is_wp_error($terms)) {
        return;
    }

    foreach ($terms as $term) {
        if ((int) $term->count === 0) {
            wp_delete_term($term->term_id, 'game_category');
        }
    }
    update_option('games_empty_category_cleanup_version', '1');
}
add_action('init', 'games_cleanup_existing_empty_category_terms', 30);

// Convert JSON-string category meta created by older plugin versions into a
// native array. This also gives genuinely uncategorized games the documented
// fallback instead of exposing "[]" in feeds that read post meta directly.
function games_migrate_category_meta_storage() {
    if (get_option('games_category_meta_storage_version') === '2') {
        return;
    }

    $post_ids = get_posts(array(
        'post_type'      => 'games',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ));

    foreach ($post_ids as $post_id) {
        $categories = games_get_post_categories($post_id);
        wp_set_object_terms($post_id, $categories, 'game_category', false);
        update_post_meta($post_id, 'category', games_serialize_categories($categories));
    }

    update_option('games_category_meta_storage_version', '2');
}
add_action('init', 'games_migrate_category_meta_storage', 20);

function games_get_category_options() {
    $options = get_terms(array(
        'taxonomy'   => 'game_category',
        'hide_empty' => false,
        'fields'     => 'names',
    ));
    if (is_wp_error($options)) {
        $options = array();
    }

    // Include values created before game_category became a taxonomy.
    $legacy_values = get_posts(array(
        'post_type'      => 'games',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_key'       => 'category',
    ));
    foreach ($legacy_values as $post_id) {
        $options = array_merge($options, games_parse_categories(get_post_meta($post_id, 'category', true)));
    }

    $options = array_values(array_unique(array_filter($options)));
    natcasesort($options);
    return array_values($options);
}

function games_render_category_picker($name, $selected = array(), $id = '') {
    $selected = games_parse_categories($selected);
    $options = array_values(array_unique(array_merge(games_get_category_options(), $selected)));
    natcasesort($options);
    ?>
    <div class="games-category-picker" data-input-name="<?php echo esc_attr($name); ?>[]" data-options="<?php echo esc_attr(wp_json_encode(array_values($options))); ?>">
        <?php if ($name === 'category'): ?>
            <input type="hidden" name="games_category_picker_present" value="1">
        <?php endif; ?>
        <div class="games-category-selections"></div>
        <input type="text" class="games-category-search regular-text"<?php echo $id ? ' id="' . esc_attr($id) . '"' : ''; ?> autocomplete="off" placeholder="Search or add a category">
        <div class="games-category-menu" hidden></div>
        <?php foreach ($selected as $category): ?>
            <input type="hidden" name="<?php echo esc_attr($name); ?>[]" value="<?php echo esc_attr($category); ?>">
        <?php endforeach; ?>
        <p class="description">Choose one or more categories. Press Enter to add a new one.</p>
    </div>
    <?php
}

function add_games_metadata_meta_box() {
    add_meta_box(
        'games_metadata',
        'Game Metadata',
        'render_games_metadata_meta_box',
        'games',
        'normal',
        'default'
    );
}
add_action('add_meta_boxes', 'add_games_metadata_meta_box');

// Game metadata is managed by the dedicated box above. Keep the stored post
// meta, but remove WordPress's redundant generic Custom Fields editor.
function remove_games_custom_fields_meta_box() {
    remove_meta_box('postcustom', 'games', 'normal');
}
add_action('add_meta_boxes_games', 'remove_games_custom_fields_meta_box', 100);

function render_games_metadata_meta_box($post) {
    wp_nonce_field('save_games_metadata', 'games_metadata_nonce');

    foreach (games_metadata_fields() as $key => $field) {
        $value = get_post_meta($post->ID, $key, true);
        ?>
        <p>
            <label for="games_meta_<?php echo esc_attr($key); ?>"><strong><?php echo esc_html($field['label']); ?></strong></label><br>
            <?php if ($field['type'] === 'select'): ?>
                <select id="games_meta_<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>">
                    <option value="">Select size</option>
                    <?php foreach ($field['options'] as $option_value => $option_label): ?>
                        <option value="<?php echo esc_attr($option_value); ?>" <?php selected($value, $option_value); ?>>
                            <?php echo esc_html($option_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php elseif ($key === 'category'): ?>
                <?php games_render_category_picker('category', games_get_post_categories($post->ID), 'games_meta_category'); ?>
            <?php elseif ($field['type'] === 'textarea'): ?>
                <textarea id="games_meta_<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>" class="large-text" rows="4"><?php echo esc_textarea($value); ?></textarea>
            <?php else: ?>
                <input id="games_meta_<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>" class="regular-text" type="<?php echo esc_attr($field['type']); ?>" value="<?php echo esc_attr($value); ?>">
            <?php endif; ?>
        </p>
        <?php
    }
}



function games_custom_columns($columns) {
    // The Template column is injected for this post type even though Games do
    // not support templates. Its empty body cell causes the AJAX-refreshed row
    // to appear one column out of alignment after Quick Edit/Update.
    unset($columns['template']);
    $columns['crossword_size'] = 'Crossword Size';
    $columns['editor'] = 'Editor';
    $columns['constructor'] = 'Constructor';
    $columns['category'] = 'Category';
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
    } elseif ($column == 'category') {
        $categories = games_get_post_categories($post_id);
        $value = implode(' · ', $categories);
        echo '<span class="games-category-value" data-categories="' . esc_attr(wp_json_encode($categories)) . '">' . esc_html($value ?: 'N/A') . '</span>';
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
	        if (typeof inlineEditPost === 'undefined') {
	            return;
	        }

	        const getColumnText = function(row, selector) {
	            const column = row.querySelector(selector);
	            return column ? column.textContent.trim() : '';
	        };

	        const wp_inline_edit = inlineEditPost.edit;
	        inlineEditPost.edit = function(post_id) {
	            wp_inline_edit.apply(this, arguments);
	            const postId = typeof(post_id) === 'object' ? parseInt(this.getId(post_id)) : post_id;
	            if (postId > 0) {
	                const row = document.querySelector(`#post-${postId}`);
	                const editRow = document.querySelector(`#edit-${postId}`);
	                if (row && editRow) {
	                    // Crossword Size
	                    const crosswordSize = getColumnText(row, '.column-crossword_size');
	                    const sizeField = editRow.querySelector('select[name="crossword_size"]');
	                    if (sizeField) sizeField.value = crosswordSize;
	
	                    // Editor
	                    const editor = getColumnText(row, '.column-editor');
	                    const editorField = editRow.querySelector('input[name="editor"]');
	                    if (editorField) editorField.value = editor !== 'N/A' ? editor : '';
	
	                    // Constructor
	                    const constructor = getColumnText(row, '.column-constructor');
	                    const constructorField = editRow.querySelector('input[name="constructor"]');
	                    if (constructorField) constructorField.value = constructor !== 'N/A' ? constructor : '';
	
	                    // Category
	                    const categoryValue = row.querySelector('.games-category-value');
	                    const categoryPicker = editRow.querySelector('.games-category-picker');
	                    if (categoryPicker && categoryValue && window.gamesCategoryPickerSet) {
	                        let categories = [];
	                        try { categories = JSON.parse(categoryValue.dataset.categories || '[]'); } catch (e) {}
	                        window.gamesCategoryPickerSet(categoryPicker, categories);
	                    }
	
	                    // Description
	                    const description = getColumnText(row, '.column-description');
	                    const descriptionField = editRow.querySelector('textarea[name="description"]');
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
    // quick_edit_custom_box runs once for every custom column. Render the
    // complete group only once so WordPress cannot scatter the fields.
    if ($column_name !== 'crossword_size') {
        return;
    }
    ?>
        <fieldset class="games-quick-edit-fields">
            <div class="games-quick-edit-grid">
                <div class="inline-edit-col">
                    <label>
                        <span class="title">Crossword Size</span>
                        <select name="crossword_size">
                            <option value="small">Small</option>
                            <option value="medium">Medium</option>
                            <option value="large">Large</option>
                        </select>
                    </label>
                    <label>
                        <span class="title">Editor</span>
                        <input type="text" name="editor" value="">
                    </label>
                    <label class="games-category-row">
                        <span class="title">Category</span>
                        <?php games_render_category_picker('category', array()); ?>
                    </label>
                </div>
                <div class="inline-edit-col">
                    <label>
                        <span class="title">Constructor</span>
                        <input type="text" name="constructor" value="">
                    </label>
                    <label>
                        <span class="title">Description</span>
                        <textarea name="description"></textarea>
                    </label>
                </div>
            </div>
        </fieldset>
    <?php
}
add_action('quick_edit_custom_box', 'add_quick_edit_custom_fields', 10, 2);


function save_crossword_size_quick_edit($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (wp_is_post_revision($post_id) || get_post_type($post_id) !== 'games') {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    if (
        isset($_POST['games_metadata_nonce']) &&
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['games_metadata_nonce'])), 'save_games_metadata')
    ) {
        return;
    }

    if (isset($_POST['games_category_picker_present'])) {
        $categories = games_parse_categories(isset($_POST['category']) ? wp_unslash($_POST['category']) : array());
        if (empty($categories)) {
            $categories = array('Other');
        }
        wp_set_object_terms($post_id, $categories, 'game_category', false);
        update_post_meta($post_id, 'category', games_serialize_categories($categories));
    }

    foreach (games_metadata_fields() as $key => $field) {
        if (!isset($_POST[$key])) {
            continue;
        }

        $raw_value = wp_unslash($_POST[$key]);
        if ($key === 'category') {
            continue;
        }
        $value = $field['type'] === 'textarea' ? sanitize_textarea_field($raw_value) : sanitize_text_field($raw_value);

        update_post_meta($post_id, $key, $value);
    }
}
add_action('save_post', 'save_crossword_size_quick_edit');

function games_category_picker_assets() {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || ($screen->post_type !== 'games' && $screen->id !== 'toplevel_page_games-post-manager')) {
        return;
    }
    ?>
    <style>
        .games-category-picker{position:relative;max-width:520px}.games-category-selections{display:flex;flex-wrap:wrap;gap:5px;margin-bottom:5px}.games-category-chip{display:inline-flex;align-items:center;gap:4px;background:#f0f0f1;border-radius:12px;padding:2px 8px;max-width:100%;overflow-wrap:anywhere}.games-category-chip button{border:0;background:transparent;cursor:pointer;padding:0;color:#646970}.games-category-menu{position:absolute;z-index:10000;left:0;right:0;max-height:180px;overflow:auto;background:#fff;border:1px solid #8c8f94;box-shadow:0 2px 5px rgba(0,0,0,.15)}.games-category-option{display:block;width:100%;padding:7px 10px;border:0;background:#fff;text-align:left;cursor:pointer}.games-category-option:hover,.games-category-option:focus{background:#f0f0f1}.games-category-picker .description{margin:4px 0 0}.games-quick-edit-layout{display:grid;grid-template-columns:minmax(300px,.8fr) minmax(560px,1.7fr);gap:24px;padding:0 .5em}.games-quick-edit-pane{min-width:0}.games-quick-edit-pane>fieldset{box-sizing:border-box;float:none!important;width:100%!important;margin:0!important;padding:0!important}.games-quick-edit-pane-left>.inline-edit-col-center{margin-top:12px!important}.games-quick-edit-pane-right>.inline-edit-col-right{margin-bottom:12px!important}.inline-edit-row fieldset.games-quick-edit-fields{clear:none;width:auto}.games-quick-edit-grid{display:grid;grid-template-columns:minmax(360px,.9fr) minmax(420px,1.1fr);gap:12px 3%}.games-quick-edit-fields .inline-edit-col{padding:0}.games-quick-edit-fields label{display:block;clear:both;margin:.35em 0}.games-quick-edit-grid>.inline-edit-col:first-child>label{display:grid;grid-template-columns:140px minmax(0,1fr);align-items:start}.games-quick-edit-grid>.inline-edit-col:first-child>label>.title{float:none;width:auto;white-space:nowrap}.games-quick-edit-fields .games-category-row>.games-category-picker{margin-left:0}.games-quick-edit-fields .games-category-search{box-sizing:border-box;width:100%}@media(max-width:1100px){.games-quick-edit-layout{grid-template-columns:1fr}.games-quick-edit-pane-left>.inline-edit-col-center{margin-top:8px!important}.games-quick-edit-grid{grid-template-columns:minmax(340px,1fr) minmax(380px,1fr)}}@media(max-width:782px){.games-quick-edit-layout{display:block;padding:0}.games-quick-edit-pane>fieldset{margin-bottom:12px!important}.games-quick-edit-grid{grid-template-columns:1fr}.games-quick-edit-grid>.inline-edit-col:first-child>label{display:block}.games-quick-edit-grid>.inline-edit-col:first-child>label>.title{white-space:normal}.games-quick-edit-fields .games-category-row>.games-category-picker{margin-left:0}.games-quick-edit-fields .games-category-row>.title{float:none;width:auto;display:block}}
    </style>
    <script>
    (function(){
        function arrangeQuickEdit(){
            document.querySelectorAll('.inline-edit-row .inline-edit-wrapper').forEach(wrapper=>{
                if(wrapper.querySelector(':scope > .games-quick-edit-layout')) return;
                const left=wrapper.querySelector(':scope > fieldset.inline-edit-col-left');
                const gameTypes=wrapper.querySelector(':scope > fieldset.inline-edit-col-center');
                const publishing=wrapper.querySelector(':scope > fieldset.inline-edit-col-right');
                const metadata=wrapper.querySelector(':scope > fieldset.games-quick-edit-fields');
                if(!left || !gameTypes || !metadata) return;
                const layout=document.createElement('div'); layout.className='games-quick-edit-layout';
                const leftPane=document.createElement('div'); leftPane.className='games-quick-edit-pane games-quick-edit-pane-left';
                const rightPane=document.createElement('div'); rightPane.className='games-quick-edit-pane games-quick-edit-pane-right';
                leftPane.append(left,gameTypes);
                if(publishing) rightPane.append(publishing);
                rightPane.append(metadata);
                layout.append(leftPane,rightPane);
                wrapper.insertBefore(layout,wrapper.querySelector('.submit'));
            });
        }
        function init(picker){
            if (picker._pickerReady) return;
            picker._pickerReady = true;
            const search = picker.querySelector('.games-category-search');
            const menu = picker.querySelector('.games-category-menu');
            const chips = picker.querySelector('.games-category-selections');
            let options = [];
            try { options = JSON.parse(picker.dataset.options || '[]'); } catch(e) {}
            function categoryInputs(){ return Array.from(picker.querySelectorAll('input[type="hidden"]')).filter(i => i.name === picker.dataset.inputName); }
            function selected(){ return categoryInputs().map(i => i.value); }
            function add(value){
                value = value.trim();
                if (!value || selected().some(v => v.toLowerCase() === value.toLowerCase())) return;
                const input = document.createElement('input'); input.type='hidden'; input.name=picker.dataset.inputName; input.value=value; picker.appendChild(input);
                if (!options.some(v => v.toLowerCase() === value.toLowerCase())) options.push(value);
                renderChips(); search.value=''; renderMenu();
            }
            function renderChips(){
                chips.innerHTML='';
                selected().forEach(value => {
                    const chip=document.createElement('span'); chip.className='games-category-chip'; chip.append(document.createTextNode(value));
                    const remove=document.createElement('button'); remove.type='button'; remove.setAttribute('aria-label','Remove '+value); remove.textContent='×';
                    remove.onclick=()=>{ const input=categoryInputs().find(i=>i.value===value); if(input) input.remove(); renderChips(); renderMenu(); };
                    chip.appendChild(remove); chips.appendChild(chip);
                });
            }
            function renderMenu(){
                const query=search.value.trim().toLowerCase(); const chosen=selected().map(v=>v.toLowerCase());
                const matches=options.filter(v=>!chosen.includes(v.toLowerCase()) && (!query || v.toLowerCase().includes(query)));
                menu.innerHTML='';
                matches.forEach(value=>{ const button=document.createElement('button'); button.type='button'; button.className='games-category-option'; button.textContent=value; button.onclick=()=>add(value); menu.appendChild(button); });
                if (query && !options.some(v=>v.toLowerCase()===query) && !chosen.includes(query)) { const button=document.createElement('button'); button.type='button'; button.className='games-category-option'; button.textContent='Add “'+search.value.trim()+'”'; button.onclick=()=>add(search.value); menu.appendChild(button); }
                menu.hidden=!menu.children.length || document.activeElement!==search;
            }
            search.addEventListener('input',renderMenu); search.addEventListener('focus',renderMenu);
            search.addEventListener('keydown',e=>{ if(e.key==='Enter' && search.value.trim()){ e.preventDefault(); add(search.value); } });
            search.addEventListener('blur',()=>setTimeout(()=>menu.hidden=true,150));
            if (picker.closest('form')) picker.closest('form').addEventListener('submit',()=>{ if(search.value.trim()) add(search.value); });
            picker._setCategories = values => { categoryInputs().forEach(i=>i.remove()); values.forEach(add); renderChips(); };
            renderChips();
        }
        window.gamesCategoryPickerSet=(picker,values)=>{ init(picker); picker._setCategories(values); };
        document.addEventListener('DOMContentLoaded',()=>{ arrangeQuickEdit(); document.querySelectorAll('.games-category-picker').forEach(init); });
    })();
    </script>
    <?php
}
add_action('admin_footer', 'games_category_picker_assets', 20);
// Hooking up our function to theme setup
add_action('init', 'create_posttype');
