<?php
class Wpil_Stemmer
{
    static $stem_cache = array();
    static $vowels = ['a', 'e', 'i', 'o', 'u'];
    static $measure;
    static $prefix;

    public static function Stem($word, $deaccent = false, $ignore_cache = false){
        // first check if we've already stemmed the word
        $cached = self::get_cached_stem($word);
        if(!empty($cached) && !$ignore_cache){
            // if we have return the cached
            return $cached;
        }

        // if it's not cached, stemm the word
        $original_word = $word;

        // Calculate the initial measure (number of vowels)
        self::$measure = self::calculateMeasure($word);

        if (self::$measure <= 2) {
            return $word;
        }

        self::$prefix = 0;

        // Remove particle and possessive pronoun in backward mode
        $word = self::removeParticle($word);

        if (self::$measure <= 2) {
            return $word;
        }

        $word = self::removePossessivePronoun($word);

        if (self::$measure <= 2) {
            return $word;
        }

        // Attempt to remove first-order prefix
        if (self::removeFirstOrderPrefix($word, $newWord)) {
            $word = $newWord;

            if (self::$measure > 2) {
                // Remove suffix in backward mode
                $word = self::removeSuffix($word);

                if (self::$measure > 2) {
                    // Remove second-order prefix
                    $word = self::removeSecondOrderPrefix($word);
                }
            }
        } else {
            // Remove second-order prefix
            $word = self::removeSecondOrderPrefix($word);

            if (self::$measure > 2) {
                // Remove suffix in backward mode
                $word = self::removeSuffix($word);
            }
        }

        // and update the cache with the stemmed word
        self::update_cached_stem($original_word, $word);

        return $word;
    }

    private static function removePossessivePronoun($word)
    {
        $possessives = ['ku', 'mu', 'nya'];

        foreach ($possessives as $possessive) {
            if (self::endsWith($word, $possessive)) {
                $word = substr($word, 0, -strlen($possessive));
                self::$measure -= 1;
                break;
            }
        }

        return $word;
    }

    private static function removeFirstOrderPrefix($word, &$newWord)
    {
        $prefixes = [
            'di', 'meng', 'men', 'me', 'ter',
            'ke', 'peng', 'pen',
            'meny', 'peny',
            'mem', 'pem'
        ];

        foreach ($prefixes as $prefix) {
            if (self::startsWith($word, $prefix)) {
                $stem = substr($word, strlen($prefix));
                self::$measure -= 1;

                if (in_array($prefix, ['di', 'meng', 'men', 'me', 'ter'])) {
                    self::$prefix = 1;
                    $newWord = $stem;
                } elseif (in_array($prefix, ['ke', 'peng', 'pen'])) {
                    self::$prefix = 3;
                    $newWord = $stem;
                } elseif ($prefix == 'meny' && self::isVowel($stem[0])) {
                    self::$prefix = 1;
                    $newWord = 's' . substr($stem, 1);
                } elseif ($prefix == 'peny' && self::isVowel($stem[0])) {
                    self::$prefix = 3;
                    $newWord = 's' . substr($stem, 1);
                } elseif ($prefix == 'mem') {
                    self::$prefix = 1;
                    if (self::isVowel($stem[0])) {
                        $newWord = 'p' . $stem;
                    } else {
                        $newWord = $stem;
                    }
                } elseif ($prefix == 'pem') {
                    self::$prefix = 3;
                    if (self::isVowel($stem[0])) {
                        $newWord = 'p' . $stem;
                    } else {
                        $newWord = $stem;
                    }
                } else {
                    $newWord = $stem;
                }
                return true;
            }
        }

        return false;
    }

    private static function removeSecondOrderPrefix($word)
    {
        $prefixes = [
            'per', 'pe', 'pelajar', 'ber', 'belajar', 'be'
        ];

        foreach ($prefixes as $prefix) {
            if (self::startsWith($word, $prefix)) {
                $stem = substr($word, strlen($prefix));
                self::$measure -= 1;

                if (in_array($prefix, ['per', 'pe'])) {
                    self::$prefix = 2;
                    $word = $stem;
                } elseif ($prefix == 'pelajar') {
                    self::$prefix = 0;
                    $word = 'ajar';
                } elseif ($prefix == 'ber') {
                    self::$prefix = 4;
                    $word = $stem;
                } elseif ($prefix == 'belajar') {
                    self::$prefix = 4;
                    $word = 'ajar';
                } elseif ($prefix == 'be' && self::endsWith($stem, 'er')) {
                    self::$prefix = 4;
                    $word = substr($stem, 2); // Remove 'er' from the stem
                } else {
                    $word = $stem;
                }
                break;
            }
        }

        return $word;
    }

    private static function removeSuffix($word)
    {
        $suffixes = ['kan', 'an', 'i'];

        foreach ($suffixes as $suffix) {
            if (self::endsWith($word, $suffix)) {
                $stem = substr($word, 0, -strlen($suffix));

                if ($suffix == 'kan') {
                    if (self::SUFFIX_KAN_OK()) {
                        $word = $stem;
                        self::$measure -= 1;
                    }
                } elseif ($suffix == 'an') {
                    if (self::SUFFIX_AN_OK()) {
                        $word = $stem;
                        self::$measure -= 1;
                    }
                } elseif ($suffix == 'i') {
                    if (self::SUFFIX_I_OK($stem)) {
                        $word = $stem;
                        self::$measure -= 1;
                    }
                }
                break;
            }
        }

        return $word;
    }

    private static function SUFFIX_KAN_OK()
    {
        // Prefix not in {ke, peng, per}
        return self::$prefix != 3 && self::$prefix != 2;
    }

    private static function SUFFIX_AN_OK()
    {
        // Prefix not in {di, meng, ter}
        return self::$prefix != 1;
    }

    private static function SUFFIX_I_OK($stem)
    {
        // Prefix not in {ke, peng, ber}
        if (self::$prefix <= 2 && substr($stem, -1) != 's') {
            return true;
        }
        return false;
    }

    private static function isVowel($char)
    {
        return in_array($char, self::$vowels);
    }

    // Replace substr with mb_substr
    private static function startsWith($string, $prefix)
    {
        return mb_substr($string, 0, mb_strlen($prefix)) === $prefix;
    }

    private static function endsWith($string, $suffix)
    {
        return mb_substr($string, -mb_strlen($suffix)) === $suffix;
    }

    private static function calculateMeasure($word)
    {
        $count = 0;
        $length = mb_strlen($word);

        for ($i = 0; $i < $length; $i++) {
            if (in_array(mb_substr($word, $i, 1), self::$vowels)) {
                $count++;
            }
        }

        return $count;
    }

    private static function removeParticle($word)
    {
        $particles = ['kah', 'lah', 'pun'];

        foreach ($particles as $particle) {
            if (self::endsWith($word, $particle)) {
                $word = mb_substr($word, 0, -mb_strlen($particle));
                self::$measure -= 1;
                break;
            }
        }

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
}
?>