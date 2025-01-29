<?php

declare (strict_types=1);
namespace LWVendor\Bamarni\Composer\Bin\Config;

use LWVendor\Composer\Config as ComposerConfig;
use LWVendor\Composer\Factory;
use LWVendor\Composer\Json\JsonFile;
use LWVendor\Composer\Json\JsonValidationException;
use LWVendor\Seld\JsonLint\ParsingException;
final class ConfigFactory
{
    /**
     * @throws JsonValidationException
     * @throws ParsingException
     */
    public static function createConfig() : ComposerConfig
    {
        $config = Factory::createConfig();
        $file = new JsonFile(Factory::getComposerFile());
        if (!$file->exists()) {
            return $config;
        }
        $file->validateSchema(JsonFile::LAX_SCHEMA);
        $config->merge($file->read());
        return $config;
    }
    private function __construct()
    {
    }
}
