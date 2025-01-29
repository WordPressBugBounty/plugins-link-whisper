<?php

namespace LWVendor;

/**
 * Module adds the nofollow attribute transformation to a tags.  It
 * is enabled by HTML.Nofollow
 */
class HTMLPurifier_HTMLModule_Nofollow extends HTMLPurifier_HTMLModule
{
    /**
     * @type string
     */
    public $name = 'Nofollow';
    /**
     * @param HTMLPurifier_Config $config
     */
    public function setup($config)
    {
        $a = $this->addBlankElement('a');
        $a->attr_transform_post[] = new HTMLPurifier_AttrTransform_Nofollow();
    }
}
/**
 * Module adds the nofollow attribute transformation to a tags.  It
 * is enabled by HTML.Nofollow
 */
\class_alias('LWVendor\\HTMLPurifier_HTMLModule_Nofollow', 'HTMLPurifier_HTMLModule_Nofollow', \false);
// vim: et sw=4 sts=4
