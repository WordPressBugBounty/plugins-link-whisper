<?php

declare (strict_types=1);
namespace LWVendor;

require __DIR__ . '/vendor-bin/tool/vendor/autoload.php';
use LWVendor\Composer\InstalledVersions;
echo "Get versions installed in vendor-bin/tool; executed from root." . \PHP_EOL;
echo InstalledVersions::getPrettyVersion('psr/log') . \PHP_EOL;
