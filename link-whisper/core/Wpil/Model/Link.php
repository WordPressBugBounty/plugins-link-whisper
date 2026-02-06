<?php

/**
 * Model for links
 *
 * Class Wpil_Model_Link
 */
class Wpil_Model_Link
{
    public $link_id = 0; // the link's row index in report_links table
    public $url = '';
    public $host = '';
    public $internal = false;
    public $post = false;
    public $anchor = '';
    public $added_by_plugin = false;
    public $location = 'content';
    public $link_whisper_created = 0;
    public $is_autolink = 0;
    public $tracking_id = 0;
    public $module_link = 0; // was the link added by a pagebuilder or shortcode or module that we're not likely to be able to manipulate?
    public $link_context = 0;
    public $ai_relation_score = 0; // how related is the source post to the target post?
    public $target_id = null;
    public $target_type = null;
    public $anchor_word_count = 0;

    public function __construct($params = [])
    {
        //fill model properties from initial array
        foreach ($params as $key => $value) {
            if (isset($this->{$key})) {
                $this->{$key} = $value;
            }
        }

        if(empty($this->anchor_word_count) && !empty($this->anchor)){
            $this->anchor_word_count = Wpil_Word::getWordCount($this->anchor);
        }
    }

    function create_scroll_link_data(){
        $data = array(
            'scrollLink' => array(
                'monitorId' => $this->tracking_id,
                'url' => $this->url,
                'anchor' => $this->anchor
            )
        );

        return base64_encode(json_encode($data));
    }

    function get_ai_relation_percent($return_number = false){
        $percent = (!empty($this->ai_relation_score)) ? (round($this->ai_relation_score, 2) * 100): 0;

        if($return_number){
            return $percent;
        }else{
            return (!empty($percent)) ? $percent . '%': 'Unknown';
        }
    }
}
