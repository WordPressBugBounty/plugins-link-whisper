<?php

namespace LWVendor;

/**
 * Validates nntp (Network News Transfer Protocol) as defined by generic RFC 1738
 */
class HTMLPurifier_URIScheme_nntp extends HTMLPurifier_URIScheme
{
    /**
     * @type int
     */
    public $default_port = 119;
    /**
     * @type bool
     */
    public $browsable = \false;
    /**
     * @param HTMLPurifier_URI $uri
     * @param HTMLPurifier_Config $config
     * @param HTMLPurifier_Context $context
     * @return bool
     */
    public function doValidate(&$uri, $config, $context)
    {
        $uri->userinfo = null;
        $uri->query = null;
        return \true;
    }
}
/**
 * Validates nntp (Network News Transfer Protocol) as defined by generic RFC 1738
 */
\class_alias('LWVendor\\HTMLPurifier_URIScheme_nntp', 'HTMLPurifier_URIScheme_nntp', \false);
// vim: et sw=4 sts=4
