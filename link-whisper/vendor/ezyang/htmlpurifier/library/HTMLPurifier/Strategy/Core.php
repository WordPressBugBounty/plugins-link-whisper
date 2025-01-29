<?php

namespace LWVendor;

/**
 * Core strategy composed of the big four strategies.
 */
class HTMLPurifier_Strategy_Core extends HTMLPurifier_Strategy_Composite
{
    public function __construct()
    {
        $this->strategies[] = new HTMLPurifier_Strategy_RemoveForeignElements();
        $this->strategies[] = new HTMLPurifier_Strategy_MakeWellFormed();
        $this->strategies[] = new HTMLPurifier_Strategy_FixNesting();
        $this->strategies[] = new HTMLPurifier_Strategy_ValidateAttributes();
    }
}
/**
 * Core strategy composed of the big four strategies.
 */
\class_alias('LWVendor\\HTMLPurifier_Strategy_Core', 'HTMLPurifier_Strategy_Core', \false);
// vim: et sw=4 sts=4
