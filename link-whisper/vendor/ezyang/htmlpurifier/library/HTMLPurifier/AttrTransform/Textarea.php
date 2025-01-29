<?php

namespace LWVendor;

/**
 * Sets height/width defaults for <textarea>
 */
class HTMLPurifier_AttrTransform_Textarea extends HTMLPurifier_AttrTransform
{
    /**
     * @param array $attr
     * @param HTMLPurifier_Config $config
     * @param HTMLPurifier_Context $context
     * @return array
     */
    public function transform($attr, $config, $context)
    {
        // Calculated from Firefox
        if (!isset($attr['cols'])) {
            $attr['cols'] = '22';
        }
        if (!isset($attr['rows'])) {
            $attr['rows'] = '3';
        }
        return $attr;
    }
}
/**
 * Sets height/width defaults for <textarea>
 */
\class_alias('LWVendor\\HTMLPurifier_AttrTransform_Textarea', 'HTMLPurifier_AttrTransform_Textarea', \false);
// vim: et sw=4 sts=4
