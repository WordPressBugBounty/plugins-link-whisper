<?php

/**
 * Divi single TEMPLATE editor
 * Since Divi uses a complex templating system in it's theme builder, we need to have a specific editor class to support it.
 * The non-template Divi content is still handled by the normal processes.
 *
 * Class Wpil_Editor_Divi
 */
class Wpil_Editor_Divi
{
    public static $force_insert_link;
    public static $divi_active = null;

    public static function get_divi_template_id($post_id = 0){
        global $wpdb;

        if(!self::divi_active() || empty($post_id)){
            return false;
        }

        $metas = $wpdb->get_results("SELECT * FROM $wpdb->postmeta WHERE `meta_key` = '_et_use_on'");
    
        $ids = array();
        if(!empty($metas)){
            foreach($metas as $meta){
                if(false !== strpos($meta->meta_value, ':id:' . $post_id)){
                    $ids[] = $meta->post_id;
                }
            }
        }

        if(!empty($ids)){
            $posts = $wpdb->get_results($wpdb->prepare("SELECT * FROM $wpdb->postmeta WHERE `post_id` = %d AND `meta_key` = '_et_body_layout_id'", max($ids)));
            if(!empty($posts)){
                $ids = array();
                foreach($posts as $post){
                    $ids[] = $post->meta_value;
                }

                if(!empty($ids)){
                    return max($ids);
                }
            }
        }

        return false;
    }

    /**
     * Gets the Divi Template content for making suggestions
     *
     * @param int $post_id The id of the post that we're trying to get information for.
     */
    public static function getContent($post_id)
    {
        global $wpdb;
        $content = '';

        if(!self::divi_active()){
            return $content;
        }

        $template_id = self::get_divi_template_id($post_id);

        if(!empty($template_id)){
            $layout = $wpdb->get_row($wpdb->prepare("SELECT * FROM $wpdb->posts WHERE `ID` = %d", $template_id));
            if(!empty($layout) && !empty($layout->post_content)){
                $content = $layout->post_content;
            }
        }

        return $content;
    }

    public static function divi_active(){
        if(!is_null(self::$divi_active)){
            return self::$divi_active;
        }

        self::$divi_active = (defined('ET_SHORTCODES_VERSION')) ? true: false;

        return self::$divi_active;
    }
}