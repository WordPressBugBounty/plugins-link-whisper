<?php

use PhpOffice\PhpSpreadsheet\Writer\Ods\Content;

/**
 * Beaver editor
 *
 * Class Wpil_Editor_Oxygen
 */
class Wpil_Editor_Oxygen
{
    public static $content_types = [
        'ct_text_block',
        'oxy_rich_text',
        'oxy_tabs_content'
    ];

    public static $args_types = [
        'oxy_testimonial' => [
            'testimonial_text',
            'testimonial_author',
            'testimonial_author_info'
        ],
        'oxy_icon_box' => [
            'icon_box_text'
        ],
        'oxy_pricing_box' => [
            'pricing_box_package_title',
            'pricing_box_package_subtitle',
            'pricing_box_content'
        ]
    ];

    public static $keyword_links_count;
    public static $force_insert_link;
    public static $json_data = false;
    public static $post_saving = null; // are we doing stuff during the "save_post" action?

    /**
     * Check if editor is active
     *
     * @return bool
     */
    public static function active()
    {
        self::$json_data = defined('CT_VERSION') && version_compare(CT_VERSION, '4.0', '>=');

        $activated_plugins = get_option('active_plugins');
        foreach ($activated_plugins as $plugin){
            if (strpos($plugin, 'oxygen/') === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get Oxygen post content
     *
     * @param $post_id
     * @return string
     */
    public static function getContent($post_id, $remove_unprocessable = true)
    {
        $data = self::getData($post_id);
        if (!self::active() || empty($data)) {
            return '';
        }

        // if we're not removing the items we can't process
        if(!$remove_unprocessable){
            // try getting the shortcode data
            $oxy_prefix = (!empty(get_option('oxy_meta_keys_prefixed', false))) ? '_ct_': 'ct_';
            $dat = get_post_meta($post_id, $oxy_prefix . 'builder_shortcodes', true);
            if(!empty($dat)){
                if(Wpil_Settings::getContentFormattingLevel() > 0){
                    return (function_exists('do_oxygen_elements')) ? do_oxygen_elements($dat): do_shortcode($dat);
                }else{
                    return $dat;
                }
            }
        }

        $content = '';
        if(self::$json_data){
            self::getJsonReadOnlyContent($data, $content);
        }else{
            foreach ($data as $item) {
                self::getItemContent($item, $content);
            }
        }

        return $content;
    }

    /**
     * Get content from certain shortcode
     *
     * @param $item
     * @param $content
     */
    public static function getItemContent($item, &$content)
    {
        foreach (self::$args_types as $type => $types) {
            if ($item->type == $type) {
                $args = json_decode($item->args_value);
                foreach ($types as $key) {
                    if (!empty($args->original->$key)) {
                        $content .= base64_decode($args->original->$key) . "\n";
                    }
                }
            }
        }

        if (!empty($item->content) && in_array($item->type, self::$content_types)) {
            $content .= $item->content . "\n";
        }

        if (!empty($item->children)) {
            foreach ($item->children as $child)
            self::getItemContent($child, $content);
        }
    }

    /**
     * Gets the content that is processable by Link Whisper, for display & reading purposes only.
     * The content is taken out of it's native object construction and stringified for searching and phrase making purposes
     **/
    public static function getJsonReadOnlyContent($data, &$content){
        // if this is the base element in the Oxygen data object
        if(isset($data['name']) && $data['name'] === 'root'){
            $data = array($data); // wrapp the data in an array so we can loop over it
        }

        foreach($data as $dat){
            if( isset($dat['name']) && 
                in_array($dat['name'], self::$content_types, true) &&
                isset($dat['options']) && isset($dat['options']['ct_content']) && !empty($dat['options']['ct_content']))
            {
                $content .= "\n" . $dat['options']['ct_content'];
            }

            if(isset($dat['children']) && !empty($dat['children'])){
                self::getJsonReadOnlyContent($dat['children'], $content);
            }
        }
    }

    /**
     * Parse Oxygen post content
     *
     * @param $post_id
     * @return array
     */
    public static function getData($post_id)
    {
        if(!defined('CT_VERSION') || !self::active()){
            return array();
        }

        $data = self::get_meta($post_id);

        if(self::$json_data){
            return $data;
        }else{
            $data = self::getItem($data);
        }

        return $data;
    }

    /**
     * Parse certain shortcode
     *
     * @param $data
     * @return array
     */
    public static function getItem($data)
    {
        $blocks = [];
        $begin = self::closestShortcode($data);

        $i = 0;
        while ($begin !== false) {
            $i++;
            $end = strpos($data, ' ', $begin);
            $type = substr($data, $begin + 1, $end - $begin - 1);
            $end = strpos($data, '[/' . $type . ']', $begin);
            $text = substr($data, $begin, $end - $begin + strlen($type) + 3);

            //get content
            $content_begin = strpos($text, ']');
            $sub_content_begin = strpos($text, ']"');
            // check if there's a shortcode inside the shortcode we're trying to examine
            if(!empty($sub_content_begin) && $content_begin === $sub_content_begin){
                // if there is, update the parent shortcode ending so it's actually the end and not the sub content shortcode ending...
                $content_begin = strpos($text, ']', ($sub_content_begin + 1));
            }
            $content_end = strrpos($text, '[');
            $content = substr($text, $content_begin + 1, $content_end - $content_begin - 1);

            //get sign type
            $params_end = strpos($text, ']');
            $params = substr($text, 0, $params_end);
            $params = explode(' ', $params);

            if(!isset($params[0]) || !isset($params[1])){
                if(false === $end){
                    $end = strpos($data, ']', $begin);
                }

                if(false === $end){
                    break;
                }else{
                    $begin = self::closestShortcode($data, $end + 1);
                    continue;
                }
            }

            $sig = explode('=', $params[1]);
            $sig_value = substr($sig[1], 1, -1);

            //get args
            $params = array_slice($params, 2);
            $params = implode('', $params);
            $args = preg_split('/([a-zA-Z0-9])(?:=)([\'])/', $params);
            array_shift($args);
            $args = implode('', $args);
            $args_value = trim($args, '\'');

            $blocks[] = (object)[
                'type' => $type,
                'text' => $text,
                'sig_key' => $sig[0],
                'sig_value' => $sig_value,
                'args_value' => $args_value,
                'content' => $content,
                'children' => self::getItem($content)
            ];

            $begin = self::closestShortcode($data, $end + 1);
        }

        return $blocks;
    }

    public static function closestShortcode($string = '', $offset = 0){
        if(empty($string) || $offset > strlen($string)){
            return false;
        }
        $tags = array('[ct', '[oxy');

        $positions = array();
        foreach($tags as $tag) {
            $position = strpos($string, $tag, $offset);
            $subtag = ('"' . $tag);
            if ($position !== false && // if we've found a tag
                $position !== (strpos($string, $subtag, $offset) + 1)) // and the tag we've found doesn't belong to a sub-field shortcode
            {
                $positions[$tag] = $position; // set the position
            }
        }

        return (!empty($positions)) ? min($positions): false;
    }

    /**
     * Obtains the Oxygen content from the post meta.
     * Gets the shortcode data for versions < 4.0 and gets JSON for versions =< 4.0
     * 
     * @param int $post_id
     * @return array
     **/
    public static function get_meta($post_id){
        if(empty($post_id) || !defined('CT_VERSION')){
            return array();
        }

        $oxy_prefix = (!empty(get_option('oxy_meta_keys_prefixed', false))) ? '_ct_': 'ct_';

        // if the version is 4.0 or above
        if(self::$json_data){
            // obtain the json data

            // is there $_POST json?
            self::$post_saving = (isset($_POST['ct_builder_json']) && !empty($_POST['ct_builder_json'])) ? true: false;

            if(self::$post_saving){
                $data = trim(wp_unslash($_POST['ct_builder_json']));
            }else{
                $data = get_post_meta($post_id, $oxy_prefix . 'builder_json', true);
            }

        }else{
            // otherwise, go for shortcodes
            $data = get_post_meta($post_id, $oxy_prefix . 'builder_shortcodes', true);
        }

        // if there's no data, return an empty array
        if(empty($data)){
            return array();
        }

        // if this is json data
        if(self::$json_data){
            // decode it before returning it
            $data = json_decode($data, true);
        }

        return $data;
    }
}