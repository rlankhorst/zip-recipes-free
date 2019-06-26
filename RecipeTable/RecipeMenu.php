<?php
namespace ZRDN;
require_once(dirname(__FILE__).'/metabox.php');
/**
 * If a post is saved, we will link this recipe to this post id.
 *
 * At the same time we wil unlink this recipe from any other post.
 *
 * Does not work for classic shortcodes in Gutenberg.
 */

add_action('edit_post', __NAMESPACE__ . '\zrdn_save_post', 10, 2);
add_action('save_post', __NAMESPACE__ . '\zrdn_save_post', 10, 2);
function zrdn_save_post($post_id, $post_data){
    if (Util::has_shortcode($post_id, $post_data)){

        $pattern = Util::get_shortcode_pattern();
        $classic_pattern = Util::get_shortcode_pattern(false, false, true);
        if (preg_match($pattern, $post_data->post_content, $matches)) {
            $recipe_id = intval($matches[1]);
            //check if this post is already linked to another recipe. If so, unlink it.
            //then link to current post.
            ZipRecipes::link_recipe_to_post($post_id, $recipe_id);
        } elseif (preg_match($classic_pattern, $post_data->post_content, $matches)) {
            $recipe_id = $matches[1];
            ZipRecipes::link_recipe_to_post($post_id, $recipe_id);
        }
    } else {
        //no shortcode, make sure there is no recipe attached.
        zrdn_unlink_post_from_recipe($post_id);
    }

}

/**
 * If a post is deleted, we should update the recipe table as well to make sure no recipes are linked anymore to this post
 */

add_action('delete_post', __NAMESPACE__ . '\zrdn_unlink_post_from_recipe', 10, 1);
function zrdn_unlink_post_from_recipe($post_id){
    global $wpdb;
    $table = $wpdb->prefix . 'amd_zlrecipe_recipes';
    $sql = $wpdb->prepare("UPDATE ".$table." SET post_id = 0 WHERE post_id = %s", $post_id);
    return $wpdb->query($sql);
}


add_action('wp_ajax_zrdn_delete_recipe', __NAMESPACE__ . '\zrdn_delete_recipe');
function zrdn_delete_recipe(){
    $error = false;

    if (!current_user_can('edit_posts')) {
        $error = true;
    }

    if (!wp_verify_nonce($_POST['nonce'],'zrdn_delete_recipe')) {
        $error = true;
    }

    if (!$error && isset($_POST['recipe_id'])) {
        $recipe = new Recipe(intval($_POST['recipe_id']));
        $success = $recipe->delete();
        if (!$success) $error = true;
    }

    $response = json_encode(array(
        'success' => !$error,
    ));
    header("Content-Type: application/json");
    echo $response;
    exit;
}

add_action('wp_ajax_zrdn_unlink_recipe', __NAMESPACE__ . '\zrdn_unlink_recipe');
function zrdn_unlink_recipe(){
    $error = false;

    if (!current_user_can('edit_posts')) {
        $error = true;
    }

    if (!wp_verify_nonce($_POST['nonce'],'zrdn_delete_recipe')) {
        $error = true;
    }

    if (!$error && isset($_POST['recipe_id'])) {
        //remove recipe shortcode from post with regex
        $recipe_id = intval($_POST['recipe_id']);
        zrdn_unlink_recipe_from_post($recipe_id);
    }

    $response = json_encode(array(
        'success' => !$error,
    ));
    header("Content-Type: application/json");
    echo $response;
    exit;

}

add_action('admin_menu',  __NAMESPACE__ . '\zrdn_recipe_admin_menu');
function zrdn_recipe_admin_menu()
{
    if (!current_user_can('manage_options')) return;
    add_menu_page(
        __('Recipes', 'zip-recipes'),
        __('Recipes', 'zip-recipes'),
        'manage_options',
        'zrdn-recipes',
        __NAMESPACE__ . '\zrdn_recipe_overview',
        ZRDN_PLUGIN_URL . 'images/recipe-icon.svg',
        apply_filters('zrdn_menu_position', 50)
    );
}

add_action('admin_enqueue_scripts', __NAMESPACE__ . '\zrdn_enqueue_style');
function zrdn_enqueue_style($hook){
    if (strpos($hook, 'zrdn') === FALSE) return;

    if ((isset($_GET['page']) && $_GET['page']=='zrdn-recipes')) {
        wp_register_style('zrdn-recipes-overview', ZRDN_PLUGIN_URL."RecipeTable/css/recipes.css", array(), ZRDN_VERSION_NUM, 'all');
        wp_enqueue_style('zrdn-recipes-overview');
    }

    if (!isset($_GET['id']) && !(isset($_GET['action']) && $_GET['action']=='new') ) return;

    wp_enqueue_script("zrdn-editor", ZRDN_PLUGIN_URL."RecipeTable/js/editor.js",  array('jquery'), ZRDN_VERSION_NUM);
    wp_enqueue_script("zrdn-conditions", ZRDN_PLUGIN_URL."RecipeTable/js/conditions.js",  array('jquery'), ZRDN_VERSION_NUM);
    $args = array(
        'str_click_to_edit_image' => __("Click to edit this image","zip-recipes"),
        'str_minutes' => __("minutes","zip-recipes"),
        'str_hours' => __("hours","zip-recipes"),
    );
    wp_localize_script('zrdn-editor', 'zrdn_editor', $args);


    //wp_enqueue_style("bootstrap-3", ZRDN_PLUGIN_URL . '/vendor/twbs/bootstrap/dist/css/bootstrap.min.css');
    wp_register_style('zrdn-editor', ZRDN_PLUGIN_URL."RecipeTable/css/editor.css", array(), ZRDN_VERSION_NUM, 'all');
    wp_enqueue_style('zrdn-editor');
    wp_enqueue_media();
}

function zrdn_recipe_overview(){
    //if (!current_user_can('edit_posts')) return;

    $id = false;
    if (isset($_GET['id'])) {
        $id = intval($_GET['id']);
    }

    if ($id || (isset($_GET['action']) && $_GET['action']=='new'))  {
        include(dirname(__FILE__)."/edit.php");
    } else {
        include(dirname(__FILE__) . '/class-recipe-table.php');

        $recipes_table = new Recipe_Table();
        $recipes_table->prepare_items();

        ?>
        <script>
            jQuery(document).ready(function ($) {
                $(document).on('click', '.zrdn-recipe-action', function (e) {

                    e.preventDefault();
                    var btn = $(this);
                    var recipe_id = btn.data('id');
                    var action = btn.data('action');
                    if (action==='delete'){
                        btn.closest('tr').css('background-color', 'red');
                    }

                    $.ajax({
                        type: "POST",
                        url: '<?php echo admin_url('admin-ajax.php')?>',
                        dataType: 'json',
                        data: ({
                            action: 'zrdn_'+action+'_recipe',
                            recipe_id: recipe_id,
                            nonce:'<?php echo wp_create_nonce('zrdn_delete_recipe')?>',
                        }),
                        success: function (response) {
                            if (response.success) {
                                if (action==='unlink'){
                                    btn.closest('tr').find('.delete a').show();
                                    btn.closest('tr').find('.unlink a').hide();
                                } else {
                                    btn.closest('tr').remove();
                                }
                            }
                        }

                });

                });
            });
        </script>

        <div class="wrap zrdn-recipes">
            <h1><?php _e("Recipes", 'zip-recipes') ?>
                <a href="<?php echo admin_url('admin.php?page=zrdn-recipes&action=new'); ?>"
                   class="page-title-action"><?php _e('Add recipe', 'zip-recipes') ?></a>
                <?php do_action('zrdn_after_recipes_overview_title'); ?>
            </h1>


            <form id="zrdn-recipe-filter" method="get"
                  action="<?php echo add_query_arg(array('page'=>'zrdn-recipes'),admin_url('admin.php'))?>">

                <?php
                $recipes_table->search_box(__('Filter', 'zip-recipes'), 'zrdn-recipe');
                $recipes_table->display();
                ?>
                <input type="hidden" name="page" value="zrdn-recipes"/>
            </form>
        </div>
        <?php
    }
}


add_action('init', __NAMESPACE__.'\zrdn_process_update_recipe');
function zrdn_process_update_recipe(){

    /**
     * unlink from post
     */

    if ((isset($_GET['action']) && $_GET['action']=='unlink')) {
        zrdn_unlink_recipe_from_post(intval($_GET['id']));
    }

    /**
     * Saving and adding
     */


    if (isset($_POST['zrdn_save_recipe']) && wp_verify_nonce($_POST['zrdn_save_recipe'], 'zrdn_save_recipe')) {

        /**
         * adding new recipe
         */

        if (isset($_POST['zrdn_add_new']) || (isset($_GET['action']) && $_GET['action']=='new')) {
            if (isset($_POST['post_id'])){
                $post_id = intval($_POST['post_id']);
            }

            $recipe = new Recipe();

            $recipe->save();
            $recipe_id = $recipe->recipe_id;
            /**
             * if a new recipe is created and post id is passed, we make sure it is inserted in the current post.
             * Because we don't have a recipe ID yet, we have to store the post_id and post_type in a hidden field, and process this on update.
             *
             * Two options:
             *  1) there already is a recipe, and it needs to be replaced, and unlinked in the database
             *  2) No recipe yet. Just insert the shortcode, and link to this post.
             */
            if (isset($_POST['post_id'])) {
                //update the shortcode in this post, if necessary.
                $post = get_post(intval($_POST['post_id']));
                if (Util::has_shortcode($post_id, $post)) {
                    //we have a linked recipe
                    $pattern = Util::get_shortcode_pattern();
                    $entire_pattern = Util::get_shortcode_pattern(false, true);
                    $classic_pattern = Util::get_shortcode_pattern(false, false, true);
                    if (preg_match($pattern, $post->post_content, $matches)) {
                        $old_recipe_id = $matches[1];
                        $old_recipe = new Recipe($old_recipe_id);
                        $old_recipe->post_id = false;
                        $old_recipe->save();
                        $new_shortcode = Util::get_shortcode($recipe_id);
                        $content = preg_replace($pattern, $new_shortcode, $post->post_content, 1);
                    } elseif (preg_match($entire_pattern, $post->post_content, $matches)){
                        $shortcode = Util::get_shortcode($recipe_id);
                        $content = preg_replace($entire_pattern, $shortcode, $post->post_content, 1);

                        //if nothing matched yet, this might be classic shortcode
                    } elseif (preg_match($classic_pattern, $post->post_content, $matches)) {
                        $old_recipe_id = $matches[1];
                        $old_recipe = new Recipe($old_recipe_id);
                        $old_recipe->post_id = false;
                        $old_recipe->save();
                        $new_shortcode = Util::get_shortcode($recipe_id);
                        $content = preg_replace($pattern, $new_shortcode, $post->post_content, 1);
                    } else {
                        $content = $post->post_content;
                    }
                } else {
                    //no recipe yet. Just insert it
                    $content = Util::get_shortcode($recipe_id) . $post->post_content;
                }

                $post = array(
                    'ID' => $post_id,
                    'post_content' => $content,
                );
                wp_update_post($post);

                //update link to post in DB
                //the recipe is by this time already linked to this post, but this call will also make sure not other recipes are linked to this post
                ZipRecipes::link_recipe_to_post($post_id, $recipe_id);
            }
        } else {
            $recipe_id = intval($_POST['zrdn_recipe_id']);
        }


        /**
         * Saving the recipe
         */
        $recipe = new Recipe($recipe_id);
        //save all recipe fields here.
        foreach ($recipe as $fieldname => $value) {

            //sanitization in recipe class
            if (isset($_POST['zrdn_'.$fieldname]) && $fieldname!=="recipe_id") {
                $recipe->{$fieldname} = $_POST['zrdn_'.$fieldname];
            }

            //time
            if (isset($_POST['zrdn_'.$fieldname."_hours"]) && isset($_POST['zrdn_'.$fieldname."_minutes"])) {
                $recipe->{$fieldname} = 'PT'.intval($_POST['zrdn_'.$fieldname.'_hours']).'H'.intval($_POST['zrdn_'.$fieldname."_minutes"]).'M';
            }

            $recipe = apply_filters('zrdn_save_recipe', $recipe);
        }


        $recipe->save();

        //if recipe was just created, redirect to single edit page
        if (isset($_POST['zrdn_add_new']) || (isset($_GET['action']) && $_GET['action']=='new')) {
            $url = add_query_arg(array('page'=>'zrdn-recipes','id'=>$recipe_id), admin_url('admin.php'));
            wp_redirect($url);
            exit;
        }
    }
}
