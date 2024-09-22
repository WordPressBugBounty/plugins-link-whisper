<?php

/**
 * YooTheme editor
 *
 * Class Wpil_Editor_YooTheme
 */
class Wpil_Editor_YooTheme
{
    public static $link_processed;
    public static $keyword_links_count;
    public static $link_confirmed;
    public static $document;
    public static $current_id;
    public static $remove_unprocessable = true;
    public static $force_insert_link;
    public static $yoo_active = null;
    public static $ignore_types = array(
        'button', 
        'button_item', 
        'headline', 
        'gallery',
        'gallery_item',
        'nav', 
        'nav_item',
        'overlay',
        'overlay-slider',
        'overlay-slider_item',
        'slideshow',
        'slideshow_item',
        'subnav',
        'subnav_item',
        'switcher',
        'switcher_item'
    );

    public static $pattern = '/<!--\s*?(\{(?:.*?)\})\s*?-->/';

    public static function matchContent($content)
    {
        return str_contains((string) $content, '<!--') &&
            preg_match(self::$pattern, $content, $matches)
            ? $matches[1]
            : null;
    }

    /**
     * Gets the Elementor content for making suggestions
     *
     * @param int $post_id The id of the post that we're trying to get information for.
     */
    public static function getContent($post_id, $remove_unprocessable = true, $return_json = false)
    {
        $post = get_post($post_id);
        $content = '';

        if(empty($post) || empty($post->post_content)){
            return $content;
        }

        $pulled_content = self::matchContent($post->post_content);

        if(empty($pulled_content)){
            return $content;
        }

        $pulled_content = json_decode($pulled_content);

        if(empty($pulled_content) || !is_object($pulled_content)){
            return $content;
        }

        if($return_json){
            return $pulled_content;
        }

        self::$remove_unprocessable = $remove_unprocessable;
        self::getProcessableData($pulled_content, $content, $post_id);
        self::$remove_unprocessable = true;

        return $content;
    }

    public static function clean_json($json_string) {
        // Unescape double quotes
        $json_string = preg_replace('/(?<!\\\\)\\\\(?=")/', '', $json_string);
        // Correct escaped characters (reduce double backslashes)
        $json_string = preg_replace('/\\\\\\\\(n|t|r|b|f|\')/', '\\\\$1', $json_string);
        return $json_string;
    }

    public static function add_extra_slash($json_string) {
        // Add an extra slash to escaped formatting characters
        $json_string = preg_replace('/(?<!\\\\)\\\\(n|t|r|b|f)/', '\\\\\\\\$1', $json_string);
        return $json_string;
    }

    /**
     * Checks the given item to see if its a heading and it can have links added to it.
     * @param object $item The Elementor item that we're going to check
     * @return bool
     **/
    public static function canAddLinksToHeading($item){
        if($item->widgetType !== 'heading'){
            return true; // possibly remove this. I'm returning true in case I accidentally use this somewhere that doesn't strictly check for headings, but this could allow false positives.
        }

        // if a custom heading element has been selected, and the element is a div, span, or p
        if(isset($item->settings) && isset($item->settings->header_size) && in_array($item->settings->header_size, array('div', 'span', 'p'))){
            // return that a link can be inserted here
            return true;
        }

        return false;
    }

    /**
     * Check certain text element
     *
     * @param $item
     * @param $params
     */
    public static function getProcessableData($item, &$content, $post_id)
    {
        if(self::$remove_unprocessable && isset($item->type) && in_array($item->type, self::$ignore_types)){
            return;
        }

        if(isset($item->children) && is_array($item->children)){
            foreach($item->children as $dat){
                if( isset($dat->props) && !empty($dat->props) &&
                    isset($dat->props->content) && !empty($dat->props->content) &&
                    isset($dat->type) && (!self::$remove_unprocessable && !in_array($dat->type, self::$ignore_types))
                ){
                    $content .= ("\n" . $dat->props->content);
                }

                if (isset($dat->children) && !empty($dat->children)) {
                    foreach ($dat->children as $e) {
                        self::getProcessableData($e, $content, $post_id);
                    }
                }
            }
        }

        if(isset($item->content) && !empty($item->content)){
            $content .= "\n" . $item->content;
        }

        if (isset($item->children) && !empty($item->children)) {
            foreach ($item->children as $element) {
                self::getProcessableData($element, $content, $post_id);
            }
        }
    }

    public static function yoo_active(){
        if(!is_null(self::$yoo_active)){
            return self::$yoo_active;
        }

        // get the currently active theme
        $theme = wp_get_theme();

        if(!empty($theme) && $theme->exists() &&
            (false !== stripos($theme->name, 'YOOtheme') ||
            (!empty($theme->parent_theme) && false !== stripos($theme->parent_theme, 'YOOtheme')))
        ){
            self::$yoo_active = true;
        }else{
            self::$yoo_active = false;
        }

        return self::$yoo_active;
    }
}