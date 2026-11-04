<?php

declare(strict_types=1);

/**
 * This file is part of the Webware Skeleton package.
 *
 * Copyright (c) 2026 Joey Smith <jsmith@webinertia.net>
 * and contributors.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace WebwareTest\Skeleton;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\Skeleton\ConfigProvider;

#[CoversClass(ConfigProvider::class)]
#[CoversMethod(ConfigProvider::class, '__invoke')]
final class ConfigProviderTest extends TestCase
{
    #[Test]
    public function providesAnEmptyDependencyFactoryMap(): void
    {
        $expected = [
            'dependencies' => [
                'factories' => [],
            ],
        ];

        self::assertSame($expected, new ConfigProvider()->__invoke());
    }
}
