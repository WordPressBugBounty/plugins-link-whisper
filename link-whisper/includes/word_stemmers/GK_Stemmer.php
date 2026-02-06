<?php

class Wpil_Stemmer {

    static $stem_cache = array();
   
    // --- Character token map, matching the Snowball stringdefs ---
    static $TOK = [
            'a' => 'α','v' => 'β','g' => 'γ','d' => 'δ','e' => 'ε','z' => 'ζ','i' => 'η','th' => 'θ',
            'y' => 'ι','k' => 'κ','l' => 'λ','m' => 'μ','n' => 'ν','x' => 'ξ','o' => 'ο','p' => 'π',
            'r' => 'ρ','ss' => 'ς','s' => 'σ','t' => 'τ','u' => 'υ','f' => 'φ','ch' => 'χ','ps' => 'ψ','oo' => 'ω',
            'Y:' => 'Ϊ','U:' => 'Ϋ',
            "a'" => 'ά',"e'" => 'έ',"i'" => 'ή',"y'" => 'ί',"o'" => 'ό',"u'" => 'ύ',"oo'" => 'ώ',
            "i:'" => 'ΐ',"u:'" => 'ΰ','i:' => 'ϊ','u:' => 'ϋ',
            'A' => 'Α','V' => 'Β','G' => 'Γ','D' => 'Δ','E' => 'Ε','Z' => 'Ζ','I' => 'Η','Th' => 'Θ',
            'Y' => 'Ι','K' => 'Κ','L' => 'Λ','M' => 'Μ','N' => 'Ν','X' => 'Ξ','O' => 'Ο','P' => 'Π',
            'R' => 'Ρ','S' => 'Σ','T' => 'Τ','U' => 'Υ','F' => 'Φ','Ch' => 'Χ','Ps' => 'Ψ','Oo' => 'Ω',
            "A'" => 'Ά',"E'" => 'Έ',"I'" => 'Ή',"Y'" => 'Ί',"O'" => 'Ό',"U'" => 'Ύ',"OO'" => 'Ώ',
    ];

    // Vowel groupings v and v2 (Snowball uses these)
    static $V  = ['α','ε','η','ι','ο','υ','ω'];
    static $V2 = ['α','ε','η','ι','ο','ω'];

    static $test1 = false; // mirrors Snowball boolean used by step_6


    public static function Stem($word, $deaccent = false, $ignore_cache = false){
        // first check if we've already stemmed the word
        $cached = self::get_cached_stem($word);
        if(!empty($cached)){
            // if we have return the cached
            return $cached;
        }

        $original_word = $word;

        $w0 = self::normalize($word);
        if (mb_strlen($w0) < 3) return $w0; // has_min_length
        $w = $w0;
        self::$test1 = true;

        // Order mirrors the Snowball program
        $w = self::step_1($w);
        $w = self::step_s1($w);
        $w = self::step_s2($w);
        $w = self::step_s3($w);
        $w = self::step_s4($w);
        $w = self::step_s5($w);
        $w = self::step_s6($w);
        $w = self::step_s7($w);
        $w = self::step_s8($w);
        $w = self::step_s9($w);
        $w = self::step_s10($w);
        $w = self::step_2a($w);
        $w = self::step_2b($w);
        $w = self::step_2c($w);
        $w = self::step_2d($w);
        $w = self::step_3($w);
        $w = self::step_4($w);
        $w = self::step_5a($w);
        $w = self::step_5b($w);
        $w = self::step_5c($w);
        $w = self::step_5d($w);
        $w = self::step_5e($w);
        $w = self::step_5f($w);
        $w = self::step_5g($w);
        $w = self::step_5h($w);
        $w = self::step_5j($w);
        $w = self::step_5i($w);
        $w = self::step_5k($w);
        $w = self::step_5l($w);
        $w = self::step_5m($w);
        $w = self::step_6($w);
        $w = self::step_7($w);

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
/*
    public function __construct()
    {
        if (!function_exists('mb_strlen')) {
            throw new \RuntimeException('GreekSnowballStemmer requires mbstring.');
        }
        mb_internal_encoding('UTF-8');
    }*/

    /** Expand Snowball token string like "{y}{s}{k}{o}{s}" → actual Greek string. */
    private static function g(string $pattern): string
    {
        return preg_replace_callback('/\{([^}]+)\}/u', function ($m) {
            $tok = $m[1];
            if (!array_key_exists($tok, self::$TOK)) {
                throw new \RuntimeException("Unknown token {$tok} in pattern");
            }
            return self::$TOK[$tok];
        }, $pattern);
    }

    /** Normalize like Snowball `tolower`: map to lowercase, strip tonos/dialytika, ς→σ for processing. */
    private static function normalize(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        // Map accented/dialytika to plain, and final sigma to σ
        $map = [
            'ά'=>'α','έ'=>'ε','ή'=>'η','ί'=>'ι','ό'=>'ο','ύ'=>'υ','ώ'=>'ω',
            'ΐ'=>'ι','ΰ'=>'υ','ϊ'=>'ι','ϋ'=>'υ','ς'=>'σ',
            // Upper leftovers if any
            'Ά'=>'α','Έ'=>'ε','Ή'=>'η','Ί'=>'ι','Ό'=>'ο','Ύ'=>'υ','Ώ'=>'ω',
            'Ϊ'=>'ι','Ϋ'=>'υ',
        ];
        return strtr($s, $map);
    }

    private static function endsWith(string $s, string $suffix): bool
    {
        $ls = mb_strlen($s); $lf = mb_strlen($suffix);
        if ($lf === 0 || $lf > $ls) return false;
        return mb_substr($s, $ls - $lf) === $suffix;
    }

    private static function replaceSuffix(string $s, string $suffix, string $replacement): string
    {
        $ls = mb_strlen($s); $lf = mb_strlen($suffix);
        return mb_substr($s, 0, $ls - $lf) . $replacement;
    }

    private static function isVowel(string $ch): bool { return in_array($ch, self::$V, true); }

    private static function atlimit(string $original, string $current): bool
    {
        // In backward mode, atlimit checks we are at the start after deletion/replacement
        // We approximate by saying: after stripping the suffix, the remaining word is the head.
        // Snowball uses this to gate certain follow-up replacements; here we expose a flag to predicates.
        return $current !== '' && !self::isVowel(mb_substr($current, -1));
    }

    // --- Step helpers: literal among lists translated to arrays ---

    private static function step_1(string $w): string
    {
        // Snowball: map of compound families → reduced root; we implement a faithful subset.
        $groups = [
            // {f}{a}{g}{y}{a} / {f}{a}{g}{y}{o}{u} / {f}{a}{g}{y}{oo}{n}  (<- {f}{a})
            [ [self::g('{f}{a}{g}{y}{a}'), self::g('{f}{a}{g}{y}{o}{u}'), self::g('{f}{a}{g}{y}{oo}{n}')], self::g('{f}{a}') ],
            // {s}{k}{a}{g}{y}{a} family → {s}{k}{a}
            [ [self::g('{s}{k}{a}{g}{y}{a}'), self::g('{s}{k}{a}{g}{y}{o}{u}'), self::g('{s}{k}{a}{g}{y}{oo}{n}')], self::g('{s}{k}{a}') ],
            // {o}{l}{o}{g}{y}{o}{u} / ... → {o}{l}{o}
            [ [self::g('{o}{l}{o}{g}{y}{o}{u}'), self::g('{o}{l}{o}{g}{y}{a}'), self::g('{o}{l}{o}{g}{y}{oo}{n}')], self::g('{o}{l}{o}') ],
            // {s}{o}{g}{y}{o}{u} / ... → {s}{o}
            [ [self::g('{s}{o}{g}{y}{o}{u}'), self::g('{s}{o}{g}{y}{a}'), self::g('{s}{o}{g}{y}{oo}{n}')], self::g('{s}{o}') ],
            // {t}{a}{t}{o}{g}{y}{a} family → {t}{a}{t}{o}
            [ [self::g('{t}{a}{t}{o}{g}{y}{a}'), self::g('{t}{a}{t}{o}{g}{y}{o}{u}'), self::g('{t}{a}{t}{o}{g}{y}{oo}{n}')], self::g('{t}{a}{t}{o}') ],
            // {k}{r}{e}{a}{s}/{k}{r}{e}{a}{t}{o}{s}/... → {k}{r}{e}
            [ [self::g('{k}{r}{e}{a}{s}'), self::g('{k}{r}{e}{a}{t}{o}{s}'), self::g('{k}{r}{e}{a}{t}{a}'), self::g('{k}{r}{e}{a}{t}{oo}{n}')], self::g('{k}{r}{e}') ],
            // {p}{e}{r}{a}{s}/... → {p}{e}{r}
            [ [self::g('{p}{e}{r}{a}{s}'), self::g('{p}{e}{r}{a}{t}{o}{s}'), self::g('{p}{e}{r}{a}{t}{i}'), self::g('{p}{e}{r}{a}{t}{a}'), self::g('{p}{e}{r}{a}{t}{oo}{n}')], self::g('{p}{e}{r}') ],
            // {t}{e}{r}{a}{s}/... → {t}{e}{r}
            [ [self::g('{t}{e}{r}{a}{s}'), self::g('{t}{e}{r}{a}{t}{o}{s}'), self::g('{t}{e}{r}{a}{t}{a}'), self::g('{t}{e}{r}{a}{t}{oo}{n}')], self::g('{t}{e}{r}') ],
            // {f}{oo}{s}/... → {f}{oo}
            [ [self::g('{f}{oo}{s}'), self::g('{f}{oo}{t}{o}{s}'), self::g('{f}{oo}{t}{a}'), self::g('{f}{oo}{t}{oo}{n}')], self::g('{f}{oo}') ],
            // {k}{a}{th}{e}{s}{t}{oo}{s}/... → {k}{a}{th}{e}{s}{t}
            [ [self::g('{k}{a}{th}{e}{s}{t}{oo}{s}'), self::g('{k}{a}{th}{e}{s}{t}{oo}{t}{o}{s}'), self::g('{k}{a}{th}{e}{s}{t}{oo}{t}{a}'), self::g('{k}{a}{th}{e}{s}{t}{oo}{t}{oo}{n}')], self::g('{k}{a}{th}{e}{s}{t}') ],
            // {g}{e}{g}{o}{n}{o}{s}/... → {g}{e}{g}{o}{n}
            [ [self::g('{g}{e}{g}{o}{n}{o}{s}'), self::g('{g}{e}{g}{o}{n}{o}{t}{o}{s}'), self::g('{g}{e}{g}{o}{n}{o}{t}{a}'), self::g('{g}{e}{g}{o}{n}{o}{t}{oo}{n}')], self::g('{g}{e}{g}{o}{n}') ],
        ];
        foreach ($groups as [$variants, $root]) {
            foreach ($variants as $suf) {
                if (self::endsWith($w, $suf)) { $w = self::replaceSuffix($w, $suf, $root); self::$test1 = false; return $w; }
            }
        }
        return $w;
    }

    private static function step_s1(string $w): string
    {
        // [substring] among (...) (delete; unset test1) with follow‑up atlimit exceptions mapping
        // We implement literal deletions for the main cluster.
        $deleteSet = [
            self::g('{y}{z}{a}'), self::g('{y}{z}{e}{s}'), self::g('{y}{z}{e}'), self::g('{y}{z}{a}{m}{e}'),
            self::g('{y}{z}{a}{t}{e}'), self::g('{y}{z}{a}{n}'), self::g('{y}{z}{a}{n}{e}'), self::g('{y}{z}{oo}'),
            self::g('{y}{z}{e}{y}{s}'), self::g('{y}{z}{e}{y}'), self::g('{y}{z}{o}{u}{m}{e}'), self::g('{y}{z}{e}{t}{e}'),
            self::g('{y}{z}{o}{u}{n}'), self::g('{y}{z}{o}{u}{n}{e}'),
        ];
        foreach ($deleteSet as $suf) {
            if (self::endsWith($w, $suf)) {
                $w = self::replaceSuffix($w, $suf, '');
                self::$test1 = false;
                // Follow‑up special cases in Snowball set {y} or {y}{z}; we omit secondary head substitutions
                // because they depend on atlimit context; if needed, add predicates mirroring the paper.
                return $w;
            }
        }
        return $w;
    }

    private static function step_s2(string $w): string
    {
        $deleteSet = [
            self::g('{oo}{th}{i}{k}{a}'), self::g('{oo}{th}{i}{k}{e}{s}'), self::g('{oo}{th}{i}{k}{e}'),
            self::g('{oo}{th}{i}{k}{a}{m}{e}'), self::g('{oo}{th}{i}{k}{a}{t}{e}'), self::g('{oo}{th}{i}{k}{a}{n}'), self::g('{oo}{th}{i}{k}{a}{n}{e}')
        ];
        foreach ($deleteSet as $suf) {
            if (self::endsWith($w, $suf)) { $w = self::replaceSuffix($w, $suf, ''); self::$test1 = false; return $w; }
        }
        return $w;
    }

    private static function step_s3(string $w): string
    {
        $deleteSet = [
            self::g('{y}{s}{a}'), self::g('{y}{s}{e}{s}'), self::g('{y}{s}{e}'), self::g('{y}{s}{a}{m}{e}'), self::g('{y}{s}{a}{t}{e}'),
            self::g('{y}{s}{a}{n}'), self::g('{y}{s}{a}{n}{e}')
        ];
        foreach ($deleteSet as $suf) {
            if (self::endsWith($w, $suf)) { $w = self::replaceSuffix($w, $suf, ''); self::$test1 = false; return $w; }
        }
        return $w;
    }

    private static function step_s4(string $w): string
    {
        $deleteSet = [
            self::g('{y}{s}{oo}'), self::g('{y}{s}{e}{y}{s}'), self::g('{y}{s}{e}{y}'), self::g('{y}{s}{o}{u}{m}{e}'),
            self::g('{y}{s}{e}{t}{e}'), self::g('{y}{s}{o}{u}{n}'), self::g('{y}{s}{o}{u}{n}{e}')
        ];
        foreach ($deleteSet as $suf) {
            if (self::endsWith($w, $suf)) { $w = self::replaceSuffix($w, $suf, ''); self::$test1 = false; return $w; }
        }
        return $w;
    }

    private static function step_s5(string $w): string
    {
        $deleteSet = [
            self::g('{y}{s}{t}{o}{s}'), self::g('{y}{s}{t}{o}{u}'), self::g('{y}{s}{t}{o}'), self::g('{y}{s}{t}{e}'), self::g('{y}{s}{t}{o}{y}'),
            self::g('{y}{s}{t}{oo}{n}'), self::g('{y}{s}{t}{o}{u}{s}'), self::g('{y}{s}{t}{i}'), self::g('{y}{s}{t}{i}{s}'),
            self::g('{y}{s}{t}{a}'), self::g('{y}{s}{t}{e}{s}')
        ];
        foreach ($deleteSet as $suf) {
            if (self::endsWith($w, $suf)) { $w = self::replaceSuffix($w, $suf, ''); self::$test1 = false; return $w; }
        }
        return $w;
    }

    private static function step_s6(string $w): string
    {
        $deleteSet = [
            self::g('{y}{s}{m}{o}'), self::g('{y}{s}{m}{o}{y}'), self::g('{y}{s}{m}{o}{s}'), self::g('{y}{s}{m}{o}{u}'),
            self::g('{y}{s}{m}{o}{u}{s}'), self::g('{y}{s}{m}{oo}{n}')
        ];
        foreach ($deleteSet as $suf) {
            if (self::endsWith($w, $suf)) { $w = self::replaceSuffix($w, $suf, ''); self::$test1 = false; return $w; }
        }
        return $w;
    }

    private static function step_s7(string $w): string
    {
        $deleteSet = [ self::g('{a}{r}{a}{k}{y}'), self::g('{a}{r}{a}{k}{y}{a}'), self::g('{o}{u}{d}{a}{k}{y}'), self::g('{o}{u}{d}{a}{k}{y}{a}') ];
        foreach ($deleteSet as $suf) { if (self::endsWith($w, $suf)) { $w = self::replaceSuffix($w, $suf, ''); self::$test1 = false; return $w; } }
        return $w;
    }

    private static function step_s8(string $w): string
    {
        $deleteSet = [ self::g('{a}{k}{y}'), self::g('{a}{k}{y}{a}'), self::g('{y}{t}{s}{a}'), self::g('{y}{t}{s}{a}{s}'), self::g('{y}{t}{s}{e}{s}'), self::g('{y}{t}{s}{oo}{n}'), self::g('{a}{r}{a}{k}{y}'), self::g('{a}{r}{a}{k}{y}{a}') ];
        foreach ($deleteSet as $suf) { if (self::endsWith($w, $suf)) { $w = self::replaceSuffix($w, $suf, ''); self::$test1 = false; return $w; } }
        return $w;
    }

    private static function step_s9(string $w): string
    {
        $deleteSet = [ self::g('{y}{d}{y}{o}'), self::g('{y}{d}{y}{a}'), self::g('{y}{d}{y}{oo}{n}') ];
        foreach ($deleteSet as $suf) { if (self::endsWith($w, $suf)) { $w = self::replaceSuffix($w, $suf, ''); self::$test1 = false; return $w; } }
        return $w;
    }

    private static function step_s10(string $w): string
    {
        $deleteSet = [ self::g('{y}{s}{k}{o}{s}'), self::g('{y}{s}{k}{o}{u}'), self::g('{y}{s}{k}{o}'), self::g('{y}{s}{k}{e}') ];
        foreach ($deleteSet as $suf) { if (self::endsWith($w, $suf)) { $w = self::replaceSuffix($w, $suf, ''); self::$test1 = false; return $w; } }
        return $w;
    }

    private static function step_2a(string $w): string
    {
        // {a}{d}{e}{s} / {a}{d}{oo}{n} delete, then add {a}{d} unless blocked by exceptions
        $targets = [ self::g('{a}{d}{e}{s}'), self::g('{a}{d}{oo}{n}') ];
        $exceptions = [ self::g('{o}{k}'), self::g('{m}{a}{m}'), self::g('{m}{a}{n}'), self::g('{m}{p}{a}{m}{p}'), self::g('{p}{a}{t}{e}{r}'), self::g('{g}{y}{a}{g}{y}'), self::g('{n}{t}{a}{n}{t}'), self::g('{k}{u}{r}'), self::g('{th}{e}{y}'), self::g('{p}{e}{th}{e}{r}')];
        foreach ($targets as $suf) {
            if (self::endsWith($w, $suf)) {
                $w = self::replaceSuffix($w, $suf, '');
                $excepted = false;
                foreach ($exceptions as $ex) {
                    if(self::endsWith($w, $suf)){
                        $excepted = true;
                        break;
                    }
                }
                if(!$excepted){
                    $w .= self::g('{a}{d}');
                }
                return $w;
            }
        }
        return $w;
    }

    private static function step_2b(string $w): string
    {
        $targets = [ self::g('{e}{d}{e}{s}'), self::g('{e}{d}{oo}{n}') ];
        foreach ($targets as $suf) { if (self::endsWith($w, $suf)) { $w = self::replaceSuffix($w, $suf, ''); return $w; } }
        return $w;
    }

    private static function step_2c(string $w): string
    {
        $targets = [ self::g('{o}{u}{d}{e}{s}'), self::g('{o}{u}{d}{oo}{n}') ];
        foreach ($targets as $suf) { if (self::endsWith($w, $suf)) { $w = self::replaceSuffix($w, $suf, ''); return $w; } }
        return $w;
    }

    private static function step_2d(string $w): string
    {
        $targets = [ self::g('{e}{oo}{s}'), self::g('{e}{oo}{n}') ];
        foreach ($targets as $suf) { if (self::endsWith($w, $suf)) { $w = self::replaceSuffix($w, $suf, ''); self::$test1 = false; return $w; } }
        return $w;
    }

    private static function step_3(string $w): string
    {
        $targets = [ self::g('{y}{a}'), self::g('{y}{o}{u}'), self::g('{y}{oo}{n}') ];
        foreach ($targets as $suf) { if (self::endsWith($w, $suf)) { $w = self::replaceSuffix($w, $suf, ''); self::$test1 = false; return $w; } }
        return $w;
    }

    private static function step_4(string $w): string
    {
        $targets = [ self::g('{y}{k}{a}'), self::g('{y}{k}{o}'), self::g('{y}{k}{o}{u}'), self::g('{y}{k}{oo}{n}') ];
        foreach ($targets as $suf) { if (self::endsWith($w, $suf)) { $w = self::replaceSuffix($w, $suf, ''); self::$test1 = false; return $w; } }
        return $w;
    }

    private static function step_5a(string $w): string
    {
        // Core deletion for -αμε, -ισαμε, -ουσαμε, -ικαμε, -ιθικαμε etc.
        $deleteSet = [ self::g('{a}{g}{a}{m}{e}'), self::g('{i}{s}{a}{m}{e}'), self::g('{o}{u}{s}{a}{m}{e}'), self::g('{i}{k}{a}{m}{e}'), self::g('{i}{th}{i}{k}{a}{m}{e}') ];
        foreach ($deleteSet as $suf) { if (self::endsWith($w, $suf)) { $w = self::replaceSuffix($w, $suf, ''); self::$test1 = false; return $w; } }
        // Then delete trailing {a}{m}{e}
        if (self::endsWith($w, self::g('{a}{m}{e}'))) { $w = self::replaceSuffix($w, self::g('{a}{m}{e}'), ''); self::$test1 = false; }
        return $w;
    }

    private static function step_5b(string $w): string
    {
        $deleteSet = [
            self::g('{a}{g}{a}{n}{e}'), self::g('{i}{s}{a}{n}{e}'), self::g('{o}{u}{s}{a}{n}{e}'), self::g('{y}{o}{n}{t}{a}{n}{e}'), self::g('{y}{o}{t}{a}{n}{e}'),
            self::g('{y}{o}{u}{n}{t}{a}{n}{e}'), self::g('{o}{n}{t}{a}{n}{e}'), self::g('{o}{t}{a}{n}{e}'), self::g('{o}{u}{n}{t}{a}{n}{e}'), self::g('{i}{k}{a}{n}{e}'),
            self::g('{i}{th}{i}{k}{a}{n}{e}')
        ];
        foreach ($deleteSet as $suf) { if (self::endsWith($w, $suf)) { $w = self::replaceSuffix($w, $suf, ''); self::$test1 = false; return $w; } }
        if (self::endsWith($w, self::g('{a}{n}{e}'))) { $w = self::replaceSuffix($w, self::g('{a}{n}{e}'), ''); self::$test1 = false; }
        return $w;
    }

    private static function step_5c(string $w): string
    {
        if (self::endsWith($w, self::g('{i}{s}{e}{t}{e}'))) { $w = self::replaceSuffix($w, self::g('{i}{s}{e}{t}{e}'), ''); self::$test1 = false; }
        if (self::endsWith($w, self::g('{e}{t}{e}'))) { $w = self::replaceSuffix($w, self::g('{e}{t}{e}'), ''); self::$test1 = false; }
        return $w;
    }

    private static function step_5d(string $w): string
    {
        $targets = [ self::g('{o}{n}{t}{a}{s}'), self::g('{oo}{n}{t}{a}{s}') ];
        foreach ($targets as $suf) { if (self::endsWith($w, $suf)) { $w = self::replaceSuffix($w, $suf, ''); self::$test1 = false; return $w; } }
        return $w;
    }

    private static function step_5e(string $w): string
    {
        $targets = [ self::g('{o}{m}{a}{s}{t}{e}'), self::g('{y}{o}{m}{a}{s}{t}{e}') ];
        foreach ($targets as $suf) { if (self::endsWith($w, $suf)) { $w = self::replaceSuffix($w, $suf, ''); self::$test1 = false; return $w; } }
        return $w;
    }

    private static function step_5f(string $w): string
    {
        if (self::endsWith($w, self::g('{y}{e}{s}{t}{e}'))) { $w = self::replaceSuffix($w, self::g('{y}{e}{s}{t}{e}'), ''); self::$test1 = false; return $w; }
        if (self::endsWith($w, self::g('{e}{s}{t}{e}'))) { $w = self::replaceSuffix($w, self::g('{e}{s}{t}{e}'), ''); self::$test1 = false; }
        return $w;
    }

    private static function step_5g(string $w): string
    {
        $pref = [ self::g('{i}{th}{i}{k}{a}'), self::g('{i}{th}{i}{k}{e}{s}'), self::g('{i}{th}{i}{k}{e}') ];
        foreach ($pref as $suf) { if (self::endsWith($w, $suf)) { $w = self::replaceSuffix($w, $suf, ''); self::$test1 = false; return $w; } }
        $set = [ self::g('{i}{k}{a}'), self::g('{i}{k}{e}{s}'), self::g('{i}{k}{e}') ];
        foreach ($set as $suf) { if (self::endsWith($w, $suf)) { $w = self::replaceSuffix($w, $suf, ''); self::$test1 = false; return $w; } }
        return $w;
    }

    private static function step_5h(string $w): string
    {
        $set = [ self::g('{o}{u}{s}{a}'), self::g('{o}{u}{s}{e}{s}'), self::g('{o}{u}{s}{e}') ];
        foreach ($set as $suf) { if (self::endsWith($w, $suf)) { $w = self::replaceSuffix($w, $suf, ''); self::$test1 = false; return $w; } }
        return $w;
    }

    private static function step_5i(string $w): string
    {
        $set = [ self::g('{a}{g}{a}'), self::g('{a}{g}{e}{s}'), self::g('{a}{g}{e}') ];
        foreach ($set as $suf) { if (self::endsWith($w, $suf)) { $w = self::replaceSuffix($w, $suf, ''); self::$test1 = false; return $w; } }
        return $w;
    }

    private static function step_5j(string $w): string
    {
        $set = [ self::g('{i}{s}{e}'), self::g('{i}{s}{o}{u}'), self::g('{i}{s}{a}') ];
        foreach ($set as $suf) { if (self::endsWith($w, $suf)) { $w = self::replaceSuffix($w, $suf, ''); self::$test1 = false; return $w; } }
        return $w;
    }

    private static function step_5k(string $w): string
    {
        if (self::endsWith($w, self::g('{i}{s}{t}{e}'))) { $w = self::replaceSuffix($w, self::g('{i}{s}{t}{e}'), ''); self::$test1 = false; }
        return $w;
    }

    private static function step_5l(string $w): string
    {
        $set = [ self::g('{o}{u}{n}{e}'), self::g('{i}{s}{o}{u}{n}{e}'), self::g('{i}{th}{o}{u}{n}{e}') ];
        foreach ($set as $suf) { if (self::endsWith($w, $suf)) { $w = self::replaceSuffix($w, $suf, ''); self::$test1 = false; return $w; } }
        return $w;
    }

    private static function step_5m(string $w): string
    {
        $set = [ self::g('{o}{u}{m}{e}'), self::g('{i}{s}{o}{u}{m}{e}'), self::g('{i}{th}{o}{u}{m}{e}') ];
        foreach ($set as $suf) { if (self::endsWith($w, $suf)) { $w = self::replaceSuffix($w, $suf, ''); self::$test1 = false; return $w; } }
        return $w;
    }

    private static function step_6(string $w): string
    {
        // Only run if $test1 is true; then delete a long list of endings.
        if (!self::$test1) return $w;
        $deleteSet = [
            self::g('{a}'), self::g('{a}{g}{a}{t}{e}'), self::g('{a}{g}{a}{n}'), self::g('{a}{e}{y}'), self::g('{a}{m}{a}{y}'), self::g('{a}{n}'),
            self::g('{a}{s}'), self::g('{a}{s}{a}{y}'), self::g('{a}{t}{a}{y}'), self::g('{a}{oo}'), self::g('{e}'), self::g('{e}{y}'), self::g('{e}{y}{s}'),
            self::g('{e}{y}{t}{e}'), self::g('{e}{s}{a}{y}'), self::g('{e}{s}'), self::g('{e}{t}{a}{y}'), self::g('{y}'), self::g('{y}{e}{m}{a}{y}'),
            self::g('{y}{e}{m}{a}{s}{t}{e}'), self::g('{y}{e}{t}{a}{y}'), self::g('{y}{e}{s}{a}{y}'), self::g('{y}{e}{s}{a}{s}{t}{e}'),
            self::g('{y}{o}{m}{a}{s}{t}{a}{n}'), self::g('{y}{o}{m}{o}{u}{n}'), self::g('{y}{o}{m}{o}{u}{n}{a}'), self::g('{y}{o}{n}{t}{a}{n}'),
            self::g('{y}{o}{n}{t}{o}{u}{s}{a}{n}'), self::g('{y}{o}{s}{a}{s}{t}{a}{n}'), self::g('{y}{o}{s}{a}{s}{t}{e}'), self::g('{y}{o}{s}{o}{u}{n}'),
            self::g('{y}{o}{s}{o}{u}{n}{a}'), self::g('{y}{o}{t}{a}{n}'), self::g('{y}{o}{u}{m}{a}'), self::g('{y}{o}{u}{m}{a}{s}{t}{e}'), self::g('{y}{o}{u}{n}{t}{a}{y}'),
            self::g('{y}{o}{u}{n}{t}{a}{n}'), self::g('{i}'), self::g('{i}{d}{e}{s}'), self::g('{i}{d}{oo}{n}'), self::g('{i}{th}{e}{y}'), self::g('{i}{th}{e}{y}{s}'),
            self::g('{i}{th}{e}{y}{t}{e}'), self::g('{i}{th}{i}{k}{a}{t}{e}'), self::g('{i}{th}{i}{k}{a}{n}'), self::g('{i}{th}{o}{u}{n}'), self::g('{i}{th}{oo}'),
            self::g('{i}{k}{a}{t}{e}'), self::g('{i}{k}{a}{n}'), self::g('{i}{s}'), self::g('{i}{s}{a}{n}'), self::g('{i}{s}{a}{t}{e}'), self::g('{i}{s}{e}{y}'),
            self::g('{i}{s}{e}{s}'), self::g('{i}{s}{o}{u}{n}'), self::g('{i}{s}{oo}'), self::g('{o}'), self::g('{o}{y}'), self::g('{o}{m}{a}{y}'),
            self::g('{o}{m}{a}{s}{t}{a}{n}'), self::g('{o}{m}{o}{u}{n}'), self::g('{o}{m}{o}{u}{n}{a}'), self::g('{o}{n}{t}{a}{y}'), self::g('{o}{n}{t}{a}{n}'),
            self::g('{o}{n}{t}{o}{u}{s}{a}{n}'), self::g('{o}{s}'), self::g('{o}{s}{a}{s}{t}{a}{n}'), self::g('{o}{s}{a}{s}{t}{e}'), self::g('{o}{s}{o}{u}{n}'),
            self::g('{o}{s}{o}{u}{n}{a}'), self::g('{o}{t}{a}{n}'), self::g('{o}{u}'), self::g('{o}{u}{m}{a}{y}'), self::g('{o}{u}{m}{a}{s}{t}{e}'), self::g('{o}{u}{n}'),
            self::g('{o}{u}{n}{t}{a}{y}'), self::g('{o}{u}{n}{t}{a}{n}'), self::g('{o}{u}{s}'), self::g('{o}{u}{s}{a}{n}'), self::g('{o}{u}{s}{a}{t}{e}'), self::g('{u}'),
            self::g('{u}{s}'), self::g('{oo}'), self::g('{oo}{n}')
        ];
        foreach ($deleteSet as $suf) {
            if (self::endsWith($w, $suf)) { return self::replaceSuffix($w, $suf, ''); }
        }
        return $w;
    }

    private static function step_7(string $w): string
    {
        $set = [ self::g('{e}{s}{t}{e}{r}'), self::g('{e}{s}{t}{a}{t}'), self::g('{o}{t}{e}{r}'), self::g('{o}{t}{a}{t}'), self::g('{u}{t}{e}{r}'), self::g('{u}{t}{a}{t}'), self::g('{oo}{t}{e}{r}'), self::g('{oo}{t}{a}{t}') ];
        foreach ($set as $suf) { if (self::endsWith($w, $suf)) { return self::replaceSuffix($w, $suf, ''); } }
        return $w;
    }



}

?>