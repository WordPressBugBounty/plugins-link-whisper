<?php

namespace LWVendor;

/**
 * Validates arbitrary text according to the HTML spec.
 */
class HTMLPurifier_AttrDef_Text extends HTMLPurifier_AttrDef
{
    /**
     * @param string $string
     * @param HTMLPurifier_Config $config
     * @param HTMLPurifier_Context $context
     * @return bool|string
     */
    public function validate($string, $config, $context)
    {
        return $this->parseCDATA($string);
    }
}
/**
 * Validates arbitrary text according to the HTML spec.
 */
\class_alias('LWVendor\\HTMLPurifier_AttrDef_Text', 'HTMLPurifier_AttrDef_Text', \false);
// vim: et sw=4 sts=4
