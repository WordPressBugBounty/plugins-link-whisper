<?php

namespace LWVendor;

/**
 * Module adds the target-based noopener attribute transformation to a tags.  It
 * is enabled by HTML.TargetNoopener
 */
class HTMLPurifier_HTMLModule_TargetNoopener extends HTMLPurifier_HTMLModule
{
    /**
     * @type string
     */
    public $name = 'TargetNoopener';
    /**
     * @param HTMLPurifier_Config $config
     */
    public function setup($config)
    {
        $a = $this->addBlankElement('a');
        $a->attr_transform_post[] = new HTMLPurifier_AttrTransform_TargetNoopener();
    }
}
/**
 * Module adds the target-based noopener attribute transformation to a tags.  It
 * is enabled by HTML.TargetNoopener
 */
\class_alias('LWVendor\\HTMLPurifier_HTMLModule_TargetNoopener', 'HTMLPurifier_HTMLModule_TargetNoopener', \false);
