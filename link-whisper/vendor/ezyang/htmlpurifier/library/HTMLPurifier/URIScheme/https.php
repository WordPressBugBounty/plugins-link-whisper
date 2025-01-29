<?php

namespace LWVendor;

/**
 * Validates https (Secure HTTP) according to http scheme.
 */
class HTMLPurifier_URIScheme_https extends HTMLPurifier_URIScheme_http
{
    /**
     * @type int
     */
    public $default_port = 443;
    /**
     * @type bool
     */
    public $secure = \true;
}
/**
 * Validates https (Secure HTTP) according to http scheme.
 */
\class_alias('LWVendor\\HTMLPurifier_URIScheme_https', 'HTMLPurifier_URIScheme_https', \false);
// vim: et sw=4 sts=4
