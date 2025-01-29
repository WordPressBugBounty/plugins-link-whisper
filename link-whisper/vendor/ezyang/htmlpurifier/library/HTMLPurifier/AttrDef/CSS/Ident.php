<?php

namespace LWVendor;

/**
 * Validates based on {ident} CSS grammar production
 */
class HTMLPurifier_AttrDef_CSS_Ident extends HTMLPurifier_AttrDef
{
    /**
     * @param string $string
     * @param HTMLPurifier_Config $config
     * @param HTMLPurifier_Context $context
     * @return bool|string
     */
    public function validate($string, $config, $context)
    {
        $string = \trim($string);
        // early abort: '' and '0' (strings that convert to false) are invalid
        if (!$string) {
            return \false;
        }
        $pattern = '/^(-?[A-Za-z_][A-Za-z_\\-0-9]*)$/';
        if (!\preg_match($pattern, $string)) {
            return \false;
        }
        return $string;
    }
}
/**
 * Validates based on {ident} CSS grammar production
 */
\class_alias('LWVendor\\HTMLPurifier_AttrDef_CSS_Ident', 'HTMLPurifier_AttrDef_CSS_Ident', \false);
// vim: et sw=4 sts=4
