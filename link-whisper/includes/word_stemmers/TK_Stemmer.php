<?php
/**
 * Turkish stemmer skeleton for Link Whisper.
 *
 * Snowball source: Turkish stemmer by Evren (Kapusuz) Çilden
 * Snowball manual (Snowman): https://snowballstem.org/compiler/snowman.html
 */
class Wpil_Stemmer {
    static $stem_cache = array();

    // String being processed (as array of UTF-8 characters for easy indexing)
    private $current;
    
    // Cursor - current position in string
    private $cursor;
    
    // Limit - boundary for cursor movement
    private $limit;
    
    // Limit for backward processing
    private $limit_backward;
    
    // Slice markers
    private $bra;  // [ marker
    private $ket;  // ] marker
    
    // Boolean
    private $B_continue_stemming_noun_suffixes;
    private $B_did_delete;
    private $B_in_verb_chain_context;

    // Character groupings
    private static $g_vowel = ['a', 'e', 'ı', 'i', 'o', 'ö', 'u', 'ü'];
    private static $g_U = ['ı', 'i', 'u', 'ü'];
    private static $g_vowel1 = ['a', 'ı', 'o', 'u'];
    private static $g_vowel2 = ['e', 'i', 'ö', 'ü'];
    private static $g_vowel3 = ['a', 'ı'];
    private static $g_vowel4 = ['e', 'i'];
    private static $g_vowel5 = ['o', 'u'];
    private static $g_vowel6 = ['ö', 'ü'];

    /**
     * Main entry point
     */
    public static function Stem($word, $deaccent = false, $ignore_cache = false) {
        $word = mb_strtolower($word, 'UTF-8');

        if (!$ignore_cache) {
            $cached = self::get_cached_stem($word);
            if ($cached !== false) {
                return $cached;
            }
        }

        $original_word = $word;
        
        $stemmer = new self();
        $stemmer->B_did_delete = false;
        $stemmer->B_in_verb_chain_context = false;
        $stemmer->current = $word;
        $stemmer->cursor = 0;
        $stemmer->limit = mb_strlen($word, 'UTF-8');
        $stemmer->limit_backward = 0;
        $stemmer->bra = 0;
        $stemmer->ket = 0;
        
        $stemmer->r_stem();
        
        $word = $stemmer->current;
        self::update_cached_stem($original_word, $word);

        return $word;
    }

    // =========================================================================
    // MAIN STEM ROUTINE
    // Snowball: define stem as (...)
    // =========================================================================
    
    private function r_stem() {
        // do remove_proper_noun_suffix
        $this->r_remove_proper_noun_suffix();

        // more_than_one_syllable_word
        if (!$this->r_more_than_one_syllable_word()) {
            return false;
        }

        // backwards...
        $this->limit_backward = 0;
        $this->limit = mb_strlen($this->current, 'UTF-8');
        $this->cursor = $this->limit;

        // do stem_nominal_verb_suffixes
        $this->B_continue_stemming_noun_suffixes = true;
        $v_1 = $this->limit - $this->cursor;
        $this->r_stem_nominal_verb_suffixes();
        $this->cursor = $this->limit - $v_1;

        // continue_stemming_noun_suffixes
        if ($this->B_continue_stemming_noun_suffixes) {
            $v_2 = $this->limit - $this->cursor;
            $this->r_stem_noun_suffixes();
            $this->cursor = $this->limit - $v_2;
        }

        $this->limit = mb_strlen($this->current, 'UTF-8');
        $this->cursor = $this->limit;

        $guard = 0;
        while ($guard++ < 4) {
            $prev = $this->current;

            if (!$this->r_stem_derivational_suffixes()) {
                break;
            }

            if ($this->current === $prev) {
                break;
            }

            $this->limit = mb_strlen($this->current, 'UTF-8');
            $this->cursor = $this->limit;
        }

        // postlude
        $this->limit = mb_strlen($this->current, 'UTF-8');
        $this->cursor = $this->limit;
        $this->r_postlude();

        return true;
    }

    
    // Check if current string has at least one vowel
    private function r_has_vowel() {
        $len = mb_strlen($this->current, 'UTF-8');
        for ($i = 0; $i < $len; $i++) {
            if (in_array($this->charAt($i), self::$g_vowel)) {
                return true;
            }
        }
        return false;
    }

    // =========================================================================
    // HELPER: Get character at position
    // =========================================================================
    
    private function charAt($pos) {
        if ($pos < 0 || $pos >= mb_strlen($this->current, 'UTF-8')) {
            return '';
        }
        return mb_substr($this->current, $pos, 1, 'UTF-8');
    }

    // =========================================================================
    // HELPER: Check if character is in grouping
    // =========================================================================
    
    private function in_grouping_b($grp) {
        if ($this->cursor <= $this->limit_backward) {
            return false;
        }
        $ch = $this->charAt($this->cursor - 1);
        return in_array($ch, $grp);
    }

    private function out_grouping_b($grp) {
        if ($this->cursor <= $this->limit_backward) {
            return false;
        }
        $ch = $this->charAt($this->cursor - 1);
        return !in_array($ch, $grp);
    }

    // =========================================================================
    // HELPER: Literal string matching (backward)
    // =========================================================================
    
    private function eq_s_b($s) {
        $len = mb_strlen($s, 'UTF-8');
        if ($this->cursor - $this->limit_backward < $len) {
            return false;
        }
        $start = $this->cursor - $len;
        for ($i = 0; $i < $len; $i++) {
            if ($this->charAt($start + $i) !== mb_substr($s, $i, 1, 'UTF-8')) {
                return false;
            }
        }
        $this->cursor -= $len;
        return true;
    }

    // =========================================================================
    // HELPER: Find among (longest match from list) - backward
    // Returns 0 if no match, otherwise the index+1 of the match
    // =========================================================================
    
    private function find_among_b($amongs) {
        $best_len = 0;
        $best_result = 0;
        
        foreach ($amongs as $idx => $entry) {
            $s = $entry[0];
            $len = mb_strlen($s, 'UTF-8');
            
            if ($len <= $best_len) continue;
            if ($this->cursor - $this->limit_backward < $len) continue;
            
            $start = $this->cursor - $len;
            $match = true;
            for ($i = 0; $i < $len; $i++) {
                if ($this->charAt($start + $i) !== mb_substr($s, $i, 1, 'UTF-8')) {
                    $match = false;
                    break;
                }
            }
            
            if ($match) {
                $best_len = $len;
                $best_result = $idx + 1;
            }
        }
        
        if ($best_result > 0) {
            $this->cursor -= $best_len;
        }
        
        return $best_result;
    }

    // =========================================================================
    // HELPER: Slice operations
    // =========================================================================
    
    private function slice_del() {
        $this->B_did_delete = true;
        $this->slice_from('');
    }
    
    private function slice_from($s) {
        $adjustment = mb_strlen($s, 'UTF-8') - ($this->ket - $this->bra);
        $before = mb_substr($this->current, 0, $this->bra, 'UTF-8');
        $after = mb_substr($this->current, $this->ket, null, 'UTF-8');
        $this->current = $before . $s . $after;
        
        $this->limit += $adjustment;
        if ($this->cursor >= $this->ket) {
            $this->cursor += $adjustment;
        } else if ($this->cursor > $this->bra) {
            $this->cursor = $this->bra;
        }
    }
    
    private function insert($bra, $ket, $s) {
        $adjustment = mb_strlen($s, 'UTF-8') - ($ket - $bra);
        $before = mb_substr($this->current, 0, $bra, 'UTF-8');
        $after = mb_substr($this->current, $ket, null, 'UTF-8');
        $this->current = $before . $s . $after;
        
        $this->limit += $adjustment;
        if ($this->cursor >= $ket) {
            $this->cursor += $adjustment;
        } else if ($this->cursor > $bra) {
            $this->cursor = $bra;
        }
    }

    // =========================================================================
    // HELPER: goto (find position where grouping matches)
    // =========================================================================
    
    private function go_out_grouping_b($grp) {
        while ($this->cursor > $this->limit_backward) {
            $ch = $this->charAt($this->cursor - 1);
            if (!in_array($ch, $grp)) {
                return true;
            }
            $this->cursor--;
        }
        return false;
    }
    
    private function go_in_grouping_b($grp) {
        while ($this->cursor > $this->limit_backward) {
            $ch = $this->charAt($this->cursor - 1);
            if (in_array($ch, $grp)) {
                return true;
            }
            $this->cursor--;
        }
        return false;
    }

    // =========================================================================
    // r_check_vowel_harmony
    // =========================================================================
    
    private function r_check_vowel_harmony() {
        // test ( (goto vowel) (...) )
        $v_1 = $this->cursor;
        
        // goto vowel - go backward until we find a vowel
        if (!$this->go_in_grouping_b(self::$g_vowel)) {
            $this->cursor = $v_1;
            return false;
        }
        
        $ch = $this->charAt($this->cursor - 1);
        
        $result = false;
        if ($ch === 'a' && $this->go_in_grouping_b(self::$g_vowel1)) {
            $result = true;
        } else if ($ch === 'e' && $this->go_in_grouping_b(self::$g_vowel2)) {
            $result = true;
        } else if ($ch === 'ı' && $this->go_in_grouping_b(self::$g_vowel3)) {
            $result = true;
        } else if ($ch === 'i' && $this->go_in_grouping_b(self::$g_vowel4)) {
            $result = true;
        } else if ($ch === 'o' && $this->go_in_grouping_b(self::$g_vowel5)) {
            $result = true;
        } else if ($ch === 'ö' && $this->go_in_grouping_b(self::$g_vowel6)) {
            $result = true;
        } else if ($ch === 'u' && $this->go_in_grouping_b(self::$g_vowel5)) {
            $result = true;
        } else if ($ch === 'ü' && $this->go_in_grouping_b(self::$g_vowel6)) {
            $result = true;
        }
        
        $this->cursor = $v_1;
        return $result;
    }

    // =========================================================================
    // r_mark_suffix_with_optional_n_consonant
    // =========================================================================
    private function r_mark_suffix_with_optional_n_consonant() {
    $v_1 = $this->cursor;

    // case: ...n + suffix, buffer n consumed
    if ($this->eq_s_b('n')) {
        if ($this->in_grouping_b(self::$g_vowel)) {
            return true;
        }
        $this->cursor = $v_1;
        return false;
    }

    // case: no buffer n => stem-final must be consonant
    $this->cursor = $v_1;
    return $this->out_grouping_b(self::$g_vowel);
}


    // =========================================================================
    // r_mark_suffix_with_optional_s_consonant
    // =========================================================================

    private function r_mark_suffix_with_optional_s_consonant() {
        $v_1 = $this->cursor;

        if ($this->eq_s_b('s')) {
            if ($this->in_grouping_b(self::$g_vowel)) {
                return true;
            }
            $this->cursor = $v_1;
            return false;
        }

        $this->cursor = $v_1;
        return $this->out_grouping_b(self::$g_vowel);
    }
    // =========================================================================
    // r_mark_suffix_with_optional_y_consonant
    // =========================================================================
    
    private function r_mark_suffix_with_optional_y_consonant() {
        $v_1 = $this->cursor;

        if ($this->eq_s_b('y')) {
            if ($this->in_grouping_b(self::$g_vowel)) {
                return true;
            }
            $this->cursor = $v_1;
            return false;
        }

        $this->cursor = $v_1;
        return $this->out_grouping_b(self::$g_vowel);
    }

    // =========================================================================
    // r_mark_suffix_with_optional_U_vowel
    // =========================================================================
        private function r_mark_suffix_with_optional_U_vowel() {
        $v_1 = $this->cursor;

        // If an optional U vowel (ı/i/u/ü) is present immediately before the suffix,
        // it must be preceded by a consonant, and it MUST be consumed (cursor moves back).
        if ($this->in_grouping_b(self::$g_U)) {
            $this->cursor--; // consume the U

            if ($this->out_grouping_b(self::$g_vowel)) {
                // valid: U preceded by consonant, keep cursor moved back
                return true;
            }

            // invalid: restore and fail
            $this->cursor = $v_1;
            return false;
        }

        // If the optional U is absent, then the suffix must attach directly to a vowel-ending stem.
        $this->cursor = $v_1;
        return $this->in_grouping_b(self::$g_vowel);
    }


    // =========================================================================
    // r_mark_possessives
    // among ('mız' 'miz' 'muz' 'müz' 'nız' 'niz' 'nuz' 'nüz' 'm' 'n')
    // (mark_suffix_with_optional_U_vowel)
    // =========================================================================
    
    private function r_mark_possessives() {
        $among = [
            ['mız'], ['miz'], ['muz'], ['müz'],
            ['nız'], ['niz'], ['nuz'], ['nüz'],
            ['m'], ['n']
        ];
        
        if ($this->find_among_b($among) == 0) {
            return false;
        }
        
        return $this->r_mark_suffix_with_optional_U_vowel();
    }

    // =========================================================================
    // r_mark_sU
    // check_vowel_harmony U (mark_suffix_with_optional_s_consonant)
    // =========================================================================
    
    private function r_mark_sU() {
        if (!$this->r_check_vowel_harmony()) {
            return false;
        }
        if (!$this->in_grouping_b(self::$g_U)) {
            return false;
        }
        $this->cursor--;

        if (!$this->r_mark_suffix_with_optional_s_consonant()) {
            return false;
        }
        return true;
    }

    // =========================================================================
    // r_mark_lArI
    // among ('leri' 'ları')
    // =========================================================================
    
    private function r_mark_lArI() {
        $among = [['leri'], ['ları']];
        return $this->find_among_b($among) != 0;
    }

    // =========================================================================
    // r_mark_yU
    // check_vowel_harmony U (mark_suffix_with_optional_y_consonant)
    // =========================================================================
    
    private function r_mark_yU() {
        if (!$this->r_check_vowel_harmony()) return false;

        if (!$this->in_grouping_b(self::$g_U)) return false;
        $this->cursor--;

        return $this->r_mark_suffix_with_optional_y_consonant();
    }

    // =========================================================================
    // r_mark_nU
    // check_vowel_harmony among ('nı' 'ni' 'nu' 'nü')
    // =========================================================================
    
    private function r_mark_nU() {
        if (!$this->r_check_vowel_harmony()) {
            return false;
        }
        $among = [['nı'], ['ni'], ['nu'], ['nü']];
        return $this->find_among_b($among) != 0;
    }

    // =========================================================================
    // r_mark_nUn
    // check_vowel_harmony among ('ın' 'in' 'un' 'ün') (mark_suffix_with_optional_n_consonant)
    // =========================================================================
    
    private function r_mark_nUn() {
        if (!$this->r_check_vowel_harmony()) {
            return false;
        }
        $among = [['ın'], ['in'], ['un'], ['ün']];
        if ($this->find_among_b($among) == 0) {
            return false;
        }
        return $this->r_mark_suffix_with_optional_n_consonant();
    }

    // =========================================================================
    // r_mark_yA
    // check_vowel_harmony among('a' 'e') (mark_suffix_with_optional_y_consonant)
    // =========================================================================
    
    private function r_mark_yA() {
        if (!$this->r_check_vowel_harmony()) return false;

        $among = [['a'], ['e']];
        if ($this->find_among_b($among) == 0) return false;

        return $this->r_mark_suffix_with_optional_y_consonant();
    }


    // =========================================================================
    // r_mark_nA
    // check_vowel_harmony among('na' 'ne')
    // =========================================================================
    
    private function r_mark_nA() {
        if (!$this->r_check_vowel_harmony()) {
            return false;
        }
        $among = [['na'], ['ne']];
        return $this->find_among_b($among) != 0;
    }

    // =========================================================================
    // r_mark_DA
    // check_vowel_harmony among('da' 'de' 'ta' 'te')
    // =========================================================================
    
    private function r_mark_DA() {
        if (!$this->r_check_vowel_harmony()) {
            return false;
        }
        $among = [['da'], ['de'], ['ta'], ['te']];
        return $this->find_among_b($among) != 0;
    }

    // =========================================================================
    // r_mark_ndA
    // check_vowel_harmony among('nda' 'nde')
    // =========================================================================
    
    private function r_mark_ndA() {
        if (!$this->r_check_vowel_harmony()) {
            return false;
        }
        $among = [['nda'], ['nde']];
        return $this->find_among_b($among) != 0;
    }

    // =========================================================================
    // r_mark_DAn
    // check_vowel_harmony among('dan' 'den' 'tan' 'ten')
    // =========================================================================
    
    private function r_mark_DAn() {
        if (!$this->r_check_vowel_harmony()) {
            return false;
        }
        $among = [['dan'], ['den'], ['tan'], ['ten']];
        return $this->find_among_b($among) != 0;
    }

    // =========================================================================
    // r_mark_ndAn
    // check_vowel_harmony among('ndan' 'nden')
    // =========================================================================
    
    private function r_mark_ndAn() {
        if (!$this->r_check_vowel_harmony()) {
            return false;
        }
        $among = [['ndan'], ['nden']];
        return $this->find_among_b($among) != 0;
    }

    // =========================================================================
    // r_mark_ylA
    // check_vowel_harmony among('la' 'le') (mark_suffix_with_optional_y_consonant)
    // =========================================================================
    
    private function r_mark_ylA() {
        if (!$this->r_check_vowel_harmony()) {
            return false;
        }
        $among = [['la'], ['le']];
        if ($this->find_among_b($among) == 0) {
            return false;
        }
        return $this->r_mark_suffix_with_optional_y_consonant();
    }

    // =========================================================================
    // r_mark_ki
    // 'ki'
    // =========================================================================
    
    private function r_mark_ki() {
        return $this->eq_s_b('ki');
    }

    // =========================================================================
    // r_mark_ncA
    // check_vowel_harmony among('ca' 'ce') (mark_suffix_with_optional_n_consonant)
    // =========================================================================
    
    private function r_mark_ncA() {
        if (!$this->r_check_vowel_harmony()) {
            return false;
        }
        $among = [['ca'], ['ce']];
        if ($this->find_among_b($among) == 0) {
            return false;
        }
        return $this->r_mark_suffix_with_optional_n_consonant();
    }

    // =========================================================================
    // r_mark_yUm
    // check_vowel_harmony among ('ım' 'im' 'um' 'üm') (mark_suffix_with_optional_y_consonant)
    // =========================================================================
    
    private function r_mark_yUm() {
        if (!$this->r_check_vowel_harmony()) {
            return false;
        }
        $among = [['ım'], ['im'], ['um'], ['üm']];
        if ($this->find_among_b($among) == 0) {
            return false;
        }
        return $this->r_mark_suffix_with_optional_y_consonant();
    }

    // =========================================================================
    // r_mark_sUn
    // check_vowel_harmony among ('sın' 'sin' 'sun' 'sün')
    // =========================================================================
    
    private function r_mark_sUn() {
        if (!$this->r_check_vowel_harmony()) {
            return false;
        }
        $among = [['sın'], ['sin'], ['sun'], ['sün']];
        return $this->find_among_b($among) != 0;
    }

    // =========================================================================
    // r_mark_yUz
    // check_vowel_harmony among ('ız' 'iz' 'uz' 'üz') (mark_suffix_with_optional_y_consonant)
    // =========================================================================
    
    private function r_mark_yUz() {
        if (!$this->r_check_vowel_harmony()) {
            return false;
        }
        $among = [['ız'], ['iz'], ['uz'], ['üz']];
        if ($this->find_among_b($among) == 0) {
            return false;
        }
        return $this->r_mark_suffix_with_optional_y_consonant();
    }

    // =========================================================================
    // r_mark_sUnUz
    // among ('sınız' 'siniz' 'sunuz' 'sünüz')
    // =========================================================================
    
    private function r_mark_sUnUz() {
        $among = [['sınız'], ['siniz'], ['sunuz'], ['sünüz']];
        return $this->find_among_b($among) != 0;
    }

    // =========================================================================
    // r_mark_lAr
    // check_vowel_harmony among ('ler' 'lar')
    // =========================================================================
    
    private function r_mark_lAr() {
        if (!$this->r_check_vowel_harmony()) {
            return false;
        }
        $among = [['ler'], ['lar']];
        return $this->find_among_b($among) != 0;
    }

    // =========================================================================
    // r_mark_nUz
    // check_vowel_harmony among ('nız' 'niz' 'nuz' 'nüz')
    // =========================================================================
    
    private function r_mark_nUz() {
        if (!$this->r_check_vowel_harmony()) {
            return false;
        }
        $among = [['nız'], ['niz'], ['nuz'], ['nüz']];
        return $this->find_among_b($among) != 0;
    }

    // =========================================================================
    // r_mark_DUr
    // check_vowel_harmony among ('tır' 'tir' 'tur' 'tür' 'dır' 'dir' 'dur' 'dür')
    // =========================================================================
    
    private function r_mark_DUr() {
        if (!$this->r_check_vowel_harmony()) {
            return false;
        }
        $among = [['tır'], ['tir'], ['tur'], ['tür'], ['dır'], ['dir'], ['dur'], ['dür']];
        return $this->find_among_b($among) != 0;
    }

    // =========================================================================
    // r_mark_cAsInA
    // among ('casına' 'cesine')
    // =========================================================================
    
    private function r_mark_cAsInA() {
        $among = [['casına'], ['cesine']];
        return $this->find_among_b($among) != 0;
    }

    // =========================================================================
    // r_mark_yDU
    // =========================================================================
    
    private function r_mark_yDU() {
        if (!$this->r_check_vowel_harmony()) {
            return false;
        }
        $among = [
            ['tım'], ['tim'], ['tum'], ['tüm'], ['dım'], ['dim'], ['dum'], ['düm'],
            ['tın'], ['tin'], ['tun'], ['tün'], ['dın'], ['din'], ['dun'], ['dün'],
            ['tık'], ['tik'], ['tuk'], ['tük'], ['dık'], ['dik'], ['duk'], ['dük'],
            ['tı'], ['ti'], ['tu'], ['tü'], ['dı'], ['di'], ['du'], ['dü']
        ];
        if ($this->find_among_b($among) == 0) {
            return false;
        }
        return $this->r_mark_suffix_with_optional_y_consonant();
    }

    // =========================================================================
    // r_mark_ysA
    // does not fully obey vowel harmony
    // =========================================================================
    
    private function r_mark_ysA() {
        $among = [['sam'], ['san'], ['sak'], ['sem'], ['sen'], ['sek'], ['sa'], ['se']];
        if ($this->find_among_b($among) == 0) {
            return false;
        }
        return $this->r_mark_suffix_with_optional_y_consonant();
    }

    // =========================================================================
    // r_mark_ymUs_
    // check_vowel_harmony among ('mış' 'miş' 'muş' 'müş') (mark_suffix_with_optional_y_consonant)
    // =========================================================================
    
    private function r_mark_ymUs_() {
        if (!$this->r_check_vowel_harmony()) {
            return false;
        }
        $among = [['mış'], ['miş'], ['muş'], ['müş']];
        if ($this->find_among_b($among) == 0) {
            return false;
        }
        return $this->r_mark_suffix_with_optional_y_consonant();
    }

    // =========================================================================
    // r_mark_yken
    // 'ken' (mark_suffix_with_optional_y_consonant)
    // =========================================================================
    
    private function r_mark_yken() {
        if (!$this->eq_s_b('ken')) {
            return false;
        }
        return $this->r_mark_suffix_with_optional_y_consonant();
    }

    // =========================================================================
    // r_stem_nominal_verb_suffixes
    // =========================================================================
    
    private function r_stem_nominal_verb_suffixes() {
        $this->ket = $this->cursor;
        $this->B_continue_stemming_noun_suffixes = true;
        
        // First big OR block: try (mark_ymUs_ or mark_yDU or mark_ysA or mark_yken)
        $v_1 = $this->cursor;
        if ($this->r_mark_ymUs_() || 
            (($this->cursor = $v_1) && false) || $this->r_mark_yDU() ||
            (($this->cursor = $v_1) && false) || $this->r_mark_ysA() ||
            (($this->cursor = $v_1) && false) || $this->r_mark_yken()) {
            $this->bra = $this->cursor;
            $this->slice_del();
            return true;
        }
        $this->cursor = $v_1;
        
        // Second: (mark_cAsInA (...) mark_ymUs_)
        $v_2 = $this->cursor;
        if ($this->r_mark_cAsInA()) {
            $v_3 = $this->cursor;
            $this->r_mark_sUnUz() || 
                (($this->cursor = $v_3) && false) || $this->r_mark_lAr() ||
                (($this->cursor = $v_3) && false) || $this->r_mark_yUm() ||
                (($this->cursor = $v_3) && false) || $this->r_mark_sUn() ||
                (($this->cursor = $v_3) && false) || $this->r_mark_yUz() ||
                ($this->cursor = $v_3);
            if ($this->r_mark_ymUs_()) {
                $this->bra = $this->cursor;
                $this->slice_del();
                return true;
            }
        }
        $this->cursor = $v_2;
        
        // Third: (mark_lAr ] delete try([(mark_DUr or mark_yDU or mark_ysA or mark_ymUs_)) unset continue_stemming_noun_suffixes
        $v_4 = $this->cursor;
        if ($this->r_mark_lAr()) {
            $this->bra = $this->cursor;
            $this->slice_del();
            
            $v_5 = $this->cursor;
            $this->ket = $this->cursor;
            $this->r_mark_DUr() ||
                (($this->cursor = $v_5) && ($this->ket = $this->cursor) && false) || $this->r_mark_yDU() ||
                (($this->cursor = $v_5) && ($this->ket = $this->cursor) && false) || $this->r_mark_ysA() ||
                (($this->cursor = $v_5) && ($this->ket = $this->cursor) && false) || $this->r_mark_ymUs_();
            if ($this->cursor != $v_5) {
                $this->bra = $this->cursor;
                $this->slice_del();
            }
            
            $this->B_continue_stemming_noun_suffixes = false;
            return true;
        }
        $this->cursor = $v_4;
        
        // Fourth: (mark_nUz (mark_yDU or mark_ysA))
        $v_6 = $this->cursor;
        if ($this->r_mark_nUz()) {
            $v_7 = $this->cursor;
            if ($this->r_mark_yDU() || (($this->cursor = $v_7) && false) || $this->r_mark_ysA()) {
                $this->bra = $this->cursor;
                $this->slice_del();
                return true;
            }
        }
        $this->cursor = $v_6;
        
        // Fifth: ((mark_sUnUz or mark_yUz or mark_sUn or mark_yUm) ] delete try([ mark_ymUs_))
        $v_8 = $this->cursor;
        if ($this->r_mark_sUnUz() || 
            (($this->cursor = $v_8) && false) || $this->r_mark_yUz() ||
            (($this->cursor = $v_8) && false) || $this->r_mark_sUn() ||
            (($this->cursor = $v_8) && false) || $this->r_mark_yUm()) {
            $this->bra = $this->cursor;
            $this->slice_del();
            
            $v_9 = $this->cursor;
            $this->ket = $this->cursor;
            if ($this->r_mark_ymUs_()) {
                $this->bra = $this->cursor;
                $this->slice_del();
            } else {
                $this->cursor = $v_9;
            }
            return true;
        }
        $this->cursor = $v_8;
        
        // Sixth: (mark_DUr ] delete try([ (mark_sUnUz or mark_lAr or mark_yUm or mark_sUn or mark_yUz or true) mark_ymUs_))
        $v_10 = $this->cursor;
        if ($this->r_mark_DUr()) {
            $this->bra = $this->cursor;
            $this->slice_del();
            
            $v_11 = $this->cursor;
            $this->ket = $this->cursor;
            $v_12 = $this->cursor;
            $this->r_mark_sUnUz() ||
                (($this->cursor = $v_12) && false) || $this->r_mark_lAr() ||
                (($this->cursor = $v_12) && false) || $this->r_mark_yUm() ||
                (($this->cursor = $v_12) && false) || $this->r_mark_sUn() ||
                (($this->cursor = $v_12) && false) || $this->r_mark_yUz() ||
                ($this->cursor = $v_12);
            if ($this->r_mark_ymUs_()) {
                $this->bra = $this->cursor;
                $this->slice_del();
            } else {
                $this->cursor = $v_11;
            }
            return true;
        }
        $this->cursor = $v_10;
        
        return false;
    }

    // =========================================================================
    // r_stem_suffix_chain_before_ki
    // =========================================================================
    
    private function r_stem_suffix_chain_before_ki() {
        $this->ket = $this->cursor;
        
        if (!$this->r_mark_ki()) {
            return false;
        }
        
        // First option: (mark_DA] delete try([...])
        $v_1 = $this->cursor;
        if ($this->r_mark_DA()) {
            $this->bra = $this->cursor;
            $this->slice_del();
            
            $v_2 = $this->cursor;
            $this->ket = $this->cursor;
            
            // try (mark_lAr] delete try(stem_suffix_chain_before_ki))
            $v_3 = $this->cursor;
            if ($this->r_mark_lAr()) {
                $this->bra = $this->cursor;
                $this->slice_del();
                $this->r_stem_suffix_chain_before_ki();
                return true;
            }
            $this->cursor = $v_3;
            
            // or (mark_possessives] delete try([mark_lAr] delete stem_suffix_chain_before_ki))
            if ($this->r_mark_possessives()) {
                $this->bra = $this->cursor;
                $this->slice_del();
                
                $v_4 = $this->cursor;
                $this->ket = $this->cursor;
                if ($this->r_mark_lAr()) {
                    $this->bra = $this->cursor;
                    $this->slice_del();
                    $this->r_stem_suffix_chain_before_ki();
                }
                return true;
            }
            
            $this->cursor = $v_2;
            return true;
        }
        $this->cursor = $v_1;
        
        // Second option: (mark_nUn] delete try([...])
        $v_5 = $this->cursor;
        if ($this->r_mark_nUn()) {
            $this->bra = $this->cursor;
            $this->slice_del();
            
            $v_6 = $this->cursor;
            $this->ket = $this->cursor;
            
            // (mark_lArI] delete)
            $v_7 = $this->cursor;
            if ($this->r_mark_lArI()) {
                $this->bra = $this->cursor;
                $this->slice_del();
                return true;
            }
            $this->cursor = $v_7;
            
            // or ([mark_possessives or mark_sU] delete try([mark_lAr] delete stem_suffix_chain_before_ki))
            $v_8 = $this->cursor;
            if ($this->r_mark_possessives() || (($this->cursor = $v_8) && false) || $this->r_mark_sU()) {
                $this->bra = $this->cursor;
                $this->slice_del();
                
                $v_9 = $this->cursor;
                $this->ket = $this->cursor;
                if ($this->r_mark_lAr()) {
                    $this->bra = $this->cursor;
                    $this->slice_del();
                    $this->r_stem_suffix_chain_before_ki();
                }
                return true;
            }
            $this->cursor = $v_8;
            
            // or (stem_suffix_chain_before_ki)
            $this->r_stem_suffix_chain_before_ki();
            return true;
        }
        $this->cursor = $v_5;
        
        // Third option: (mark_ndA (...))
        $v_10 = $this->cursor;
        if ($this->r_mark_ndA()) {
            // (mark_lArI] delete)
            $v_11 = $this->cursor;
            if ($this->r_mark_lArI()) {
                $this->bra = $this->cursor;
                $this->slice_del();
                return true;
            }
            $this->cursor = $v_11;
            
            // or ((mark_sU] delete try([mark_lAr]delete stem_suffix_chain_before_ki)))
            $v_12 = $this->cursor;
            if ($this->r_mark_sU()) {
                $this->bra = $this->cursor;
                $this->slice_del();
                
                $v_13 = $this->cursor;
                $this->ket = $this->cursor;
                if ($this->r_mark_lAr()) {
                    $this->bra = $this->cursor;
                    $this->slice_del();
                    $this->r_stem_suffix_chain_before_ki();
                }
                return true;
            }
            $this->cursor = $v_12;
            
            // or (stem_suffix_chain_before_ki)
            $this->r_stem_suffix_chain_before_ki();
            return true;
        }
        $this->cursor = $v_10;
        
        return false;
    }

    // =========================================================================
    // r_stem_noun_suffixes
    // =========================================================================
    
    private function r_stem_noun_suffixes() {
        // ([mark_lAr] delete try(stem_suffix_chain_before_ki))
        $v_1 = $this->cursor;
        $this->ket = $this->cursor;
        if ($this->r_mark_lAr()) {
            $this->bra = $this->cursor;
            $this->slice_del();
            $this->r_stem_suffix_chain_before_ki();
            return true;
        }
        $this->cursor = $v_1;
        
        // or ([mark_ncA] delete try(...))
        $this->ket = $this->cursor;
        if ($this->r_mark_ncA()) {
            $this->bra = $this->cursor;
            $this->slice_del();
            
            $v_2 = $this->cursor;
            // try (([mark_lArI] delete) or ([mark_possessives or mark_sU] delete try([mark_lAr] delete stem_suffix_chain_before_ki)) or ([mark_lAr] delete stem_suffix_chain_before_ki))
            $this->ket = $this->cursor;
            $v_3 = $this->cursor;
            if ($this->r_mark_lArI()) {
                $this->bra = $this->cursor;
                $this->slice_del();
            } else {
                $this->cursor = $v_3;
                $v_4 = $this->cursor;
                if ($this->r_mark_possessives() || (($this->cursor = $v_4) && false) || $this->r_mark_sU()) {
                    $this->bra = $this->cursor;
                    $this->slice_del();
                    
                    $v_5 = $this->cursor;
                    $this->ket = $this->cursor;
                    if ($this->r_mark_lAr()) {
                        $this->bra = $this->cursor;
                        $this->slice_del();
                        $this->r_stem_suffix_chain_before_ki();
                    } else {
                        $this->cursor = $v_5;
                    }
                } else {
                    $this->cursor = $v_4;
                    if ($this->r_mark_lAr()) {
                        $this->bra = $this->cursor;
                        $this->slice_del();
                        $this->r_stem_suffix_chain_before_ki();
                    } else {
                        $this->cursor = $v_2;
                    }
                }
            }
            return true;
        }
        $this->cursor = $v_1;
        
        // or ([(mark_ndA or mark_nA) (...)])
        $this->ket = $this->cursor;
        $v_6 = $this->cursor;
        if ($this->r_mark_ndA() || (($this->cursor = $v_6) && false) || $this->r_mark_nA()) {
            // (mark_lArI] delete) or (mark_sU] delete try([mark_lAr] delete stem_suffix_chain_before_ki)) or (stem_suffix_chain_before_ki)
            $v_7 = $this->cursor;
            if ($this->r_mark_lArI()) {
                $this->bra = $this->cursor;
                $this->slice_del();
                return true;
            }
            $this->cursor = $v_7;
            
            if ($this->r_mark_sU()) {
                $this->bra = $this->cursor;
                $this->slice_del();
                
                $v_8 = $this->cursor;
                $this->ket = $this->cursor;
                if ($this->r_mark_lAr()) {
                    $this->bra = $this->cursor;
                    $this->slice_del();
                    $this->r_stem_suffix_chain_before_ki();
                } else {
                    $this->cursor = $v_8;
                }
                return true;
            }
            $this->cursor = $v_7;
            
            if ($this->r_stem_suffix_chain_before_ki()) {
                return true;
            }
        }
        $this->cursor = $v_1;
        
        // or ([(mark_ndAn or mark_nU) ((mark_sU ] delete try([mark_lAr] delete stem_suffix_chain_before_ki)) or (mark_lArI))])
        $this->ket = $this->cursor;
        $v_9 = $this->cursor;
        if ($this->r_mark_ndAn() || (($this->cursor = $v_9) && false) || $this->r_mark_nU()) {
            $v_10 = $this->cursor;
            if ($this->r_mark_sU()) {
                $this->bra = $this->cursor;
                $this->slice_del();
                
                $v_11 = $this->cursor;
                $this->ket = $this->cursor;
                if ($this->r_mark_lAr()) {
                    $this->bra = $this->cursor;
                    $this->slice_del();
                    $this->r_stem_suffix_chain_before_ki();
                } else {
                    $this->cursor = $v_11;
                }
                return true;
            }
            $this->cursor = $v_10;
            
            if ($this->r_mark_lArI()) {
                $this->bra = $this->cursor;
                $this->slice_del();
                return true;
            }
        }
        $this->cursor = $v_1;
        
        // or ( [mark_DAn] delete try ([...]))
        $this->ket = $this->cursor;
        if ($this->r_mark_DAn()) {
            $this->bra = $this->cursor;
            $this->slice_del();
            
            $v_12 = $this->cursor;
            $this->ket = $this->cursor;
            $v_13 = $this->cursor;
            if ($this->r_mark_possessives()) {
                $this->bra = $this->cursor;
                $this->slice_del();
                
                $v_14 = $this->cursor;
                $this->ket = $this->cursor;
                if ($this->r_mark_lAr()) {
                    $this->bra = $this->cursor;
                    $this->slice_del();
                    $this->r_stem_suffix_chain_before_ki();
                } else {
                    $this->cursor = $v_14;
                }
            } else {
                $this->cursor = $v_13;
                if ($this->r_mark_lAr()) {
                    $this->bra = $this->cursor;
                    $this->slice_del();
                    $this->r_stem_suffix_chain_before_ki();
                } else {
                    $this->cursor = $v_13;
                    $this->r_stem_suffix_chain_before_ki();
                }
            }
            return true;
        }
        $this->cursor = $v_1;
        
        // or ([mark_nUn or mark_ylA] delete try(...))
        $this->ket = $this->cursor;
        $v_15 = $this->cursor;
        if ($this->r_mark_nUn() || (($this->cursor = $v_15) && false) || $this->r_mark_ylA()) {
            $this->bra = $this->cursor;
            $this->slice_del();
            
            $v_16 = $this->cursor;
            // try (([mark_lAr] delete stem_suffix_chain_before_ki) or ([mark_possessives or mark_sU] delete try([mark_lAr] delete stem_suffix_chain_before_ki)) or stem_suffix_chain_before_ki)
            $this->ket = $this->cursor;
            $v_17 = $this->cursor;
            if ($this->r_mark_lAr()) {
                $this->bra = $this->cursor;
                $this->slice_del();
                $this->r_stem_suffix_chain_before_ki();
            } else {
                $this->cursor = $v_17;
                $v_18 = $this->cursor;
                if ($this->r_mark_possessives() || (($this->cursor = $v_18) && false) || $this->r_mark_sU()) {
                    $this->bra = $this->cursor;
                    $this->slice_del();
                    
                    $v_19 = $this->cursor;
                    $this->ket = $this->cursor;
                    if ($this->r_mark_lAr()) {
                        $this->bra = $this->cursor;
                        $this->slice_del();
                        $this->r_stem_suffix_chain_before_ki();
                    } else {
                        $this->cursor = $v_19;
                    }
                } else {
                    $this->cursor = $v_17;
                    $this->r_stem_suffix_chain_before_ki();
                }
            }
            return true;
        }
        $this->cursor = $v_1;
        
        // or ([mark_lArI] delete)
        $this->ket = $this->cursor;
        if ($this->r_mark_lArI()) {
            $this->bra = $this->cursor;
            $this->slice_del();
            return true;
        }
        $this->cursor = $v_1;
        
        // or (stem_suffix_chain_before_ki)
        if ($this->r_stem_suffix_chain_before_ki()) {
            return true;
        }
        $this->cursor = $v_1;
        
        // or ([mark_DA or mark_yU or mark_yA] delete try([((mark_possessives] delete try([mark_lAr)) or mark_lAr) ] delete [ stem_suffix_chain_before_ki))
        $this->ket = $this->cursor;
        $v_20 = $this->cursor;
        if ($this->r_mark_DA() || (($this->cursor = $v_20) && false) || $this->r_mark_yU() || (($this->cursor = $v_20) && false) || $this->r_mark_yA()) {
            $this->bra = $this->cursor;
            $this->slice_del();
            
            $v_21 = $this->cursor;
            $this->ket = $this->cursor;
            $v_22 = $this->cursor;
            if ($this->r_mark_possessives()) {
                $this->bra = $this->cursor;
                $this->slice_del();
                
                $v_23 = $this->cursor;
                $this->ket = $this->cursor;

                if ($this->r_mark_lAr()) {
                    $this->bra = $this->cursor;
                    $this->slice_del();
                    $this->r_stem_suffix_chain_before_ki();
                } else {
                    $this->cursor = $v_23;
                }
            } else {
                $this->cursor = $v_22;
                if ($this->r_mark_lAr()) {
                    // found mark_lAr
                }
            }
            if ($this->cursor != $v_21) {
                $this->bra = $this->cursor;
                $this->slice_del();
            }
            $this->ket = $this->cursor;
            $this->r_stem_suffix_chain_before_ki();
            return true;
        }
        $this->cursor = $v_1;
        
        // or ([mark_possessives or mark_sU] delete try([mark_lAr] delete stem_suffix_chain_before_ki))
        $this->ket = $this->cursor;
        $v_24 = $this->cursor;
        if ($this->r_mark_possessives() || (($this->cursor = $v_24) && false) || $this->r_mark_sU()) {
            $this->bra = $this->cursor;
            $this->slice_del();
            
            $v_25 = $this->cursor;
            $this->ket = $this->cursor;
            if ($this->r_mark_lAr()) {
                $this->bra = $this->cursor;
                $this->slice_del();
                $this->r_stem_suffix_chain_before_ki();
            } else {
                $this->cursor = $v_25;
            }
            return true;
        }
        $this->cursor = $v_1;
        
        return false;
    }

    // =========================================================================
    // r_post_process_last_consonants
    // =========================================================================
    
    private function r_post_process_last_consonants() {
        $this->ket = $this->cursor;
        
        if ($this->cursor <= $this->limit_backward) {
            return false;
        }
        
        $ch = $this->charAt($this->cursor - 1);
        $this->cursor--;
        $this->bra = $this->cursor;
        
        $replacement = null;
        if ($ch === 'b') $replacement = 'p';
        else if ($ch === 'c') $replacement = 'ç';
        else if ($ch === 'd') $replacement = 't';
//        else if ($ch === 'ğ') $replacement = 'k';
        
        if ($replacement !== null) {
            $this->slice_from($replacement);
            return true;
        }
        
        return false;
    }

    // =========================================================================
    // r_append_U_to_stems_ending_with_d_or_g
    // =========================================================================
    
    private function r_append_U_to_stems_ending_with_d_or_g() {
        $len = mb_strlen($this->current, 'UTF-8');
        if ($len < 2) {
            return false;
        }
        
        $last = $this->charAt($len - 1);
        if ($last !== 'd' && $last !== 'g') {
            return false;
        }
        
        // Find the last vowel
        $last_vowel = null;
        for ($i = $len - 2; $i >= 0; $i--) {
            $ch = $this->charAt($i);
            if (in_array($ch, self::$g_vowel)) {
                $last_vowel = $ch;
                break;
            }
        }
        
        if ($last_vowel === null) {
            return false;
        }
        
        // Determine which vowel to append
        $append = '';
        if ($last_vowel === 'a' || $last_vowel === 'ı') {
            $append = 'ı';
        } else if ($last_vowel === 'e' || $last_vowel === 'i') {
            $append = 'i';
        } else if ($last_vowel === 'o' || $last_vowel === 'u') {
            $append = 'u';
        } else if ($last_vowel === 'ö' || $last_vowel === 'ü') {
            $append = 'ü';
        }
        
        if ($append !== '') {
            // Insert at end
            $this->current .= $append;
            $this->limit = mb_strlen($this->current, 'UTF-8');
            return true;
        }
        
        return false;
    }

    // =========================================================================
    // r_is_reserved_word
    // 'ad' try 'soy' atlimit
    // =========================================================================
    
    private function r_is_reserved_word() {
        // Check if word is 'ad' or 'soyad'
        if (!$this->eq_s_b('da')) { // 'ad' backwards
            return false;
        }
        
        $v_1 = $this->cursor;
        $this->eq_s_b('yos'); // 'soy' backwards - optional (try)
        $this->cursor = $v_1; // reset for atlimit check
        
        // atlimit - cursor should be at limit_backward
        return $this->cursor == $this->limit_backward;
    }

    // =========================================================================
    // r_stem_derivational_suffixes
    // Handles verb derivation suffixes not covered by the standard Snowball stemmer
    // =========================================================================
    
    private function r_stem_derivational_suffixes() {
        $this->ket = $this->cursor;
        
        // Try each derivational suffix pattern
        // Order matters - try longer/more specific patterns first
        
        // -AmAyAcAk (negative future: -amayacak, -emeyecek)
        $v_1 = $this->cursor;
        if ($this->r_mark_AmAyAcAk()) {
            $this->bra = $this->cursor;
            $this->slice_del();
            $this->B_in_verb_chain_context = true;
            $this->r_maybe_remove_causative_t();
            return true;
        }
        $this->cursor = $v_1;
        
        // -AbIlEcEk (ability future: -abilecek, -ebilecek)
        if ($this->r_mark_AbIlEcEk()) {
            $this->bra = $this->cursor;
            $this->slice_del();
            $this->B_in_verb_chain_context = true;
            $this->r_maybe_remove_causative_t();
            return true;
        }
        $this->cursor = $v_1;
        
        // -AcAk (future: -acak, -ecek)
        if ($this->r_mark_AcAk()) {
            $this->bra = $this->cursor;
            $this->slice_del();
            $this->B_in_verb_chain_context = true;
            $this->r_maybe_remove_causative_t();
            return true;
        }
        $this->cursor = $v_1;
        
        // -Iyor (present continuous: -ıyor, -iyor, -uyor, -üyor)
        if ($this->r_mark_Iyor()) {
            $this->bra = $this->cursor;
            $this->slice_del();
            $this->B_in_verb_chain_context = true;
            $this->r_maybe_remove_causative_t();
            return true;
        }
        $this->cursor = $v_1;
        
        // -AbIl (ability: -abil, -ebil)
        if ($this->r_mark_AbIl()) {
            $this->bra = $this->cursor;
            $this->slice_del();
            return true;
        }
        $this->cursor = $v_1;
        
        // -AmA (negative ability: -ama, -eme)
        if ($this->r_mark_AmA()) {
            $this->bra = $this->cursor;
            $this->slice_del();
            return true;
        }
        $this->cursor = $v_1;
        
        // -mAk (infinitive: -mak, -mek)
        if ($this->r_mark_mAk()) {
            $this->bra = $this->cursor;
            $this->slice_del();
            $this->B_in_verb_chain_context = true;
            $this->r_maybe_remove_causative_t();
            return true;
        }
        $this->cursor = $v_1;
        
// noun derivation: -lık/-lik/-luk/-lük
$v0 = $this->cursor;
if ($this->r_mark_lIk()) {
    if ($this->r_safe_min_stem_after_match(3)) {
        $this->bra = $this->cursor;
        $this->slice_del();
        return true;
    }
    $this->cursor = $v0;
}
        
        // -lAş (becoming: -laş, -leş)
        if ($this->B_in_verb_chain_context && $this->r_mark_lAs()) {
            $this->bra = $this->cursor;
            $this->slice_del();
            $this->r_maybe_remove_causative_t();
            return true;
        }
        $this->cursor = $v_1;
        
        // -lAn (acquiring: -lan, -len)
        if ($this->B_in_verb_chain_context && $this->r_mark_lAn()) {
            $this->bra = $this->cursor;
            $this->slice_del();
            $this->r_maybe_remove_causative_t();
            return true;
        }
        $this->cursor = $v_1;
        
        // -DUr / -DIr etc should only be removed in verb chains
        if ($this->B_in_verb_chain_context && $this->r_mark_DUr()) {
            $this->bra = $this->cursor;
            $this->slice_del();
            $this->r_maybe_remove_causative_t();
            return true;
        }

        // -Il passive should only be removed in verb chains
        if ($this->B_in_verb_chain_context && $this->r_mark_Il()) {
            $this->bra = $this->cursor;
            $this->slice_del();
            $this->r_maybe_remove_causative_t();
            return true;
        }
        
        return false;
    }
    
    private function r_safe_min_stem_after_match($min_len = 3) {
        $remaining_len = $this->cursor;
        if ($remaining_len < $min_len) return false;

        $stem = mb_substr($this->current, 0, $remaining_len, 'UTF-8');
        $L = mb_strlen($stem, 'UTF-8');
        for ($i = 0; $i < $L; $i++) {
            if (in_array(mb_substr($stem, $i, 1, 'UTF-8'), self::$g_vowel, true)) {
                return true;
            }
        }
        return false;
    }

    private function r_maybe_remove_causative_t() {
        $len = mb_strlen($this->current, 'UTF-8');
        if ($len < 4) return false; // need at least ...l V t

        // must end in 't'
        if ($this->charAt($len - 1) !== 't') return false;

        // must be preceded by a vowel
        $vowel = $this->charAt($len - 2);
        if (!in_array($vowel, self::$g_vowel, true)) return false;

        // NEW: only treat as causative -t if it is ...l + vowel + t
        // (dinle->dinlet, izle->izlet, söyle->söylet, etc.)
        $beforeVowel = $this->charAt($len - 3);
        if ($beforeVowel !== 'l') return false;

        // delete last char 't'
        $this->bra = $len - 1;
        $this->ket = $len;
        $this->slice_del();
        return true;
    }

    // Derivational suffix markers
    
    private function r_mark_AmAyAcAk() {
        $among = [['amayacak'], ['emeyecek']];
        if ($this->find_among_b($among) == 0) return false;

        // remove buffer y if present (and validate attachment if absent)
        return $this->r_mark_suffix_with_optional_y_consonant();
    }

    
    private function r_mark_AbIlEcEk() {
        // -abilecek, -ebilecek (ability future)
        $among = [['abilecek'], ['ebilecek']];
        if ($this->find_among_b($among) == 0) return false;

        // remove buffer y if present (and validate attachment if absent)
        return $this->r_mark_suffix_with_optional_y_consonant();
    }
    
    private function r_mark_AcAk() {
        // -acak, -ecek (future)
        if (!$this->r_check_vowel_harmony()) {
            return false;
        }
        $among = [['acak'], ['ecek']];
        if ($this->find_among_b($among) == 0) {
            return false;
        }
        return $this->r_mark_suffix_with_optional_y_consonant();
    }
    
    private function r_mark_Iyor() {
        // -ıyor, -iyor, -uyor, -üyor (present continuous)
        $among = [['ıyor'], ['iyor'], ['uyor'], ['üyor']];
        return $this->find_among_b($among) != 0;
    }
    
    private function r_mark_AbIl() {
        // -abil, -ebil (ability)
        if (!$this->r_check_vowel_harmony()) {
            return false;
        }
        $among = [['abil'], ['ebil']];
        if ($this->find_among_b($among) == 0) {
            return false;
        }
        return $this->r_mark_suffix_with_optional_y_consonant();
    }
    
    private function r_mark_AmA() {
        // -ama, -eme (negative ability) - but be careful, many words end in these
        if (!$this->r_check_vowel_harmony()) {
            return false;
        }
        $among = [['ama'], ['eme']];
        if ($this->find_among_b($among) == 0) {
            return false;
        }
        // Only remove if preceded by a consonant (to avoid removing from words like "anne")
        if ($this->cursor <= $this->limit_backward) {
            return false;
        }
        $ch = $this->charAt($this->cursor - 1);
        if (in_array($ch, self::$g_vowel)) {
            return false;
        }
        return $this->r_mark_suffix_with_optional_y_consonant();
    }
    
    private function r_mark_mAk() {
        // -mak, -mek (infinitive)
        if (!$this->r_check_vowel_harmony()) {
            return false;
        }
        $among = [['mak'], ['mek']];
        return $this->find_among_b($among) != 0;
    }
    
    private function r_mark_lIk() {
        // -lık, -lik, -luk, -lük (noun forming)
        if (!$this->r_check_vowel_harmony()) {
            return false;
        }
        $among = [['lık'], ['lik'], ['luk'], ['lük']];
        return $this->find_among_b($among) != 0;
    }
    
    private function r_mark_lAs() {
        // -laş, -leş (becoming)
        if (!$this->r_check_vowel_harmony()) {
            return false;
        }
        $among = [['laş'], ['leş']];
        return $this->find_among_b($among) != 0;
    }
    
    private function r_mark_lAn() {
        if (!$this->r_check_vowel_harmony()) return false;

        $v0 = $this->cursor;
        $among = [['lan'], ['len']];
        if ($this->find_among_b($among) == 0) return false;

        // NEW: prevent planlan->p
        if (!$this->r_safe_min_stem_after_match(3)) {
            $this->cursor = $v0;
            return false;
        }

        return $this->r_mark_suffix_with_optional_y_consonant();
    }
    
    private function r_mark_Il() {
        if (!$this->r_check_vowel_harmony()) return false;

        $v0 = $this->cursor;
        $among = [['ıl'], ['il'], ['ul'], ['ül']];
        if ($this->find_among_b($among) == 0) return false;

        // allow 2-letter stems like "aç"
        $remaining_len = $this->cursor;
        if ($remaining_len < 2) {
            $this->cursor = $v0;
            return false;
        }

        // remaining must contain a vowel (still blocks bil->b)
        $stem = mb_substr($this->current, 0, $remaining_len, 'UTF-8');
        $has_vowel = false;
        $L = mb_strlen($stem, 'UTF-8');
        for ($i = 0; $i < $L; $i++) {
            if (in_array(mb_substr($stem, $i, 1, 'UTF-8'), self::$g_vowel, true)) {
                $has_vowel = true;
                break;
            }
        }
        if (!$has_vowel) {
            $this->cursor = $v0;
            return false;
        }

        return true;
    }

    // =========================================================================
    // r_postlude
    // =========================================================================
    
    private function r_postlude() {
        $this->limit_backward = 0;
        $this->cursor = $this->limit;

        $v_1 = $this->cursor;
        if ($this->r_is_reserved_word()) {
            $this->cursor = $v_1;
            return false;
        }
        $this->cursor = $v_1;

        // Only do these if we actually deleted a suffix somewhere
        if ($this->B_did_delete) {
            // allow for shorter stems now
            $this->cursor = $this->limit;
            $v_2 = $this->cursor;
            $this->r_append_U_to_stems_ending_with_d_or_g();
            $this->cursor = $v_2;

            $this->cursor = $this->limit;
            $v_3 = $this->cursor;
            $this->r_post_process_last_consonants();
            $this->cursor = $v_3;
        }

        return true;
    }

    // =========================================================================
    // r_remove_proper_noun_suffix
    // =========================================================================
    
    private function r_remove_proper_noun_suffix() {
        // Remove leading apostrophes
        while (mb_strlen($this->current, 'UTF-8') > 0 && $this->charAt(0) === "'") {
            $this->current = mb_substr($this->current, 1, null, 'UTF-8');
            $this->limit = mb_strlen($this->current, 'UTF-8');
        }
        
        // Find apostrophe after at least 2 characters
        $len = mb_strlen($this->current, 'UTF-8');
        if ($len > 2) {
            // hop 2 goto '
            for ($i = 2; $i < $len; $i++) {
                if ($this->charAt($i) === "'") {
                    // [ tolimit ] delete - delete everything after apostrophe
                    $this->current = mb_substr($this->current, 0, $i, 'UTF-8');
                    $this->limit = $i;
                    break;
                }
            }
        }
        
        return true;
    }

    // =========================================================================
    // r_more_than_one_syllable_word
    // test (loop 2 gopast vowel)
    // =========================================================================
    
    private function r_more_than_one_syllable_word() {
        $v_1 = $this->cursor;
        
        // loop 2 gopast vowel
        $vowel_count = 0;
        $len = mb_strlen($this->current, 'UTF-8');
        
        for ($i = 0; $i < $len && $vowel_count < 2; $i++) {
            if (in_array($this->charAt($i), self::$g_vowel)) {
                $vowel_count++;
            }
        }
        
        $this->cursor = $v_1; // test restores cursor
        return $vowel_count >= 2;
    }

    // =========================================================================
    // Cache methods
    // =========================================================================
    
    public static function get_cached_stem($word = '') {
        if (empty($word) || !isset(self::$stem_cache[$word])) {
            return false;
        }
        return self::$stem_cache[$word];
    }

    public static function update_cached_stem($word, $stemmed_word) {
        if (empty($word) || empty($stemmed_word) || isset(self::$stem_cache[$word])) {
            return false;
        }
        self::$stem_cache[$word] = $stemmed_word;
        if (count(self::$stem_cache) > 25000) {
            unset(self::$stem_cache[key(self::$stem_cache)]);
        }
    }

    public static function codes_to_chars($string) {
        return html_entity_decode(mb_convert_encoding($string, "HTML-ENTITIES"), ENT_QUOTES, 'UTF-8');
    }
}
?>
