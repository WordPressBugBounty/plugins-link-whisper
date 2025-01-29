<?php

namespace LWVendor;

/**
 * Module defines proprietary tags and attributes in HTML.
 * @warning If this module is enabled, standards-compliance is off!
 */
class HTMLPurifier_HTMLModule_Proprietary extends HTMLPurifier_HTMLModule
{
    /**
     * @type string
     */
    public $name = 'Proprietary';
    /**
     * @param HTMLPurifier_Config $config
     */
    public function setup($config)
    {
        $this->addElement('marquee', 'Inline', 'Flow', 'Common', array('direction' => 'Enum#left,right,up,down', 'behavior' => 'Enum#alternate', 'width' => 'Length', 'height' => 'Length', 'scrolldelay' => 'Number', 'scrollamount' => 'Number', 'loop' => 'Number', 'bgcolor' => 'Color', 'hspace' => 'Pixels', 'vspace' => 'Pixels'));
    }
}
/**
 * Module defines proprietary tags and attributes in HTML.
 * @warning If this module is enabled, standards-compliance is off!
 */
\class_alias('LWVendor\\HTMLPurifier_HTMLModule_Proprietary', 'HTMLPurifier_HTMLModule_Proprietary', \false);
// vim: et sw=4 sts=4
