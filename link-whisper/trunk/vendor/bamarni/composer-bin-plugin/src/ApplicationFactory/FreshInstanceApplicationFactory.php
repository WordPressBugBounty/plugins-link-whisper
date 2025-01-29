<?php

declare (strict_types=1);
namespace LWVendor\Bamarni\Composer\Bin\ApplicationFactory;

use LWVendor\Composer\Console\Application;
final class FreshInstanceApplicationFactory implements NamespaceApplicationFactory
{
    public function create(Application $existingApplication) : Application
    {
        return new Application();
    }
}
