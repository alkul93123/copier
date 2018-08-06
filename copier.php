#!/usr/bin/php
<?php

require 'src/Autoloader.php';
require 'vendor/autoload.php';
spl_autoload_register(['Autoloader', 'autoload'], true, true);

use src\Copier;
use src\Logger;
use src\IOHelper;
use src\Configurator;
use exceptions\ConfigException;

try {
    $config = include './config.php';

    $configurator = new Configurator($config);
    define('LOG_PATH', $configurator->config['logPath']);

    if ($configurator->config['consoleOutput'] == true) {
        echo IOHelper::colorize("Check configuration success", IOHelper::GREEN, true);
    }

    $copier = new Copier($configurator);
    $copier->fire();

} catch (ConfigException $e) {
    echo IOHelper::colorize("Configuration error:\n------------\n{$e->getMessage()}\n------------\n", IOHelper::RED, true);
    echo IOHelper::colorize("Check configuration: file './config.php'", IOHelper::YELLOW, true);
    Logger::error("Configuration error:\n------------\n{$e->getMessage()}\n------------\n");

} catch (\Exception $e) {
    echo IOHelper::colorize("Unexpected error:\n------------\n{$e->getMessage()}\n------------\n", IOHelper::RED, true);
    Logger::error("Unexpected error:\n------------\n{$e->getMessage()}\n------------\n");
}
