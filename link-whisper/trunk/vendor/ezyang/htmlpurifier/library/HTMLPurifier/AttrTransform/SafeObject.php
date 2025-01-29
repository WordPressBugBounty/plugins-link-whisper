<?php

namespace LWVendor;

/**
 * Writes default type for all objects. Currently only supports flash.
 */
class HTMLPurifier_AttrTransform_SafeObject extends HTMLPurifier_AttrTransform
{
    /**
     * @type string
     */
    public $name = "SafeObject";
    /**
     * @param array $attr
     * @param HTMLPurifier_Config $config
     * @param HTMLPurifier_Context $context
     * @return array
     */
    public function transform($attr, $config, $context)
    {
        if (!isset($attr['type'])) {
            $attr['type'] = 'application/x-shockwave-flash';
        }
        return $attr;
    }
}
/**
 * Writes default type for all objects. Currently only supports flash.
 */
\class_alias('LWVendor\\HTMLPurifier_AttrTransform_SafeObject', 'HTMLPurifier_AttrTransform_SafeObject', \false);
// vim: et sw=4 sts=4
