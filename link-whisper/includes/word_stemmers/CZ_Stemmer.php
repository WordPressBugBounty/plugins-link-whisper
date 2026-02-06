<?php

class Wpil_Stemmer {

    static $stem_cache = array();

    /**
     * UTF-8 vowels used by the Snowball definition:
     * v = a e i o u y á ě é í ó ú ů ý
     */
    private const VOWELS = [
        'a','e','i','o','u','y','á','ě','é','í','ó','ú','ů','ý',
    ];

    // Cached lengths for speed
    private static array $lenCache = [];


    public static function Stem($word, $deaccent = false, $ignore_cache = false){
        // first check if we've already stemmed the word
        $cached = self::get_cached_stem($word);
        if(!empty($cached)){
            // if we have return the cached
            return $cached;
        }

        $original_word = $word;

        $w = self::normalize($word);
        if ($w === '') {
            return $w;
        }

        [$pV, $p1] = self::markRegions($w);

        // Backward passes (Snowball order)
        $w = self::do_case($w, $pV, $p1);
        $w = self::do_possessive($w, $pV, $p1);
        // Aggressive mode (as in your Snowball file comment)
        $w = self::do_comparative($w, $pV, $p1);
        $w = self::do_diminutive($w, $pV, $p1);
        $w = self::do_augmentative($w, $pV, $p1);
        // do_derivational OR do_deriv_single (match derivational first; if none, try single)
        $before = $w;
        $w = self::do_derivational($w, $pV, $p1);
        if ($w === $before) {
            $w = self::do_deriv_single($w, $p1); // pass p1
        }

        // and update the cache with the (hopefully) stemmed word
        self::update_cached_stem($original_word, $w);

        return $w;
    }
    
    /**
     * Checks to see if the word was previously stemmed and is in the stem cache.
     * If it is in the cache, it returns the cached word so we don't have to run through the process again.
     * Returns false if the word hasn't been stemmed yet, or the "word" isn't a word
     **/
    public static function get_cached_stem($word = ''){
        if(empty($word) || !isset(self::$stem_cache[$word]) || !is_string($word)){
            return false;
        }

        return self::$stem_cache[$word];
    }

    /**
     * Updates the stemmed word cache when we come across a word that we haven't stemmed yet.
     * Also does some housekeeping to make sure the cache doesn't grow too big
     **/
    public static function update_cached_stem($word, $stemmed_word){
        if(empty($word) || empty($stemmed_word) || isset(self::$stem_cache[$word]) || !is_string($word)){
            return false;
        }

        self::$stem_cache[$word] = $stemmed_word;

        if(count(self::$stem_cache) > 25000){
            $ind = key(self::$stem_cache);
            unset(self::$stem_cache[$ind]);
        }
    }

    /* ------------------------- Utilities ------------------------- */

    private static function normalize($s)
    {
        // Normalize to NFC if intl is available, then lowercase.
        if (class_exists('\Normalizer')) {
            $s = \Normalizer::normalize($s, \Normalizer::FORM_C) ?? $s;
        } else {
            // Minimal fold for common combining acute sequences → precomposed
            $map = [
                "/a\u{0301}/u" => 'á',
                "/e\u{0301}/u" => 'é',
                "/i\u{0301}/u" => 'í',
                "/o\u{0301}/u" => 'ó',
                "/u\u{0301}/u" => 'ú',
                "/y\u{0301}/u" => 'ý',
                "/u\u{030A}/u" => 'ů',
                "/c\u{030C}/u" => 'č',
                "/d\u{030C}/u" => 'ď',
                "/e\u{030C}/u" => 'ě',
                "/n\u{030C}/u" => 'ň',
                "/r\u{030C}/u" => 'ř',
                "/s\u{030C}/u" => 'š',
                "/t\u{030C}/u" => 'ť',
                "/z\u{030C}/u" => 'ž',
            ];
            $s = preg_replace(array_keys($map), array_values($map), $s);
        }
        $s = mb_strtolower($s, 'UTF-8');
        return trim($s);
    }


    private static function len($s)
    {
        if(!isset(self::$lenCache[$s]) || is_null(self::$lenCache[$s])){
            self::$lenCache[$s] = mb_strlen($s, 'UTF-8');
        }
        return self::$lenCache[$s];
    }

    private static function endsWith($s, $suffix)
    {
        $ls = self::len($s);
        $lf = self::len($suffix);
        if ($lf > $ls) return false;
        return mb_substr($s, $ls - $lf, null, 'UTF-8') === $suffix;
    }

    private static function cutSuffix($s, $suffix)
    {
        return mb_substr($s, 0, self::len($s) - self::len($suffix), 'UTF-8');
    }

    private static function replaceSuffix($s, $suffix, $with)
    {
        return self::cutSuffix($s, $suffix) . $with;
    }

    private static function isVowel($ch)
    {
        return in_array($ch, self::VOWELS, true);
    }

    private static function charAt($s, $i)
    {
        return mb_substr($s, $i, 1, 'UTF-8');
    }

    /**
     * Implements the Snowball 'mark_regions' block:
     *   pV = limit; p1 = limit;
     *   do(
     *     gopast non-v setmark pV
     *     gopast non-v gopast v setmark p1
     *   )
     *
     * Interpreted as:
     * - pV: position *after* the first vowel that is preceded by a consonant.
     * - p1: position *after* the next vowel that is preceded by a consonant (the classic R1-like rule).
     *
     * Returns [pV, p1] as absolute character indices (0..len).
     */
    private static function markRegions($w)
    {
        $n = self::len($w);
        $pV = $n;
        $p1 = $n;

        // pV: first (consonant, vowel) — mark AT that vowel (index i)
        for ($i = 1; $i < $n; $i++) {
            if (!self::isVowel(self::charAt($w, $i - 1)) && self::isVowel(self::charAt($w, $i))) {
                $pV = $i;
                break;
            }
        }

        // p1: next (consonant, vowel) after pV — mark AT that vowel
        for ($i = max(1, $pV + 1); $i < $n; $i++) {
            if (!self::isVowel(self::charAt($w, $i - 1)) && self::isVowel(self::charAt($w, $i))) {
                $p1 = $i;
                break;
            }
        }

        // Clamp
        //$pV = min($pV, $n);
        //$p1 = min($p1, $n);

        return [$pV, $p1];
    }


    private static function inRV($w, $pV, $suffix)
    {
        $start = self::len($w) - self::len($suffix);
        return $start >= $pV;
    }

    private static function inR1($w, $p1, $suffix)
    {
        $start = self::len($w) - self::len($suffix);
        return $start >= $p1;
    }

    /**
     * Palatalize end per Snowball 'palatalise' among:
     *   'ci' 'ce' 'či' 'č'     -> 'k'
     *   'zi' 'ze' 'ži' 'že'    -> 'h'
     *   'čtě' 'čti' 'čté'      -> 'ck'
     *   'ště' 'šti' 'šté'      -> 'sk'
     */
    private static function palatalise($w)
    {
        $map = [
            // -> 'k'
            ['ci','k'], ['ce','k'], ['či','k'], ['č','k'],
            // -> 'h'
            ['zi','h'], ['ze','h'], ['ži','h'], ['že','h'],
            // -> 'ck'
            ['čtě','ck'], ['čti','ck'], ['čté','ck'],
            // -> 'sk'
            ['ště','sk'], ['šti','sk'], ['šté','sk'],
        ];

        foreach ($map as [$suf, $rep]) {
            if (self::endsWith($w, $suf)) {
                return self::replaceSuffix($w, $suf, $rep);
            }
        }
        return $w;
    }

    /* ------------------------- Passes (backward mode) ------------------------- */

    private static function do_possessive($w, $pV, $p1)
    {
        // In Snowball: RV among ('ov','ův' => delete) ; 'in' => delete + try palatalise
        $rules = [
            ['ov',   function($w){ return self::cutSuffix($w, 'ov'); }, true],
            ['ův',   function($w){ return self::cutSuffix($w, 'ův'); }, true],
            ['in',   function($w){ $w = self::cutSuffix($w, 'in'); return self::palatalise($w); }, true],
        ];

        foreach ($rules as [$suf, $fn, $rvOnly]) {
            if (self::endsWith($w, $suf) && (!$rvOnly || self::inRV($w, $pV, $suf))) {
                return $fn($w);
            }
        }
        return $w;
    }

    private static function do_case($w, $pV, $p1)
    {
        // Pure deletes (longest-first)
        $delete = [
            'atech',
            'ětem','atům',
            'ách','ých','ové','ými',
            'ata','aty','ama','ami','ovi',
            'at','ám','os','us','ým','mi','ou',
            'u','y','ů','a','o','á','é','ý',
        ];
        foreach ($delete as $s) {
            if (self::endsWith($w, $s)) {
                return self::cutSuffix($w, $s);
            }
        }

        // Delete THEN palatalise
        $delPal = [
            'ech','ich','ích',
            'ého','ěmi','ému','ěte','ěti','ího','ími',
            'emi','iho','imu',
            'ém','ím','es',
            'e','i','í','ě',
        ];
        foreach ($delPal as $s) {
            if (self::endsWith($w, $s)) {
                $w = self::cutSuffix($w, $s);
                return self::palatalise($w);
            }
        }

        // Special: 'em' -> 'e' + pal
        if (self::endsWith($w, 'em')) {
            $w = self::replaceSuffix($w, 'em', 'e');
            return self::palatalise($w);
        }

        return $w;
    }

    private static function do_derivational($w, $pV, $p1)
    {
        // R1-only big set; many delete, some replace + palatalise
        $delR1 = [
            'obinec',
            'ovisk','ovstv','ovišt','ovník',
            'ásek','loun','nost','teln','ovec','ovník','ovtv','ovin','štin',
            'árn','och','ost','ovn','oun','out','ouš','uš',
            'kyn','čan','ář','ěř','ník','ctv','stv',
            'áč','ač','án','an','ář','as',
            'ob','ot','ov','oň','ul','yn',
            'čk','čn','dl','nk','tv','tk','vk',
        ];
        foreach ($delR1 as $s) {
            if (self::endsWith($w, $s) && self::inR1($w, $p1, $s)) {
                return self::cutSuffix($w, $s);
            }
        }

        // 'ionář' | 'inec' 'itel' | 'ián' 'ist' 'isk' 'išk' 'itb' | 'ic' 'in' 'it' 'iv'
        // with replacements then palatalise as Snowball
        $repPal_R1 = [
            // <- 'i' + pal
            ['ionář', 'i'],
            ['inec',  'i'],
            ['itel',  'i'],
            ['ián',   'i'],
            ['ist',   'i'],
            ['isk',   'i'],
            ['išk',   'i'],
            ['itb',   'i'],
            ['ic',    'i'],
            ['in',    'i'],
            ['it',    'i'],
            ['iv',    'i'],
        ];
        foreach ($repPal_R1 as [$suf, $with]) {
            if (self::endsWith($w, $suf) && self::inR1($w, $p1, $suf)) {
                $w = self::replaceSuffix($w, $suf, $with);
                return self::palatalise($w);
            }
        }

        // 'enic' 'ec' 'en'  <- 'e' + pal
        $repEPal_R1 = ['enic','ec','en'];
        foreach ($repEPal_R1 as $suf) {
            if (self::endsWith($w, $suf) && self::inR1($w, $p1, $suf)) {
                $w = self::replaceSuffix($w, $suf, 'e');
                return self::palatalise($w);
            }
        }

        // 'eř' <- 'e' + pal
        if (self::endsWith($w, 'eř') && self::inR1($w, $p1, 'eř')) {
            $w = self::replaceSuffix($w, 'eř', 'e');
            return self::palatalise($w);
        }

        // 'ěn' <- 'ě' + pal
        if (self::endsWith($w, 'ěn') && self::inR1($w, $p1, 'ěn')) {
            $w = self::replaceSuffix($w, 'ěn', 'ě');
            return self::palatalise($w);
        }

        // 'írn' | 'íř' | 'ín' <- 'í' + pal
        foreach (['írn','íř','ín'] as $suf) {
            if (self::endsWith($w, $suf) && self::inR1($w, $p1, $suf)) {
                $w = self::replaceSuffix($w, $suf, 'í');
                return self::palatalise($w);
            }
        }

        return $w;
    }

    private static function do_deriv_single($w, $p1)
    {
        foreach (['c','č','k','l','n','t'] as $ch) {
            if (self::endsWith($w, $ch)) {
                $start = self::len($w) - self::len($ch);
                if ($start >= $p1) {
                    return self::cutSuffix($w, $ch);
                }
            }
        }
        return $w;
    }

    private static function do_augmentative($w, $pV, $p1)
    {
        // delete 'ajzn' 'ák' ; 'izn' 'isk' -> '<- i' + pal
        foreach (['ajzn','ák'] as $s) {
            if (self::endsWith($w, $s)) return self::cutSuffix($w, $s);
        }
        foreach (['izn','isk'] as $s) {
            if (self::endsWith($w, $s)) {
                $w = self::replaceSuffix($w, $s, 'i');
                return self::palatalise($w);
            }
        }
        return $w;
    }

    private static function do_diminutive($w, $pV, $p1)
    {
        // Combine ALL diminutive options; pick the LONGEST match exactly once.
        $rules = [];

        // 1) Deletes
        foreach ([
            'oušek','áček','aček','oček','uček',
            'anek','onek','unek','ánek',
            'ečk','éčk','ičk','íčk','enk','énk','ink','ínk',
            'áčk','ačk','očk','učk','ank','onk','unk',
            'átk','ánk','uš',
            'k',
        ] as $suf) {
            $rules[] = [$suf, function($w) use ($suf) {
                return self::cutSuffix($w, $suf);
            }];
        }

        foreach ([['íčka','í'], ['ička','i'], ['áčka','á'], ['ačka','a'], ['očka','o'], ['učka','u']] as [$suf,$with]) {
            $rules[] = [$suf, function($w) use ($suf,$with) {
                return self::replaceSuffix($w, $suf, $with); // no palatalise here
            }];
        }

        // 2) Replacements + palatalise
        foreach ([['ečkem','e'], ['ečke','e'], ['eček','e'], ['enek','e'], ['ek','e']] as [$suf,$with]) {
            $rules[] = [$suf, function($w) use ($suf,$with) {
                $w = self::replaceSuffix($w, $suf, $with);
                return self::palatalise($w);
            }];
        }
        foreach ([['éček','é'], ['ék','é']] as [$suf,$with]) {
            $rules[] = [$suf, function($w) use ($suf,$with) {
                $w = self::replaceSuffix($w, $suf, $with);
                return self::palatalise($w);
            }];
        }
        foreach ([['iček','i'], ['inek','i'], ['ik','i']] as [$suf,$with]) {
            $rules[] = [$suf, function($w) use ($suf,$with) {
                $w = self::replaceSuffix($w, $suf, $with);
                return self::palatalise($w);
            }];
        }
        foreach ([['íček','í'], ['ík','í']] as [$suf,$with]) {
            $rules[] = [$suf, function($w) use ($suf,$with) {
                $w = self::replaceSuffix($w, $suf, $with);
                return self::palatalise($w);
            }];
        }

        // 3) 'ák'/'ak'/'ok'/'uk' -> single vowel
        foreach ([['ák','á'], ['ak','a'], ['ok','o'], ['uk','u']] as [$suf,$with]) {
            $rules[] = [$suf, function($w) use ($suf,$with) {
                return self::replaceSuffix($w, $suf, $with);
            }];
        }

        return self::applyLongestSuffix($w, $rules);
    }


    private static function applyLongestSuffix($w, $rules)
    {
        // $rules: [ [suffix, action], ... ], where action is a closure($w): string
        $best = null;
        foreach ($rules as [$suf, $fn]) {
            if (self::endsWith($w, $suf)) {
                if ($best === null || self::len($suf) > self::len($best[0])) {
                    $best = [$suf, $fn];
                }
            }
        }
        return $best ? $best[1]($w) : $w;
    }


    private static function do_comparative($w, $pV, $p1)
    {
        // 'ějš' <- 'ě' + pal ; 'ejš' <- 'e' + pal
        if (self::endsWith($w, 'ějš')) {
            $w = self::replaceSuffix($w, 'ějš', 'ě');
            return self::palatalise($w);
        }
        if (self::endsWith($w, 'ejš')) {
            $w = self::replaceSuffix($w, 'ejš', 'e');
            return self::palatalise($w);
        }
        return $w;
    }
}

?>