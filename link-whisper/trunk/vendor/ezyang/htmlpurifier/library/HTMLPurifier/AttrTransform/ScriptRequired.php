<?php

namespace LWVendor;

/**
 * Implements required attribute stipulation for <script>
 */
class HTMLPurifier_AttrTransform_ScriptRequired extends HTMLPurifier_AttrTransform
{
    /**
     * @param array $attr
     * @param HTMLPurifier_Config $config
     * @param HTMLPurifier_Context $context
     * @return array
     */
    public function transform($attr, $config, $context)
    {
        if (!isset($attr['type'])) {
            $attr['type'] = 'text/javascript';
        }
        return $attr;
    }
}
/**
 * Implements required attribute stipulation for <script>
 */
\class_alias('LWVendor\\HTMLPurifier_AttrTransform_ScriptRequired', 'HTMLPurifier_AttrTransform_ScriptRequired', \false);
// vim: et sw=4 sts=4
