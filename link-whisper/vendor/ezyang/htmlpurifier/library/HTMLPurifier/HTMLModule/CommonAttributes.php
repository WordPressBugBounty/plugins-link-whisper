<?php

namespace LWVendor;

class HTMLPurifier_HTMLModule_CommonAttributes extends HTMLPurifier_HTMLModule
{
    /**
     * @type string
     */
    public $name = 'CommonAttributes';
    /**
     * @type array
     */
    public $attr_collections = array('Core' => array(
        0 => array('Style'),
        // 'xml:space' => false,
        'class' => 'Class',
        'id' => 'ID',
        'title' => 'CDATA',
        'contenteditable' => 'ContentEditable',
    ), 'Lang' => array(), 'I18N' => array(0 => array('Lang')), 'Common' => array(0 => array('Core', 'I18N')));
}
\class_alias('LWVendor\\HTMLPurifier_HTMLModule_CommonAttributes', 'HTMLPurifier_HTMLModule_CommonAttributes', \false);
// vim: et sw=4 sts=4
