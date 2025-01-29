<?php

namespace LWVendor;

/**
 * Module adds the target=blank attribute transformation to a tags.  It
 * is enabled by HTML.TargetBlank
 */
class HTMLPurifier_HTMLModule_TargetBlank extends HTMLPurifier_HTMLModule
{
    /**
     * @type string
     */
    public $name = 'TargetBlank';
    /**
     * @param HTMLPurifier_Config $config
     */
    public function setup($config)
    {
        $a = $this->addBlankElement('a');
        $a->attr_transform_post[] = new HTMLPurifier_AttrTransform_TargetBlank();
    }
}
/**
 * Module adds the target=blank attribute transformation to a tags.  It
 * is enabled by HTML.TargetBlank
 */
\class_alias('LWVendor\\HTMLPurifier_HTMLModule_TargetBlank', 'HTMLPurifier_HTMLModule_TargetBlank', \false);
// vim: et sw=4 sts=4
