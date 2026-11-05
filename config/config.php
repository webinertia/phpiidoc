<?php

declare(strict_types=1);

use Laminas\ConfigAggregator\ArrayProvider;
use Laminas\ConfigAggregator\ConfigAggregator;
use Laminas\ConfigAggregator\PhpFileProvider;
use Laminas\Diactoros\ConfigProvider as DiactorosConfigProvider;
use Laminas\ServiceManager\ConfigProvider as ServiceManagerConfigProvider;
use Laminas\View\ConfigProvider as ViewConfigProvider;
use Phpiidoc\ConfigProvider;
use Webware\Console\ConfigProvider as ConsoleConfigProvider;
use Webware\MessageBus\ConfigProvider as MessageBusConfigProvider;
use Webware\MessageBus\Event\ConfigProvider as MessageBusEventConfigProvider;
use Webware\Router\ConfigProvider as RouterConfigProvider;

// To enable or disable caching, set the `ConfigAggregator::ENABLE_CACHE` boolean
// in `config/autoload/local.php`.
$cacheConfig = [
    'config_cache_path' => 'data/cache/config-cache.php',
];

$aggregator = new ConfigAggregator(
    [
        // Dependency providers. Loaded first, so anything after them wins when
        // two providers register the same service key.
        ServiceManagerConfigProvider::class,
        DiactorosConfigProvider::class,
        ViewConfigProvider::class,
        MessageBusConfigProvider::class,
        MessageBusEventConfigProvider::class,
        RouterConfigProvider::class,
        // Wires the console Application and the command loader.
        ConsoleConfigProvider::class,
        // Wires this package's own services, and overrides the console
        // provider's Application factory with this package's own.
        ConfigProvider::class,
        // Include cache configuration.
        new ArrayProvider($cacheConfig),
        // Load application config in a pre-defined order so that local settings
        // overwrite global settings. (Loaded first to last):
        //   - `global.php`
        //   - `*.global.php`
        //   - `local.php`
        //   - `*.local.php`
        new PhpFileProvider(realpath(__DIR__) . '/autoload/{{,*.}global,{,*.}local}.php'),
        // Load development config if it exists.
        new PhpFileProvider(realpath(__DIR__) . '/development.config.php'),
    ],
    $cacheConfig['config_cache_path'],
);

return $aggregator->getMergedConfig();
