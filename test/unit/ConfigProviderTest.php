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

namespace PhpiidocTest;

use Phpiidoc\ConfigProvider;
use Phpiidoc\Container\PhpiidocFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;

#[CoversClass(ConfigProvider::class)]
#[CoversMethod(ConfigProvider::class, '__invoke')]
#[CoversMethod(ConfigProvider::class, 'getDependencies')]
final class ConfigProviderTest extends TestCase
{
    #[Test]
    public function providesTheApplicationFactory(): void
    {
        $expected = [
            'dependencies' => [
                'factories' => [
                    Application::class => PhpiidocFactory::class,
                ],
            ],
        ];

        static::assertSame($expected, new ConfigProvider()->__invoke());
    }

    #[Test]
    public function returnsTheDependencyFactoryMap(): void
    {
        static::assertSame(
            [
                'factories' => [
                    Application::class => PhpiidocFactory::class,
                ],
            ],
            new ConfigProvider()->getDependencies(),
        );
    }
}
