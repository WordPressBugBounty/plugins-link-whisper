<?php

/**
 * Recipe editor
 *
 * Class Wpil_Editor_WPRecipe
 */
class Wpil_Editor_WPRecipe
{

    private static $action_tracker = array();

    /**
     * Obtains the WP Recipe content from the fields that the user has selected.
     * Not intended for saving!
     **/
    public static function getPostContent($post_id){
        $content = '';

        // try getting the fields that are available
        $fields = self::get_selected_fields();

        // if we have fields
        if(!empty($fields)){
            // look over each of them
            foreach($fields as $field_name => $field_indexes){
                // try pulling the data for them
                $field_data = get_post_meta($post_id, $field_name, true);
                if(empty($field_data)){
                    continue;
                }

                // if we're dealing with an array of indexes
                if(is_array($field_indexes)){
                    // go over each index
                    foreach($field_indexes as $index => $val){
                        // "unkey" the index so we can search the arrayy data for it
                        $ind = str_replace('wprm_', '', $index);
                        // and over each data row
                        foreach($field_data as $key => $data){
                            $field_key = str_replace('wprm_', '', $field_name);
                            if(!empty($field_key) && is_array($data) && isset($data[$field_key])){
                                foreach($data[$field_key] as $inst_index => $inst_data){
                                    // and check to see if there's text in our selected index
                                    if(isset($inst_data[$ind]) && is_string($inst_data[$ind]) && !empty($inst_data[$ind])){
                                        $content .= "\n" . $inst_data[$ind];
                                    }
                                }
                            }else{
                                // and check to see if there's text in our selected index
                                if(isset($data[$ind]) && is_string($data[$ind]) && !empty($data[$ind])){
                                    $content .= "\n" . $data[$ind];
                                }
                            }
                        }
                    }
                }elseif(is_string($field_data) && !empty($field_data)){
                    $content .= "\n" . $field_data;
                }
            }
        }

        return $content;
    }

    public static function get_insertable_fields(){
        return array(
            'wprm_notes' => 'Recipe Notes', // Title strings for simple data, array for data with subfields
            'wprm_equipment' => array('name' => __('Equipment Name', ''), 'notes' => __('Equipment Notes', '')),
            'wprm_ingredients' => array('name' => __('Ingredients Name', ''), 'notes' => __('Ingredients Notes', '')),
            'wprm_instructions' => array('name' => __('Instructions Name', ''), 'text' => __('Instructions Notes', '')) // the 'instructions' call the instructing text 'text' in the database, but we'll keep calling it notes for consistency
        );
    }

    public static function get_selected_fields($unkey_indexes = false){
        $fields = get_option('wpil_suggestion_wp_recipe_fields', array('wprm_notes' => 'Recipe Notes'));

        if($unkey_indexes){
            $fields = self::unkey_indexes($fields);
        }

        return $fields;
    }

    private static function unkey_indexes($fields){
        if(!is_array($fields)){
            return $fields;
        }

        $rekeyed = array();
        foreach($fields as $index => $data){
            $ind = str_replace('wprm_', '', $index);
            if(is_array($data)){
                $rekeyed[$ind] = self::unkey_indexes($data);
            }else{
                $rekeyed[$ind] = $data;
            }
        }

        return $rekeyed;
    }

    public static function wprm_active($post_id = false){
        $active = defined('WPRM_POST_TYPE') && in_array('wprm_recipe', Wpil_Settings::getPostTypes());

        if(empty($post_id) || empty($active)){
            return $active;
        }
        return ('wprm_recipe' === get_post_type($post_id));
    }

    /**
     * Tracks if something happened inside this instance so that we don't pull from the global scope using the main tracker.
     * That way, we can only update the fields that need updating and we can skip over the rest.
     **/
    private static function track_action($action = '', $value = false){
        if(empty($action) || !is_string($action)){
            return;
        }

        if(isset(self::$action_tracker[$action]) && !empty(self::$action_tracker[$action])){
            self::$action_tracker[$action] = $value;
        }elseif(!array_key_exists($action, self::$action_tracker)){
            self::$action_tracker[$action] = $value;
        }
    }

    private static function action_happened($action = '', $return_result = true){
        if(empty($action) || !is_string($action)){
            return false;
        }

        $logged = array_key_exists($action, self::$action_tracker);

        if(!$logged){
            return false;
        }

        return ($return_result) ? self::$action_tracker[$action]: $logged;
    }

    private static function clear_tracked_action($action = ''){
        if(empty($action) || !is_string($action)){
            return;
        }

        if(array_key_exists($action, self::$action_tracker)){
            unset(self::$action_tracker[$action]);
        }
    }

}