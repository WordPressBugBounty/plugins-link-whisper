<?php

namespace LWVendor;

/**
 * Pre-transform that changes deprecated name attribute to ID if necessary
 */
class HTMLPurifier_AttrTransform_Name extends HTMLPurifier_AttrTransform
{
    /**
     * @param array $attr
     * @param HTMLPurifier_Config $config
     * @param HTMLPurifier_Context $context
     * @return array
     */
    public function transform($attr, $config, $context)
    {
        // Abort early if we're using relaxed definition of name
        if ($config->get('HTML.Attr.Name.UseCDATA')) {
            return $attr;
        }
        if (!isset($attr['name'])) {
            return $attr;
        }
        $id = $this->confiscateAttr($attr, 'name');
        if (isset($attr['id'])) {
            return $attr;
        }
        $attr['id'] = $id;
        return $attr;
    }
}
/**
 * Pre-transform that changes deprecated name attribute to ID if necessary
 */
\class_alias('LWVendor\\HTMLPurifier_AttrTransform_Name', 'HTMLPurifier_AttrTransform_Name', \false);
// vim: et sw=4 sts=4
