<?php

/**
 * Created by PhpStorm.
 * User: gezimhoxha
 * Date: 15-05-25
 * Time: 12:59 AM
 */

namespace ZRDN;

require_once(ZRDN_PLUGIN_DIRECTORY . 'vendor/autoload.php');

class Util {
    /* Send debug code to the Javascript console */

    public static function zrdn_debug_to_console($data) {
        if (is_array($data) || is_object($data)) {
            echo("<script>console.log('PHP: " . json_encode($data) . "');</script>");
        } else {
            echo("<script>console.log('PHP: " . $data . "');</script>");
        }
    }

	public static function timeToISO8601($hours, $minutes) {
		$time = '';
		if ($hours || $minutes) {
			$time = 'P';
			if (isset($hours) || isset($minutes)) {
				$time .= 'T';
			}
			if (isset($hours)) {
				if ($minutes && $hours == '') { // if there's minutes and not hours set hours to 0 ..
					$time .= '0H';
				}
				else {
					$time .= $hours . 'H';
				}
			}
			if (isset($minutes)) {
				if ($hours && $minutes == '') { // if there's hours and but not minutes, set minutes to 0..
					$time .=  '0M';
				}
				else {
					$time .= $minutes . 'M';
				}
			}
		}
		return $time;
	}

    /**
     * Check if this setup uses Gutenberg
     * @return bool
     */
    public static function uses_gutenberg()
    {

        if (function_exists('has_block') && !class_exists('Classic_Editor')) {
            return true;
        }
        return false;
    }

    /**
     * Check if this site uses Elementor
     * When Elementor is used, the classic shortcode should be used, even when on Gutenberg
     *
     * @return bool $uses_elementor
     */

    public static function uses_elementor(){
        if (defined('ELEMENTOR_VERSION')) return true;

        return false;
    }

    /**
     *
     * get the shortcode or block for a page type
     *
     * @param string $type
     * @return string $shortcode
     *
     *
     */


    public static function get_shortcode($recipe_id)
    {
        if (!$recipe_id) return false;

        //even if on gutenberg, with elementor we have to use classic shortcodes.
        if (Util::uses_gutenberg() && !Util::uses_elementor()){
            return '<!-- wp:zip-recipes/recipe-block {"id":"'.$recipe_id.'"} /-->';
        } else {
            return '[amd-zlrecipe-recipe:'.$recipe_id.']';

        }
    }

    /**
     * Check if post contains a Gutenberg block or shortcode from Zip
     * @param $post_id
     * @param $post_data
     * @return bool
     */


    public static function has_shortcode($post_id, $post_data){
        if (!$post_data) $post_data = get_post($post_id);
        if (!$post_data) return false;

        if (strpos($post_data->post_content, 'amd-zlrecipe-recipe')!==FALSE || strpos($post_data->post_content, 'wp:zip-recipes/recipe-block')!==FALSE){
            return true;
        }
        return false;
    }


    /**
     *
     * get the shortcode or block for a page type
     *
     * @param string $type
     * @param boolean empty, to get pattern for gutenberg shortcode without recipeid
     * @return string $shortcode
     *
     *
     */


    public static function get_shortcode_pattern($recipe_id=false, $match_all=false, $force_classic=false)
    {
        //even if on gutenberg, with elementor we have to use classic shortcodes.
        $gutenberg = Util::uses_gutenberg() && !Util::uses_elementor();
        $classic = !$gutenberg;
        if ($force_classic || $classic) {
            if ($recipe_id){
                return '/(\[amd-zlrecipe-recipe:'.$recipe_id.'\])/i';
            }
            if ($match_all){
                return '/(\[amd-zlrecipe-recipe:.*?\])/i';
            }
            return '/\[amd-zlrecipe-recipe:([0-9]\d*).*?\]/i';
        } else {
            if ($recipe_id){
                return '/<!-- wp:zip-recipes\/recipe-block {"id":"'.$recipe_id.'".*?} \/-->/i';
            }
            if ($match_all){
                return '/(<!-- wp:zip-recipes\/recipe-block {.*?} \/-->)/i';
            }
            return '/<!-- wp:zip-recipes\/recipe-block {.*?"id":"([0-9]\d*)".*?} \/-->/i';
        }
    }

    /**
     * Render PHP template
     * @param string $file
     * @param array $options
     * @param string|bool $pluginDir
     * @return string $html
     */

	public static function render_template($file, $options=array(), $pluginDir=false){

	    if (!$pluginDir) {
            $trace = debug_backtrace();
            $caller = $trace[2]; // 0 here is direct caller of _view, 1 would be our Util class so we want 2

            $pluginDir = "";
            if (isset($caller['class'])) {
                $classComponents = explode("\\", $caller['class']);

                $class = $classComponents[count($classComponents) - 1];
                $pluginDir = $class;
            }
        }

        if ($pluginDir ) {
            if (file_exists(ZRDN_PLUGIN_DIRECTORY."plugins/$pluginDir/")) $pluginDir = "plugins/$pluginDir/";
        }

        $viewDir = ZRDN_PLUGIN_DIRECTORY . $pluginDir . 'views/';

        $theme_file = trailingslashit(get_stylesheet_directory()) . dirname(ZRDN_PLUGIN_DIRECTORY) . $file;

        $plugin_file = $viewDir . $file;

        $file = file_exists($theme_file) ? $theme_file :$plugin_file;

        if (strpos($file, '.php') !== FALSE) {
            ob_start();
            require $file;
            $contents = ob_get_clean();
        } else {
            $contents = file_get_contents($file);
        }

        if (count($options)>0){
            foreach($options as $placeholder => $value){

                if (strpos($contents,'{/'.$placeholder.'}')!==FALSE){

                    $value = ($value==='true' || $value==1 || $value) ? true : false;

                    if (!$value){
                        //remove the entire string
                        $contents = preg_replace('/{'.$placeholder.'}.*?{\/'.$placeholder.'}/s', '', $contents);
                    } else {
                        //only remove the placeholders
                        $contents = str_replace(array('{'.$placeholder.'}','{/'.$placeholder.'}'),'', $contents);
                    }
                } else {
                    $contents = str_replace('{'.$placeholder.'}', $value, $contents);
                }
            }
        }


        return $contents;
    }

    /**
     * Get the number of recipes on this site
     * @return int
     */

    public static function count_recipes($args=array()){
        $default_args = array(
            'search' =>'',
        );
        $args = wp_parse_args($args, $default_args);

        global $wpdb;
        $search_sql = '';
        if (strlen($args['search'])>0){
            $search_sql = $wpdb->prepare(" AND recipe_title like %s", $args['search']);
        }
        $table = $wpdb->prefix . "amd_zlrecipe_recipes";
        $count = $wpdb->get_var("SELECT count(*) FROM $table WHERE 1=1 $search_sql ");
        return intval($count);
    }

	public static function iso8601toHoursMinutes($time) {
		try {
			if ($time) {
				$date = new \DateInterval($time);
				$minutes = $date->i;
				$hours = $date->h;
			}

			return array($hours, $minutes);
		} catch (\Exception $e) {
			return null;
		}
	}

    /**
     * validate a time string, make sure a valid string is returned.
     * @param $time_str
     * @return string
     */


    public static function validate_time($time_str){
        $pattern="/PT[0-9]{1,2}H[0-9]{1,2}M/i";

        if (preg_match($pattern, $time_str, $matches) === 1 && $matches[0] === $time_str) {
            return $time_str;
        }

        return 'PT0H0M';
    }

    /**
     * Render view and echo it.
     *
     * @param string $name  name of html view to be found in views/ directory. Doesn't contain .html extension.
     * @param array $args object View context parameters.
     * @return string Rendered view.
     */
    public static function _view($name, $args = array()) {
        $trace = debug_backtrace();
        $caller = $trace[2]; // 0 here is direct caller of _view, 1 would be our Util class so we want 2

        $plugin_name = "";
        if (isset($caller['class'])) {
            $classComponents = explode("\\", $caller['class']);
            $class = $classComponents[count($classComponents) - 1];
            $plugin_name = $class;
        }

        $pluginDir = "";
        // don't consider core class a plugin
        if ($plugin_name && $plugin_name !== "ZipRecipes") { // TODO: ZipRecipes is hardcoded and needs to change
            $pluginDir = "plugins/$plugin_name/";
        }

        $viewDir = ZRDN_PLUGIN_DIRECTORY . $pluginDir . 'views/';
        $file = $name . '.twig';

        $uploads = wp_upload_dir();
        $uploads_dir = trailingslashit($uploads['basedir']);

        if (!file_exists($uploads_dir . 'zip-recipes/')){
            mkdir($uploads_dir . 'zip-recipes/');
        }

        if (!file_exists($uploads_dir . 'zip-recipes/cache/')) {
            mkdir($uploads_dir . 'zip-recipes/cache/');
        }

        $cacheDir = false;
        if (is_writable($uploads_dir . 'zip-recipes/cache')) {
            $cacheDir = $uploads_dir . 'zip-recipes/cache';
        }

        //fallback own plugin directory
        if (!$cacheDir) {
            if (is_writable($viewDir) || chmod($viewDir, 0660)) {
                $cacheDir = "${viewDir}cache";
            }
        }

        Util::log("Looking for template in dir:" . $viewDir);
        Util::log("Template name:" . $file);

        $loader = new \Twig_Loader_Filesystem(array($viewDir, ZRDN_PLUGIN_DIRECTORY . 'views/'));

        $twig_settings = array(
            'autoescape' => true,
            'auto_reload' => true
        );
        //if ($cacheDir) $twig_settings['cache'] = $cacheDir;

        $twig = new \Twig_Environment($loader, $twig_settings);

        $twig->addFunction( '__', new \Twig_SimpleFunction( '__', function ( $text ) {
            return __( $text, 'zip-recipes' );
        } ) );
        return $twig->render($file, $args);
    }

    public static function print_view($name, $args = array()) {
        echo self::_view($name, $args);
    }

    public static function view($name, $args = array()) {
        return self::_view($name, $args);
    }


    public static function get_charset_collate() {
        global $wpdb;

        $charset_collate = '';

        if (!empty($wpdb->charset))
            $charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
        if (!empty($wpdb->collate))
            $charset_collate .= " COLLATE $wpdb->collate";

        return $charset_collate;
    }

    // Get value of an array key
    // Used to suppress warnings if key doesn't exist
    public static function get_array_value($key, $array) {
        if (isset($array[$key])) {
            return $array[$key];
        }

        return null;
    }

    /**
     * Get list of installed plugins as a string. Each plugin is separated with ;
     */
    public static function zrdn_get_installed_plugins() {
        $pluginsString = '';
        $plugins = get_plugins();
        foreach ($plugins as $path => $pluginData) {
            // if you update the delimiter here, ensure the api.ziprecipes.net changes as well
            $pluginsString .= $pluginData['Name'] . "|";
        }

        return $pluginsString;
    }

    /**
     * Get all recipes
     * @param array $args
     * @return array $recipes
     */

    public static function get_recipes($args=array()){
        $default_args = array(
            'post_id'=>false,
            'offset' => 0,
            'number' => 20,
            'order_by' => 'recipe_title',
            'search' =>'',
            'searchFields' => 'title',
            'orderby' => 'recipe_title',
            'order' => 'ASC',
            'post_status' => 'all',
            'category' => 'all',
        );
        $args = wp_parse_args($args, $default_args);
        $pagesize = intval($args['number']);
        $offset = $args['offset'];
        $orderby = $args['orderby'];
        $order = $args['order'];
        global $wpdb;
        $search_sql = '';
        if (strlen($args['search'])>0){
            if ($args['searchFields']==='all'){
                $fields = array(
                    'recipe_title',
                    'ingredients',
                    'instructions',
                    'summary',
                    'notes',
                    'cuisine',
                );
            } else {
                $fields = array('recipe_title');
            }
            $search = sanitize_text_field($args['search']);
            $search_sql = " AND (".implode(" like '%$search%' OR ", $fields)." like '%$search%')";
        }

        $offset = $args['number']!=-1 ? $offset = "LIMIT $offset, $pagesize" : '';

        $table = $wpdb->prefix . "amd_zlrecipe_recipes";
        if ($args['category']!=='all') {
            //get by category slug
            $term = get_category_by_slug( sanitize_title($args['category']) );
            $category_id = $term ? $term->term_id : false;            //if not found, default back to all
            if (!$category_id) $args['category'] = 'all';
        }

        if ($args['category']==='all') {
            if ($args['post_status'] === 'publish') {
                $sql = "SELECT * FROM $table INNER JOIN $wpdb->posts ON $table.post_id = $wpdb->posts.ID where $wpdb->posts.post_status='publish'";
            } else {
                $sql = "SELECT * FROM $table where 1=1 ";
            }
        } else {
            $sql = $wpdb->prepare("select * from $table INNER JOIN (select $wpdb->posts.* from $wpdb->posts inner join (select $wpdb->term_taxonomy.term_taxonomy_id, $wpdb->term_relationships.object_id from $wpdb->term_relationships inner join $wpdb->term_taxonomy on $wpdb->term_relationships.term_taxonomy_id=$wpdb->term_taxonomy.term_taxonomy_id where  $wpdb->term_taxonomy.taxonomy='category') as cats ON $wpdb->posts.ID = cats.object_id where cats.term_taxonomy_id = %s) as p ON $table.post_id = p.ID where 1=1 ", $category_id);
            if ($args['post_status'] === 'publish') {
                $sql .= " AND p.post_status='publish'";
            }
        }
        $recipes = $wpdb->get_results("$sql $search_sql ORDER BY $orderby $order $offset ");
        return $recipes;
    }

    public static function get_recipe_categories(){

        $recipes  = self::get_recipes();
        $categories = array();
        foreach ($recipes as $recipe){
            if (!empty($recipe->post_id)){
                $categories += wp_get_post_categories($recipe->post_id);
            }
        }

        return $categories;
    }

    /**
     * Log messages if WP_DEBUG is set.
     * @param $message String Message to log.
     */
    public static function log($message) {
        if (!WP_DEBUG) {
            return;
        }

        $trace = debug_backtrace();

        $traceIndex = 1;
        $caller = $trace[$traceIndex];

        $output = "";

        do {
            $className = array_key_exists('class', $caller) ? $caller['class'] : "";
            $functionName = array_key_exists('function', $caller) ? $caller['function'] : "";
            $file = array_key_exists('file', $caller) ? $caller['file'] : "";
            $lineNumber = array_key_exists('line', $caller) ? $caller['line'] : "";

            $prefix = $traceIndex === 1 ? "ZRDN: " : "";
            $message = $traceIndex === 1 ? ": $message" : "";

            $output .= str_repeat("\t", $traceIndex - 1) . "$prefix$className $functionName" . $message . "\n";
            if ($file && $lineNumber) {
                $output .= str_repeat("\t", $traceIndex) . " from $file:$lineNumber" . "\n";
            }

            if (array_key_exists(++$traceIndex, $trace)) {
                $caller = $trace[$traceIndex];
            } else {
                $caller = null;
            }
        } while ($caller);

    }

}

/**
 * @param $key
 * @param $arr
 * @param bool $keys_are_objects If array keys are objects. Default: false.
 */
function array_by_key($key, $arr, $keys_are_objects=false) {
	return array_reduce(
		$arr,
		function ($carry, $recipe) use ($keys_are_objects, $key) {
			if ($keys_are_objects) {
				$needle =  $recipe->{ $key };
			}
			else {
				$needle = $recipe[ $key ];
			}

			if (array_key_exists($needle, $carry)) {
				$carry[ $needle ][ ] = $recipe;
			}
			else {
				$carry[ $needle ] = array($recipe);
			}

			return $carry;
		},
		array()
	);
}
