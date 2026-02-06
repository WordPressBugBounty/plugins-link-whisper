<?php

class Wpil_Stemmer {

    static $stem_cache = array();
    static $bg_min_base = 3; // minimum remaining length after a strip/transform
    // Indeclinable or non-plural words ending in -и that we should not strip
    static $bg_no_strip_i = [
        'такси','киви','суши','алиби','лоби','спагети', // common loanwords
        // very common function words or forms you likely do not want to stem
        'или','при','дори','ми','ти','ни','винаги','къде',
        // frequent imperatives that end in -и (tune as you like)
        'иди','кажи','върви',
    ];

    public static function Stem($word, $deaccent = false, $ignore_cache = false){
        // first check if we've already stemmed the word
        $cached = self::get_cached_stem($word);
        if(!empty($cached)){
            // if we have return the cached
            return $cached;
        }

        $original_word = $word;

        $word = self::process($word);

        // and update the cache with the (hopefully) stemmed word
        self::update_cached_stem($original_word, $word);

        return $word;
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

    public static function process($line)
    {
        // Strip comparative/superlative prefixes before anything else
        if (preg_match('/^(по|най)-/u', $line)) {
            $line = preg_replace('/^(по|най)-/u', '', $line);
        }

        // Special-case common adverb: зле → зл
        if (mb_strtolower($line, 'UTF-8') === 'зле') {
            $line = 'зл';
        }

        // Flags to guide later decisions
        $from_aya = false;   // e.g., стаЯ → стаЙ (keep that Й)
        $from_oj  = false;   // e.g., герОЙ → герО (keep that final О)

        $line = self::remove_article($line);
        $line = self::remove_plural($line);
        $line = self::remove_verb_endings($line);

        // ...ъл -> ...л  (мисъл, мисълта, мислите → мисл)
        if (preg_match('/ъ(?=л($|[аиете]))/u', $line)) {
            $line = preg_replace('/ъ(?=л($|[аиете]))/u', '', $line);
        }

        // Collapse doubled final consonant: ...тт/лл/сс/... → single
        if (preg_match('/([бвгджзклмнпрстфхцчшщ])\\1$/u', $line)) {
            $line = preg_replace('/([бвгджзклмнпрстфхцчшщ])\\1$/u', '$1', $line);
        }

        // Map ...ая -> ...ай (e.g., стая -> стай) and remember we came from -ая
        if (preg_match('/ая$/u', $line)) {
            $line = preg_replace('/ая$/u', 'ай', $line);
            $from_aya = true;
        }

        // Generic final '-я' (not '-ая'): идея → иде, говоря/работя → говор/работ
        if (self::bg_ends_with($line, 'я') && !preg_match('/ая$/u', $line)) {
            $cand = self::bg_substr($line, 0, self::bg_len($line) - 1);
            if (self::bg_len($cand) >= 2) $line = $cand;
        }

        // If ends with consonant+ой, we will keep the 'о' after removing 'й'
        if (preg_match('/[бвгджзйклмнпрстфхцчшщ]ой$/u', $line)) {
            $from_oj = true;
        }

        // Drop final 'й' (герой→геро, чай→ча, стай: protected below)
        if (self::bg_ends_with($line, 'й')) {
            if (!$from_aya) {
                $cand = self::bg_substr($line, 0, self::bg_len($line) - 1);
                if (self::bg_len($cand) >= 2) $line = $cand;
            }
        }

        // Normalize trailing vowels [аое], but keep 'о' for former ...ой (герой→геро)
        if (preg_match('/[аео]$/u', $line)) {
            $last = self::bg_last_char($line);
            if ($last === 'о' && $from_oj) {
                // keep 'о' (геро)
            } else {
                $cand = self::bg_substr($line, 0, self::bg_len($line) - 1);
                if (self::bg_len($cand) >= self::$bg_min_base) $line = $cand;
            }
        }

        // Optional adjective lemma fixes for UI grouping
        static $ADJ_FIX = [
            // нов family (handles no, ноа, ноо, нои)
            'но' => 'нов', 'ноа' => 'нов', 'ноо' => 'нов', 'нои' => 'нов',
            // красив family
            'крас' => 'красив', 'краси' => 'красив',
            // бърз family
            'бър' => 'бърз',
            // умен family
            'уме' => 'умен',
            // щастлив family
            'щастл' => 'щастлив', 'щастли' => 'щастлив',
            // силен / тих / лесен
            'сил' => 'силен', 'ти' => 'тих', 'лес' => 'лесен',
        ];
        $lower = mb_strtolower($line, 'UTF-8');
        if (isset($ADJ_FIX[$lower])) {
            $line = $ADJ_FIX[$lower];
        }

        return $line;
    }

    public static function remove_article($word)
    {
        // -ият (adj masc definite)
        if (($cand = self::strip_suffix_guarded($word, 'ият', self::$bg_min_base, false)) !== null) return $cand;

        // These are safe always
        foreach (['ия','ът','то','ят'] as $art) {
            if (($cand = self::strip_suffix_guarded($word, $art, self::$bg_min_base, false)) !== null) return $cand;
        }

        // 'те' (plural definite) — only if base before 'те' looks plural-ish
        if (self::bg_ends_with($word, 'те')) {
            $base = self::bg_substr($word, 0, self::bg_len($word) - 2);
            if (preg_match('/(и|а|я|ове|еве)$/u', $base) && self::bg_len($base) >= self::$bg_min_base) {
                return $base;
            }
        }

        // 'та' — feminine singular definite OR plural-in--a (but not masc like "студента")
        if (self::bg_ends_with($word, 'та')) {
            $base = self::bg_substr($word, 0, self::bg_len($word) - 2);
            // Heuristics: allow if base ends with a vowel that typically marks fem/plural,
            // or ends with "ъл" (e.g., "мисълта" -> "мисъл").
            if (preg_match('/[аяиеоуъю]$/u', $base) || preg_match('/ъл$/u', $base)) {
                if (self::bg_len($base) >= self::$bg_min_base) return $base;
            }
        }

        // final '-я' as definite (masc animate, some nouns): учителя → учител, лекаря → лекар
        // DO NOT touch '-ая' here (стая handled elsewhere as ая→ай)
        if (self::bg_ends_with($word, 'я') && !preg_match('/ая$/u', $word)) {
            $base = self::bg_substr($word, 0, self::bg_len($word) - 1);
            if (self::bg_len($base) >= self::$bg_min_base) return $base;
        }

        return $word;
    }

    public static function remove_plural($word)
    {
        $lower = mb_strtolower($word, 'UTF-8');

        // Neuter plural/definite: ...ета / ...етата  (море→морета→мор)
        // If matches, drop 'ет(а|ата)' and also drop the preceding 'е'
        if (preg_match('/^(.+?)ет(а|ата)$/u', $word, $m)) {
            $w = $m[1];
            if (self::bg_ends_with($w, 'е')) $w = self::bg_substr($w, 0, self::bg_len($w) - 1);
            if (self::bg_len($w) >= self::$bg_min_base) return $w;
        }

        // 1) -ове / -еве (masc plur)
        if (($cand = self::strip_suffix_guarded($word, 'ове', self::$bg_min_base, true)) !== null) return $cand;
        if (($cand = self::strip_suffix_guarded($word, 'еве', self::$bg_min_base, true)) !== null) return $cand;

        // 2) generic -и (with exceptions)
        if (!in_array($lower, self::$bg_no_strip_i, true)) {
            if (($cand = self::strip_suffix_guarded($word, 'и', self::$bg_min_base, false)) !== null) {
                $word = $cand; // continue processing
            }
        }

        // 3) -ища
        if (self::bg_ends_with($word, 'ища')) {
            $cand = self::bg_substr($word, 0, bg_len($word) - 3);
            if (self::bg_len($cand) >= self::$bg_min_base) return $cand;
        }

        // 4) -та after plural bases (if it slipped past article stage)
        if (self::bg_ends_with($word, 'та')) {
            $base = self::bg_substr($word, 0, self::bg_len($word) - 2);
            if (preg_match('/(и|а|я|ове|еве)$/u', $base) && self::bg_len($base) >= self::$bg_min_base) return $base;
        }

        // (removed: зи→г, ци→к, си→х alternations to avoid 'краси'→'крах' etc.)
        return $word;
    }

    public static function remove_verb_endings($w)
    {
        // Longest-first; guarded by self::$bg_min_base in the loop
        $endings = [
            'ете', 'йте',     // 2pl (present/imperative): четете/пишете/говорете → чет/пиш/говор
            'яхме','яхте','ем', 'еш', 'ят', 'ат',
            'ах', 'аха',      // aorist
            'ал', 'ала', 'али','аме', 'ам',
            'ане','ене',      // verbal nouns: четене→чет, говорене→говор
            'е','а','о','и',  // light trims (still guarded)
        ];
        foreach ($endings as $sfx) {
            if (self::bg_ends_with($w, $sfx)) {
                $cand = self::bg_substr($w, 0, self::bg_len($w) - self::bg_len($sfx));
                if (self::bg_len($cand) >= 2) {  // was self::$bg_min_base
                    $w = $cand;
                    break;
                }
            }
        }
        return $w;
    }

    /* ========== UTF-8 helpers ========== */
    public static function bg_len($s){ return mb_strlen($s, 'UTF-8'); }
    public static function bg_substr($s, $start, ?int $len=null){
        if(!$len){ $len = self::bg_len($s) - $start; }
        return mb_substr($s, $start, $len, 'UTF-8');
    }
    public static function bg_ends_with($w, $sfx){
        $n = self::bg_len($sfx);
        return $n <= self::bg_len($w) && self::bg_substr($w, -$n) === $sfx;
    }
    public static function bg_last_char($w){ return self::bg_substr($w, -1, 1); }
    public static function bg_is_vowel($ch){
        static $v = ['а','е','и','о','у','ъ','ю','я'];
        return in_array($ch, $v, true);
    }
    /** Strip a suffix if remaining base length >= bg_min_base. Optionally require consonant ending. */
    public static function strip_suffix_guarded($w, $sfx, $minBase = 3, $needConsonantEnd = false){
        if (!self::bg_ends_with($w, $sfx)) return null;
        $base = self::bg_substr($w, 0, self::bg_len($w) - self::bg_len($sfx));
        if (self::bg_len($base) < $minBase) return null;
        if ($needConsonantEnd && self::bg_is_vowel(self::bg_last_char($base))) return null;
        return $base;
    }
    /** Apply a regex replacement if the result keeps base >= self::$bg_min_base. */
    public static function replace_guarded($w, $pattern, $repl){
        $cand = preg_replace($pattern, $repl, $w);
        if ($cand === null || $cand === $w) return null;
        if (self::bg_len($cand) < self::$bg_min_base) return null;
        return $cand;
    }
}

?>