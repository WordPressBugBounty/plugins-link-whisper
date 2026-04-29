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
    public $url_slug_word_count = 0;
    public $anchor_slug_positional_match = 0;
    public $page_context = '';

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

        if(!empty($this->url) || !empty($this->anchor)){
            $slug_match_data = Wpil_Report::get_url_slug_match_data($this->url, $this->anchor);

            if(empty($this->url_slug_word_count)){
                $this->url_slug_word_count = $slug_match_data['url_slug_word_count'];
            }

            if(empty($this->anchor_slug_positional_match)){
                $this->anchor_slug_positional_match = $slug_match_data['anchor_slug_positional_match'];
            }
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
        $score = $this->get_reporting_ai_relation_score();
        $percent = (!empty($score)) ? (round($score, 2) * 100): 0;

        if($return_number){
            return $percent;
        }else{
            return (!empty($percent)) ? $percent . '%': 'Unknown';
        }
    }

    function get_reporting_ai_relation_score(){
        $score = (isset($this->ai_relation_score) && is_numeric($this->ai_relation_score)) ? (float)$this->ai_relation_score : 0;

        if(
            !empty($this->internal) &&
            $score > 0 &&
            isset($this->anchor_slug_positional_match) &&
            is_numeric($this->anchor_slug_positional_match) &&
            (float)$this->anchor_slug_positional_match >= 80
        ){
            $score = min(1, ($score * 1.2));
        }

        return $score;
    }
}
