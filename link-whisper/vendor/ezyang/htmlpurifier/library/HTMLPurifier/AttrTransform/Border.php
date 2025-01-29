<?php

namespace LWVendor;

/**
 * Pre-transform that changes deprecated border attribute to CSS.
 */
class HTMLPurifier_AttrTransform_Border extends HTMLPurifier_AttrTransform
{
    /**
     * @param array $attr
     * @param HTMLPurifier_Config $config
     * @param HTMLPurifier_Context $context
     * @return array
     */
    public function transform($attr, $config, $context)
    {
        if (!isset($attr['border'])) {
            return $attr;
        }
        $border_width = $this->confiscateAttr($attr, 'border');
        // some validation should happen here
        $this->prependCSS($attr, "border:{$border_width}px solid;");
        return $attr;
    }
}
/**
 * Pre-transform that changes deprecated border attribute to CSS.
 */
\class_alias('LWVendor\\HTMLPurifier_AttrTransform_Border', 'HTMLPurifier_AttrTransform_Border', \false);
// vim: et sw=4 sts=4
