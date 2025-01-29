<?php

/**
 * Model for phrases
 *
 * Class Wpil_Model_Phrase
 */
class Wpil_Model_Phrase
{
    public static $prev_id = 0;
    public $src = '';
    public $text = '';
    public $sentence_src = '';
    public $sentence_text = '';
    public $suggestions = [];
    public $opacity = 1;
    public $words_uniq = array();
    public $top_ai_score = 0;
    public $top_sentence_score = 0;

    public function __construct($params = [])
    {
        //fill model properties from initial array
        foreach ($params as $key => $value) {
            if (isset($this->{$key})) {
                $this->{$key} = $value;
            }
        }
    }
}
