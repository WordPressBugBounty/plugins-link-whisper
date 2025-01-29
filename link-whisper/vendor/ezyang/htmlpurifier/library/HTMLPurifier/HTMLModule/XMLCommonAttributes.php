<?php

namespace LWVendor;

class HTMLPurifier_HTMLModule_XMLCommonAttributes extends HTMLPurifier_HTMLModule
{
    /**
     * @type string
     */
    public $name = 'XMLCommonAttributes';
    /**
     * @type array
     */
    public $attr_collections = array('Lang' => array('xml:lang' => 'LanguageCode'));
}
\class_alias('LWVendor\\HTMLPurifier_HTMLModule_XMLCommonAttributes', 'HTMLPurifier_HTMLModule_XMLCommonAttributes', \false);
// vim: et sw=4 sts=4
