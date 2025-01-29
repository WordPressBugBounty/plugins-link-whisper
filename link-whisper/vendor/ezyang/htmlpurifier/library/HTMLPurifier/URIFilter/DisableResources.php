<?php

namespace LWVendor;

class HTMLPurifier_URIFilter_DisableResources extends HTMLPurifier_URIFilter
{
    /**
     * @type string
     */
    public $name = 'DisableResources';
    /**
     * @param HTMLPurifier_URI $uri
     * @param HTMLPurifier_Config $config
     * @param HTMLPurifier_Context $context
     * @return bool
     */
    public function filter(&$uri, $config, $context)
    {
        return !$context->get('EmbeddedURI', \true);
    }
}
\class_alias('LWVendor\\HTMLPurifier_URIFilter_DisableResources', 'HTMLPurifier_URIFilter_DisableResources', \false);
// vim: et sw=4 sts=4
