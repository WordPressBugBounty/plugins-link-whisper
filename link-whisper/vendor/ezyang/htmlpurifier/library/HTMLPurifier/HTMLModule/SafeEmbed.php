<?php

namespace LWVendor;

/**
 * A "safe" embed module. See SafeObject. This is a proprietary element.
 */
class HTMLPurifier_HTMLModule_SafeEmbed extends HTMLPurifier_HTMLModule
{
    /**
     * @type string
     */
    public $name = 'SafeEmbed';
    /**
     * @param HTMLPurifier_Config $config
     */
    public function setup($config)
    {
        $max = $config->get('HTML.MaxImgLength');
        $embed = $this->addElement('embed', 'Inline', 'Empty', 'Common', array('src*' => 'URI#embedded', 'type' => 'Enum#application/x-shockwave-flash', 'width' => 'Pixels#' . $max, 'height' => 'Pixels#' . $max, 'allowscriptaccess' => 'Enum#never', 'allownetworking' => 'Enum#internal', 'flashvars' => 'Text', 'wmode' => 'Enum#window,transparent,opaque', 'name' => 'ID'));
        $embed->attr_transform_post[] = new HTMLPurifier_AttrTransform_SafeEmbed();
    }
}
/**
 * A "safe" embed module. See SafeObject. This is a proprietary element.
 */
\class_alias('LWVendor\\HTMLPurifier_HTMLModule_SafeEmbed', 'HTMLPurifier_HTMLModule_SafeEmbed', \false);
// vim: et sw=4 sts=4
