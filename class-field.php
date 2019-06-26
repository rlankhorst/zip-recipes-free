<?php
namespace ZRDN;
defined('ABSPATH') or die("you do not have acces to this page!");

if (!class_exists("ZRDN_Field")) {
    class ZRDN_Field
    {
        private static $_this;
        public $position;
        public $fields;
        public $default_args;
        public $form_errors = array();

        function __construct()
        {
            if (isset(self::$_this))
                wp_die(sprintf(__('%s is a singleton class and you cannot create a second instance.', 'zip-recipes'), get_class($this)));

            self::$_this = $this;

            add_action('zrdn_register_translation', array($this, 'register_translation'), 10, 2);

            add_action('zrdn_before_label', array($this, 'before_label'), 10, 1);
            add_action('zrdn_before_label', array($this, 'show_errors'), 10, 1);
            add_action('zrdn_after_label', array($this, 'after_label'), 10, 1);
            add_action('zrdn_after_field', array($this, 'after_field'), 10, 1);

            $this->load();
        }

        static function this()
        {
            return self::$_this;
        }



        /**
         * Register each string in supported string translation tools
         *
         */

        public function register_translation($fieldname, $string)
        {
            //polylang
            if (function_exists("pll_register_string")) {
                pll_register_string($fieldname, $string, 'complianz');
            }

            //wpml
            if (function_exists('icl_register_string')) {
                icl_register_string('complianz', $fieldname, $string);
            }

            do_action('wpml_register_single_string', 'complianz', $fieldname, $string);
        }


        public function load()
        {
            $this->default_args = array(
                "fieldname" => '',
                "type" => 'text',
                "required" => false,
                'default' => '',
                'label' => '',
                'table' => false,
                'callback_condition' => false,
                'condition' => false,
                'callback' => false,
                'placeholder' => '',
                'optional' => false,
                'disabled' => false,
                'hidden' => false,
                'region' => false,
                'media' => true,
                'first' => false,
                'warn' => false,
                'value' => '',
            );
        }

        /**
         * santize an array for save storage
         *
         * @param $array
         * @return mixed
         */

        public function sanitize_array($array)
        {
            foreach ($array as &$value) {
                if (!is_array($value))
                    $value = sanitize_text_field($value);
                //if ($value === 'on') $value = true;
                else
                    $this->sanitize_array($value);
            }

            return $array;
        }
        

        public function sanitize($value, $type)
        {

            switch ($type) {
                case 'colorpicker':
                    return sanitize_hex_color($value);
                case 'text':
                    return sanitize_text_field($value);
                case 'multicheckbox':
                    if (!is_array($value)) $value = array($value);
                    return array_map('sanitize_text_field', $value);
                case 'phone':
                    $value = sanitize_text_field($value);
                    return $value;
                case 'email':
                    return sanitize_email($value);
                case 'css':
                    return $value;
                case 'url':
                    return esc_url_raw($value);
                case 'number':
                    return intval($value);
                case 'editor':
                case 'textarea':
                    return wp_kses_post($value);
            }
            return sanitize_text_field($value);
        }

        public
        function before_label($args)
        {

            $condition = false;
            $condition_question = '';
            $condition_answer = '';

            if (!empty($args['condition'])) {
                $condition = true;
                $condition_answer = reset($args['condition']);
                $condition_question = key($args['condition']);
            }
            $condition_class = $condition ? 'condition-check' : '';

            $hidden_class =  ($args['hidden']) ? 'hidden' : '';
            $first_class =  ($args['first']) ? 'first' : '';

            $this->get_master_label($args);

            if ($args['table']) {
                echo '<tr class="zrdn-settings field-group' . esc_attr($hidden_class.' '.$condition_class) . '"';
                echo $condition ? 'data-condition-question="' . esc_attr($condition_question) . '" data-condition-answer="' . esc_attr($condition_answer) . '"' : '';
                echo '><th scope="row">';
            } else {
                echo '<div class="field-group ' .  esc_attr($hidden_class.''.$first_class.' '.$condition_class) . '" ';
                echo $condition ? 'data-condition-question="' . esc_attr($condition_question) . '" data-condition-answer="' . esc_attr($condition_answer) . '"' : '';
                echo '><div class="zrdn-label">';
            }
        }

        public function get_master_label($args)
        {
            if (!isset($args['master_label'])) return;
            ?>
            <div class="zrdn-master-label"><?php echo esc_html($args['master_label']) ?></div>
            <hr>
            <?php

        }

        public
        function show_errors($args)
        {
            if (in_array($args['fieldname'], $this->form_errors)) {
                ?>
                <div class="zrdn-form-errors">
                    <?php _e("This field is required. Please complete the question before continuing", 'complianz-gdpr') ?>
                </div>
                <?php
            }
        }

        public
        function after_label($args)
        {
            if ($args['table']) {
                echo '</th><td>';
            } else {
                echo '</div><div class="zrdn-field">';
            }

            do_action('zrdn_notice_' . $args['fieldname'], $args);

        }

        public
        function after_field($args)
        {
            $this->get_comment($args);

            if ($args['table']) {
                echo '</td><td>'.$this->get_help_tip($args).'</td></tr>';
            } else {
                echo '</div>'.$this->get_help_tip($args).'</div>';
            }
        }


        public
        function text($args)
        {
            $fieldname = 'zrdn_' . $args['fieldname'];

            $value = $args['value'];

            ?>

            <?php do_action('zrdn_before_label', $args); ?>
            <label for="<?php echo $args['fieldname'] ?>"><?php echo $args['label'] ?><?php echo $this->get_help_tip_btn($args);?></label>
            <?php do_action('zrdn_after_label', $args); ?>
            <input <?php if ($args['required']) echo 'required'; ?>
                class="validation zrdn-field-input <?php if ($args['required']) echo 'is-required'; ?>"
                placeholder="<?php echo esc_html($args['placeholder']) ?>"
                type="text"
                value="<?php echo esc_html($value) ?>"
                name="<?php echo esc_html($fieldname) ?>">
            <?php do_action('zrdn_after_field', $args); ?>
            <?php
        }


        public
        function hidden($args)
        {
            $fieldname = 'zrdn_' . $args['fieldname'];
            $value = $args['value'];
            ?>

            <input
                    class="validation zrdn-field-input "
                    type="hidden"
                    value="<?php echo esc_html($value) ?>"
                    name="<?php echo esc_html($fieldname) ?>">
            <?php
        }

        public
        function url($args)
        {
            $fieldname = 'zrdn_' . $args['fieldname'];
            $value = $args['value'];
            ?>

            <?php do_action('zrdn_before_label', $args); ?>
            <label for="<?php echo $args['fieldname'] ?>"><?php echo $args['label'] ?><?php echo $this->get_help_tip_btn($args);?></label>
            <?php do_action('zrdn_after_label', $args); ?>
            <input <?php if ($args['required']) echo 'required'; ?>
                class="validation <?php if ($args['required']) echo 'is-required'; ?>"
                placeholder="<?php echo esc_html($args['placeholder']) ?>"
                type="text"
                pattern="^(http(s)?(:\/\/))?(www\.)?[a-zA-Z0-9-_\.\/]+"
                value="<?php echo esc_html($value) ?>"
                name="<?php echo esc_html($fieldname)?>">
            <?php do_action('zrdn_after_field', $args); ?>
            <?php
        }

        public
        function email($args)
        {
            $fieldname = 'zrdn_' . $args['fieldname'];
            $value = $args['value'];
            ?>

            <?php do_action('zrdn_before_label', $args); ?>
            <label for="<?php echo $args['fieldname'] ?>"><?php echo $args['label'] ?><?php echo $this->get_help_tip_btn($args);?></label>
            <?php do_action('zrdn_after_label', $args); ?>
            <input <?php if ($args['required']) echo 'required'; ?>
                class="validation <?php if ($args['required']) echo 'is-required'; ?>"
                placeholder="<?php echo esc_html($args['placeholder']) ?>"
                type="email"
                value="<?php echo esc_html($value) ?>"
                name="<?php echo esc_html($fieldname)?>">
            <?php do_action('zrdn_after_field', $args); ?>
            <?php
        }

        public
        function phone($args)
        {
            $fieldname = 'zrdn_' . $args['fieldname'];
            $value = $args['value'];
            ?>

            <?php do_action('zrdn_before_label', $args); ?>
            <label for="<?php echo $args['fieldname'] ?>"><?php echo $args['label'] ?><?php echo $this->get_help_tip_btn($args);?></label>
            <?php do_action('zrdn_after_label', $args); ?>
            <input autocomplete="tel" <?php if ($args['required']) echo 'required'; ?>
                   class="validation <?php if ($args['required']) echo 'is-required'; ?>"
                   placeholder="<?php echo esc_html($args['placeholder']) ?>"
                   type="text"
                   value="<?php echo esc_html($value) ?>"
                   name="<?php echo esc_html($fieldname) ?>">
            <?php do_action('zrdn_after_field', $args); ?>
            <?php
        }

        public
        function number($args)
        {
            $fieldname = 'zrdn_' . $args['fieldname'];
            $value = $args['value'];
            ?>

            <?php do_action('zrdn_before_label', $args); ?>
            <label for="<?php echo $args['fieldname'] ?>"><?php echo $args['label'] ?><?php echo $this->get_help_tip_btn($args);?></label>
            <?php do_action('zrdn_after_label', $args); ?>
            <input <?php if ($args['required']) echo 'required'; ?>
                class="validation <?php if ($args['required']) echo 'is-required'; ?>"
                placeholder="<?php echo esc_html($args['placeholder']) ?>"
                type="number"
                value="<?php echo esc_html($value) ?>"
                name="<?php echo esc_html($fieldname) ?>">
            <?php do_action('zrdn_after_field', $args); ?>
            <?php
        }


        public
        function time($args)
        {
            $fieldname = 'zrdn_' . $args['fieldname'];
            $value = $args['value'];
            $time = ZipRecipes:: zrdn_extract_time($value);
            $hours = $time['time_hours'];
            $minutes = $time['time_minutes'];

            ?>

            <?php do_action('zrdn_before_label', $args); ?>
            <label for="<?php echo $args['fieldname'] ?>"><?php echo $args['label'] ?><?php echo $this->get_help_tip_btn($args);?></label>
            <?php do_action('zrdn_after_label', $args); ?>
            <input <?php if ($args['required']) echo 'required'; ?>
                    class="validation zrdn-field-hour <?php if ($args['required']) echo 'is-required'; ?>"
                    placeholder="<?php echo esc_html($args['placeholder']) ?>"
                    type="number"
                    min="0" max="48"
                    value="<?php echo esc_html($hours) ?>"
                    name="<?php echo esc_html($fieldname) ?>_hours">
            <?php _e("(h)","zip-recipes")?>

            <input <?php if ($args['required']) echo 'required'; ?>
                    class="validation zrdn-field-minute <?php if ($args['required']) echo 'is-required'; ?>"
                    placeholder="<?php echo esc_html($args['placeholder']) ?>"
                    type="number"
                    min="0" max="59"
                    value="<?php echo esc_html($minutes) ?>"
                    name="<?php echo esc_html($fieldname) ?>_minutes">
            <?php _e("(m)","zip-recipes")?>
            <?php do_action('zrdn_after_field', $args); ?>
            <?php
        }


        public
        function checkbox($args)
        {
            $fieldname = 'zrdn_' . $args['fieldname'];

            $value = $args['value'];
            $placeholder_value = ($args['disabled'] && $value) ? $value : 0;

            ?>
            <?php do_action('zrdn_before_label', $args); ?>

            <label for="<?php echo esc_html($fieldname) ?>-label"><?php echo $args['label'] ?><?php echo $this->get_help_tip_btn($args);?></label>

            <?php do_action('zrdn_after_label', $args); ?>

            <label class="zrdn-switch">
                <input name="<?php echo esc_html($fieldname) ?>" type="hidden" value="<?php echo $placeholder_value?>"/>

                <input name="<?php echo esc_html($fieldname) ?>" size="40" type="checkbox"
                    <?php if ($args['disabled']) echo 'disabled'; ?>
                       class="<?php if ($args['required']) echo 'is-required'; ?>"
                       value="1" <?php checked(1, $value, true) ?> />
                <span class="zrdn-slider zrdn-round"></span>
            </label>

            <?php do_action('zrdn_after_field', $args); ?>
            <?php
        }


        public
        function radio($args)
        {
            $fieldname = 'zrdn_' . $args['fieldname'];
            $options = $args['options'];

            $value = $args['value'];

            ?>
            <?php do_action('zrdn_before_label', $args); ?>

            <label for="<?php echo $args['fieldname'] ?>"><?php echo $args['label'] ?><?php echo $this->get_help_tip_btn($args);?></label>

            <?php do_action('zrdn_after_label', $args); ?>
            <div class="zrdn-validate-radio">
                <?php
                if (!empty($options)) {
                    if ($args['disabled']) echo '<input type="hidden" value="'.$args['default'].'" name="'.$fieldname.'">';
                    foreach ($options as $option_value => $option_label) {
                        ?>
                        <input <?php if ($args['disabled']) echo "disabled"?>
                            <?php if ($args['required']) echo "required"; ?>
                            type="radio"
                            id="<?php echo esc_html($fieldname) ?>"
                            name="<?php echo esc_html($fieldname) ?>"
                            value="<?php echo esc_html($option_value); ?>" <?php if ($value == $option_value) echo "checked" ?>>
                        <label class="">
                            <?php echo esc_html($option_label); ?>
                        </label>
                        <div class="clear"></div>
                    <?php }
                }
                ?>
            </div>

            <?php do_action('zrdn_after_field', $args); ?>
            <?php
        }
        


        public
        function textarea($args)
        {
            $fieldname = 'zrdn_' . $args['fieldname'];

            $value = $args['value'];
            ?>
            <?php do_action('zrdn_before_label', $args); ?>
            <label for="<?php echo $args['fieldname'] ?>"><?php echo esc_html($args['label']) ?><?php echo $this->get_help_tip_btn($args);?></label>
            <?php do_action('zrdn_after_label', $args); ?>
            <textarea name="<?php echo esc_html($fieldname) ?>"
                      <?php if ($args['required']) echo 'required'; ?>
                        class="validation zrdn-field-textarea <?php if ($args['required']) echo 'is-required'; ?>"
                      placeholder="<?php echo esc_html($args['placeholder']) ?>"><?php echo esc_html($value) ?></textarea>
            <?php do_action('zrdn_after_field', $args); ?>
            <?php
        }

        /*
         * Show field with editor
         *
         *
         * */

        public
        function editor($args)
        {
            $fieldname = 'zrdn_' . $args['fieldname'];
            $args['first'] = true;
            $media = $args['media'] ? true : false;

            $value = $args['value'];
            ?>
            <?php do_action('zrdn_before_label', $args); ?>
            <label for="<?php echo $args['fieldname'] ?>"><?php echo esc_html($args['label']) ?><?php echo $this->get_help_tip_btn($args);?></label>
            <?php do_action('zrdn_after_label', $args); ?>
            <?php
            $settings = array(
                'media_buttons' => $media,
                'editor_height' => 300,
                'textarea_rows' => 15,
                'teeny'=>true,
            );
            wp_editor($value, $fieldname, $settings); ?>
            <?php do_action('zrdn_after_field', $args); ?>
            <?php
        }

        public
        function css($args)
        {
            $fieldname = 'zrdn_' . $args['fieldname'];

            $value = $args['value'];
            ?>

            <?php do_action('zrdn_before_label', $args); ?>
            <label for="<?php echo $args['fieldname'] ?>"><?php echo esc_html($args['label']) ?><?php echo $this->get_help_tip_btn($args);?></label>
            <?php do_action('zrdn_after_label', $args); ?>
            <div id="<?php echo esc_html($fieldname) ?>editor"
                 style="height: 200px; width: 100%"><?php echo $value ?></div>
            <?php do_action('zrdn_after_field', $args); ?>
            <script>
                var <?php echo esc_html($fieldname)?> =
                ace.edit("<?php echo esc_html($fieldname)?>editor");
                <?php echo esc_html($fieldname)?>.setTheme("ace/theme/monokai");
                <?php echo esc_html($fieldname)?>.session.setMode("ace/mode/css");
                jQuery(document).ready(function ($) {
                    var textarea = $('textarea[name="<?php echo esc_html($fieldname)?>"]');
                    <?php echo esc_html($fieldname)?>.
                    getSession().on("change", function () {
                        textarea.val(<?php echo esc_html($fieldname)?>.getSession().getValue()
                    )
                    });
                });
            </script>
            <textarea style="display:none" name="<?php echo esc_html($fieldname) ?>"><?php echo $value ?></textarea>
            <?php
        }


        public
        function colorpicker($args)
        {
            $fieldname = 'zrdn_' . $args['fieldname'];

            $value = $args['value'];

            ?>
            <?php do_action('zrdn_before_label', $args); ?>
            <label for="<?php echo esc_html($fieldname) ?>"><?php echo esc_html($args['label']) ?><?php echo $this->get_help_tip_btn($args);?></label>
            <?php do_action('zrdn_after_label', $args); ?>
            <input type="hidden" name="<?php echo esc_html($fieldname) ?>" id="<?php echo esc_html($fieldname) ?>"
                   value="<?php echo esc_html($value) ?>" class="zrdn-color-picker-hidden">
            <input type="text" name="color_picker_container" data-hidden-input='<?php echo esc_html($fieldname) ?>'
                   value="<?php echo esc_html($value) ?>" class="zrdn-color-picker"
                   data-default-color="<?php echo esc_html($args['default']) ?>">
            <?php do_action('zrdn_after_field', $args); ?>

            <?php
        }



        public
        function get_field_html($args)
        {
            $args = wp_parse_args($args, $this->default_args);
            $type = ($args['callback']) ? 'callback' : $args['type'];

            switch ($args['type']) {
                case 'callback':
                    $this->callback($args);
                    break;
                case 'button':
                    $this->button($args);
                    break;
                case 'upload':
                    $this->upload($args);
                    break;
                case 'url':
                    $this->url($args);
                    break;
                case 'select':
                    $this->select($args);
                    break;
                case 'colorpicker':
                    $this->colorpicker($args);
                    break;
                case 'checkbox':
                    $this->checkbox($args);
                    break;
                case 'textarea':
                    $this->textarea($args);
                    break;
                case 'cookies':
                    $this->cookies($args);
                    break;
                case 'multiple':
                    $this->multiple($args);
                    break;
                case 'radio':
                    $this->radio($args);
                    break;
                case 'multicheckbox':
                    $this->multicheckbox($args);
                    break;
                case 'javascript':
                    $this->javascript($args);
                    break;
                case 'css':
                    $this->css($args);
                    break;
                case 'email':
                    $this->email($args);
                    break;
                case 'phone':
                    $this->phone($args);
                    break;
                case 'number':
                    $this->number($args);
                    break;
                case 'time':
                    $this->time($args);
                    break;
                case 'notice':
                    $this->notice($args);
                    break;
                case 'editor':
                    $this->editor($args);
                    break;
                case 'hidden':
                    $this->hidden($args);
                    break;
                case 'label':
                    $this->label($args);
                    break;
                default:
                    $this->text($args);
            }


        }

        public
        function callback($args)
        {
            $callback = $args['callback'];
            do_action("zrdn_$callback", $args);
        }

        public
        function notice($args)
        {
            do_action('zrdn_before_label', $args);
            zrdn_notice($args['label']);
            do_action('zrdn_after_label', $args);
            do_action('zrdn_after_field', $args);
        }

        public
        function select($args)
        {

            $fieldname = 'zrdn_' . $args['fieldname'];

            $value = $args['value'];

            ?>
            <?php do_action('zrdn_before_label', $args); ?>
            <label for="<?php echo esc_html($fieldname) ?>"><?php echo esc_html($args['label']) ?><?php echo $this->get_help_tip_btn($args);?></label>
            <?php do_action('zrdn_after_label', $args); ?>
            <select <?php if ($args['required']) echo 'required'; ?> name="<?php echo esc_html($fieldname) ?>">
                <option value=""><?php _e("Choose an option", 'complianz-gdpr') ?></option>
                <?php foreach ($args['options'] as $option_key => $option_label) { ?>
                    <option value="<?php echo esc_html($option_key) ?>" <?php echo ($option_key == $value) ? "selected" : "" ?>><?php echo esc_html($option_label) ?></option>
                <?php } ?>
            </select>

            <?php do_action('zrdn_after_field', $args); ?>
            <?php
        }

        public
        function label($args)
        {

            $fieldname = 'zrdn_' . $args['fieldname'];

            ?>
            <?php do_action('zrdn_before_label', $args); ?>
            <label for="<?php echo esc_html($fieldname) ?>"><?php echo esc_html($args['label']) ?><?php echo $this->get_help_tip_btn($args);?></label>
            <?php do_action('zrdn_after_label', $args); ?>

            <?php do_action('zrdn_after_field', $args); ?>
            <?php
        }

        /**
         *
         * Button/Action field
         * @param $args
         * @echo string $html
         */

        public
        function button($args)
        {
            $fieldname = 'zrdn_' . $args['fieldname'];

            ?>
            <?php do_action('zrdn_before_label', $args); ?>
            <label><?php echo esc_html($args['label']) ?><?php echo $this->get_help_tip_btn($args);?></label>
            <?php do_action('zrdn_after_label', $args); ?>
            <?php if ($args['post_get']==='get'){ ?>
            <a <?php if ($args['disabled']) echo "disabled"?> href="<?php echo $args['disabled'] ? "#" : admin_url('admin.php?page=zrdn-settings&action='.$args['action'])?>" class="button"><?php echo esc_html($args['label']) ?></a>
        <?php } else { ?>
            <input <?php if ($args['warn']) echo 'onclick="return confirm(\''.$args['warn'].'\');"'?> <?php if ($args['disabled']) echo "disabled"?> class="button" type="submit" name="<?php echo $args['action']?>"
                                                                                                                                                     value="<?php echo esc_html($args['label']) ?>">
        <?php }  ?>

            <?php do_action('zrdn_after_field', $args); ?>
            <?php
        }

        /**
         * Upload field
         * @param $args
         *
         * @echo string $html
         */

        public
        function upload($args)
        {

            ?>
            <?php do_action('zrdn_before_label', $args); ?>
            <label><?php echo esc_html($args['label']) ?><?php echo $this->get_help_tip_btn($args);?></label>
            <?php do_action('zrdn_after_label', $args); ?>

            <input type="file" type="submit" name="zrdn-upload-file"
                   value="<?php echo esc_html($args['label']) ?>">
            <input <?php if ($args['disabled']) echo "disabled"?> class="button" type="submit" name="<?php echo $args['action']?>"
                                                                  value="<?php _e('Start', 'complianz-gdpr') ?>">
            <?php do_action('zrdn_after_field', $args); ?>
            <?php
        }


        public
        function save_button()
        {
            wp_nonce_field('zrdn_save', 'zrdn_nonce');
            ?>
            <th></th>
            <td>
                <input class="button button-primary" type="submit" name="zrdn-save"
                       value="<?php _e("Save", 'complianz-gdpr') ?>">

            </td>
            <?php
        }








        /**
         * Checks if a fieldname exists in the complianz field list.
         *
         * @param string $fieldname
         * @return bool
         */

        public
        function sanitize_fieldname($fieldname)
        {
            $fields = COMPLIANZ()->config->fields();
            if (array_key_exists($fieldname, $fields)) return $fieldname;

            return false;
        }


        public
        function get_comment($args)
        {
            if (!isset($args['comment'])) return;
            ?>
            <div class="zrdn-comment"><?php echo $args['comment'] ?></div>
            <?php
        }

        /**
         *
         * returns the button with which a user can open the help modal
         *
         * @param array $args
         * @return string
         */

        public
        function get_help_tip_btn($args)
        {
            $output='';
            if (isset($args['help']) ) {
                //$output = '<a href="#" class="button zrdn-open-modal"><i class="fa fa-question"></i></a>';
            }
            return $output;
        }

        /**
         * returns the modal help window
         *
         * @param array $args
         * @return string
         */

        public
        function get_help_tip($args)
        {
            $output = '';
            if (isset($args['help'])) {
//                $output = '<div><div class="zrdn-help-modal"><span><i class="fa fa-times"></i></span>' . wp_kses_post($args['help']) . '</div></div>';
                $output = '<div><div class="zrdn-help-modal">' . wp_kses_post($args['help']) . '</div></div>';
            }
            return $output;
        }


        public
        function has_errors()
        {
            if (count($this->form_errors) > 0) {
                return true;
            }


            return false;
        }


    }
} //class closure