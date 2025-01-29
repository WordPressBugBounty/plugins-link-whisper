<?php

namespace LWVendor;

/**
 * Concrete empty token class.
 */
class HTMLPurifier_Token_Empty extends HTMLPurifier_Token_Tag
{
    public function toNode()
    {
        $n = parent::toNode();
        $n->empty = \true;
        return $n;
    }
}
/**
 * Concrete empty token class.
 */
\class_alias('LWVendor\\HTMLPurifier_Token_Empty', 'HTMLPurifier_Token_Empty', \false);
// vim: et sw=4 sts=4
