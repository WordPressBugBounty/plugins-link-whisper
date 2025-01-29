<?php

namespace LWVendor;

class HTMLPurifier_HTMLModule_Tidy_Transitional extends HTMLPurifier_HTMLModule_Tidy_XHTMLAndHTML4
{
    /**
     * @type string
     */
    public $name = 'Tidy_Transitional';
    /**
     * @type string
     */
    public $defaultLevel = 'heavy';
}
\class_alias('LWVendor\\HTMLPurifier_HTMLModule_Tidy_Transitional', 'HTMLPurifier_HTMLModule_Tidy_Transitional', \false);
// vim: et sw=4 sts=4
