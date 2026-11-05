<?php

declare(strict_types=1);

/**
 * This file is part of the Phpiidoc package.
 *
 * Copyright (c) 2026 Joey Smith <jsmith@webinertia.net>
 * and contributors.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Phpiidoc;

use Phpiidoc\Container\PhpiidocFactory;
use Symfony\Component\Console\Application;

/**
 * Wiring entry point for the package.
 *
 * Declared under `extra.laminas.config-provider` in composer.json, so a consumer's
 * config aggregator merges this without any further registration.
 *
 * @internal
 */
final class ConfigProvider
{
    /** @return array<string, mixed> */
    public function getDependencies(): array
    {
        return [
            'factories' => [
                Application::class => PhpiidocFactory::class,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencies(),
        ];
    }
}
