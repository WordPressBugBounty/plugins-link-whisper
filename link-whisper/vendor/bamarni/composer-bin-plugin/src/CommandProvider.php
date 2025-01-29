<?php

declare (strict_types=1);
namespace LWVendor\Bamarni\Composer\Bin;

use LWVendor\Bamarni\Composer\Bin\Command\BinCommand;
use LWVendor\Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
/**
 * @final Will be final in 2.x.
 */
class CommandProvider implements CommandProviderCapability
{
    public function getCommands() : array
    {
        return [new BinCommand()];
    }
}
