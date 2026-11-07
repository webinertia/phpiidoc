<?php

declare(strict_types=1);

use Laminas\ConfigAggregator\ArrayProvider;
use Laminas\ConfigAggregator\ConfigAggregator;
use Laminas\ConfigAggregator\PhpFileProvider;
use Laminas\Diactoros\ConfigProvider as DiactorosConfigProvider;
use Laminas\ServiceManager\ConfigProvider as ServiceManagerConfigProvider;
use Laminas\View\ConfigProvider as ViewConfigProvider;
use Phly\EventDispatcher\ConfigProvider as DispatcherConfigProvider;
use Phpiidoc\ConfigProvider;
use Webware\Console\ConfigProvider as ConsoleConfigProvider;
use Webware\Event\ConfigProvider as EventConfigProvider;
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
        // Registers the concrete event dispatcher the event provider aliases.
        DispatcherConfigProvider::class,
        // Owns the event dispatcher alias and the listener provider, so it
        // loads after the concrete dispatcher it aliases.
        EventConfigProvider::class,
        RouterConfigProvider::class,
        // Wires the console Application and the command loader.
        ConsoleConfigProvider::class,
        // Registers this package's own services and contributes its console
        // command. The console Application and the command loader come from
        // the console provider above.
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
