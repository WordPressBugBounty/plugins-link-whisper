<?php

namespace LWVendor;

/**
 * Composite strategy that runs multiple strategies on tokens.
 */
abstract class HTMLPurifier_Strategy_Composite extends HTMLPurifier_Strategy
{
    /**
     * List of strategies to run tokens through.
     * @type HTMLPurifier_Strategy[]
     */
    protected $strategies = array();
    /**
     * @param HTMLPurifier_Token[] $tokens
     * @param HTMLPurifier_Config $config
     * @param HTMLPurifier_Context $context
     * @return HTMLPurifier_Token[]
     */
    public function execute($tokens, $config, $context)
    {
        foreach ($this->strategies as $strategy) {
            $tokens = $strategy->execute($tokens, $config, $context);
        }
        return $tokens;
    }
}
/**
 * Composite strategy that runs multiple strategies on tokens.
 */
\class_alias('LWVendor\\HTMLPurifier_Strategy_Composite', 'HTMLPurifier_Strategy_Composite', \false);
// vim: et sw=4 sts=4
