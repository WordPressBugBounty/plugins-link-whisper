<?php

/**
 * Goodlayers editor
 *
 * Class Wpil_Editor_Goodlayers
 */
class Wpil_Editor_Goodlayers
{
    static $item_types = array('text-box', 'accordion', 'blockquote', 'toggle-box');
    public static $force_insert_link;

    /**
     * Gets the post content for the Goodlayers builder
     *
     * @param $post_id
     */
    public static function getContent($post_id){
        // goodlayer stores it's data in a vast array under a single index
        $goodlayer = get_post_meta($post_id, 'gdlr-core-page-builder', true);

        $content = '';

        if(!empty($goodlayer)){
            foreach($goodlayer as $item){
                // if this item is a type that we can get content out of
                if(isset($item['type']) && in_array($item['type'], self::$item_types, true) &&
                    isset($item['value']) && !empty($item['value'])) // and if there's a value
                {
                    // check if it's tabbed
                    if(isset($item['value']['tabs'])){
                        // if it is, retrieve the content from the tabs
                        foreach($item['value']['tabs'] as $tab){
                            if(isset($tab['content']) && !empty($tab['content'])){
                                $content .= "\n" . $tab['content'];
                            }
                        }
                    }elseif(isset($item['value']['content']) && !empty($item['value']['content'])){
                        $content .= "\n" . $item['value']['content'];
                    }
                }elseif(isset($item['type']) && $item['type'] === 'wordpress-editor-content'){
                    // if there's a WP editor content in the array, pull the post content
                    $content .= "\n" . get_post($post_id)->post_content;
                }
            }
        }

        return $content;
    }
}