<?php

namespace LWVendor;

class HTMLPurifier_HTMLModule_NonXMLCommonAttributes extends HTMLPurifier_HTMLModule
{
    /**
     * @type string
     */
    public $name = 'NonXMLCommonAttributes';
    /**
     * @type array
     */
    public $attr_collections = array('Lang' => array('lang' => 'LanguageCode'));
}
\class_alias('LWVendor\\HTMLPurifier_HTMLModule_NonXMLCommonAttributes', 'HTMLPurifier_HTMLModule_NonXMLCommonAttributes', \false);
// vim: et sw=4 sts=4
