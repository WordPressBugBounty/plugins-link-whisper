<?php

namespace LWVendor;

/**
 * Global exception class for HTML Purifier; any exceptions we throw
 * are from here.
 */
class HTMLPurifier_Exception extends \Exception
{
}
/**
 * Global exception class for HTML Purifier; any exceptions we throw
 * are from here.
 */
\class_alias('LWVendor\\HTMLPurifier_Exception', 'HTMLPurifier_Exception', \false);
// vim: et sw=4 sts=4
