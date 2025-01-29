<?php

/**
 * Themify editor
 *
 * Class Wpil_Editor_Themify
 */
class Wpil_Editor_Themify
{
    public static $keyword_links_count;
    public static $meta_key = '_themify_builder_settings_json';
    public static $post_content;
    public static $force_insert_link;

    /**
     * Get Themify post content
     *
     * @param $post_id
     * @return string
     */
    public static function getContent($post_id)
    {
        self::$post_content = '';

        if(!class_exists('ThemifyBuilder_Data_Manager')){
            return self::$post_content;
        }

        $content = $post_id;
        self::manageLink($content, [
            'action' => 'get',
        ]);

        return self::$post_content;
    }

    /**
     * Find all text elements
     *
     * @param $data
     * @param $params
     */
    public static function manageLink(&$data, $params)
    {
        global $ThemifyBuilder_Data_Manager;


        if (is_numeric($data)) {
            // check if there's an instatiated object to use
            if(!empty($ThemifyBuilder_Data_Manager) && method_exists($ThemifyBuilder_Data_Manager, 'get_data')){
                // if there is, use it to save the data
                $content = $ThemifyBuilder_Data_Manager->get_data($data, true);
            }else{
                $content = ThemifyBuilder_Data_Manager::get_data($data, true);
            }

            if (empty($content)) {
                return;
            }

            $data = json_decode($content);
        }

        if (is_countable($data)) {
            foreach ($data as $item) {
                self::checkItem($item, $params);
            }
        }
    }

    /**
     * Check certain text element
     *
     * @param $item
     * @param $params
     */
    public static function checkItem(&$item, $params)
    {
        if (!empty($item->mod_settings)) {
            foreach (['content_text', 'text_alert', 'content_box', 'text_callout', 'content_feature', 'plain_text'] as $key) {
                if (!empty($item->mod_settings->$key)) {
                    self::manageBlock($item->mod_settings->$key, $params);
                }
            }

            if (!empty($item->mod_settings->content_accordion)) {
                foreach ($item->mod_settings->content_accordion as &$value) {
                    if (!empty($value->text_accordion)) {
                        self::manageBlock($value->text_accordion, $params);
                    }
                }
            }

            if (!empty($item->mod_settings->tab_content_testimonial)) {
                foreach ($item->mod_settings->tab_content_testimonial as &$value) {
                    if (!empty($value) && isset($value->content_testimonial) && !empty($value->content_testimonial)) {
                        self::manageBlock($value->content_testimonial, $params);
                    }
                }
            }

            if (!empty($item->mod_settings->tab_content_tab)) {
                foreach ($item->mod_settings->tab_content_tab as &$value) {
                    if (!empty($value) && isset($value->text_tab) && !empty($value->text_tab)) {
                        self::manageBlock($value->text_tab, $params);
                    }
                }
            }
        }

        if (!empty($item->cols)) {
            foreach ($item->cols as &$value) {
                self::checkItem($value, $params);
            }
        }

        if (!empty($item->modules)) {
            foreach ($item->modules as &$value) {
                self::checkItem($value, $params);
            }
        }
    }

    /**
     * Route current action
     *
     * @param $block
     * @param $params
     */
    public static function manageBlock(&$block, $params)
    {
        if ($params['action'] == 'get') {
            self::$post_content .= $block . "\n"; /* mb_ereg_replace_callback('<[^<>]*?(title=["\'][^\'"]*?["\']|alt=["\'][^\'"]*?["\'])+[^<>]*?>', function($matches){ 
                return str_replace("'", '"', $matches[0]);
            }, $block) . "\n";*/ // todo remove if I can't find a use for this after version 2.3.0
        }
    }
}