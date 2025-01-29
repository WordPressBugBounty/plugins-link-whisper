<?php

declare (strict_types=1);
namespace LWVendor;

require __DIR__ . '/vendor/autoload.php';
use LWVendor\Composer\InstalledVersions;
echo "Get versions installed in root; executed from root." . \PHP_EOL;
echo InstalledVersions::getPrettyVersion('psr/log') . \PHP_EOL;
