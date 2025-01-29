<?php

/**
 * Model for suggestions
 *
 * Class Wpil_Model_Suggestion
 */
class Wpil_Model_Suggestion
{
    public $post = false;
    public $words = [];
    public $anchor = '';
    public $sentence_with_anchor = '';
    public $original_sentence_with_anchor = '';
    public $post_score = 0;
    public $anchor_score = 0;
    public $total_score = 0;
    public $opacity = 1;
    public $ai_score_data = null;
    public $ai_relatedness_calculation = 0;

    public function __construct($params = [])
    {
        //fill model properties from initial array
        foreach ($params as $key => $value) {
            if (isset($this->{$key})) {
                $this->{$key} = $value;
            }
        }
    }

    public function has_ai_scored(){
        return $this->ai_score_data !== null;
    }

    public function get_ai_score_data(){
        return (isset($this->ai_score_data) && !empty($this->ai_score_data)) ? $this->ai_score_data: array();
    }

    public function get_ai_related(){
        if(isset($this->ai_score_data) && !empty($this->ai_score_data) && isset($this->ai_score_data->related)){
            return ($this->ai_score_data->related === 'yes');
        }

        return false;
    }

    public function get_ai_similarity_score(){
        if($this->has_ai_scored()){
            if(isset($this->ai_score_data->similarity_score)){
                return (int) $this->ai_score_data->similarity_score;
            }

            return 0; // NOTE: It might be better to have a false or null
        }

        return false;
    }

    public function get_ai_related_explanation(){
        if($this->has_ai_scored()){
            if(isset($this->ai_score_data->explanation) && !empty($this->ai_score_data->explanation)){
                return $this->ai_score_data->explanation;
            }else{
                return __('No relation explanation possible.', 'wpil');
            }
        }

        return false;
    }
}
