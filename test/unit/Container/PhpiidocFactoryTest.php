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

namespace PhpiidocTest\Container;

use Phpiidoc\Container\PhpiidocFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\CommandLoader\ContainerCommandLoader;

#[CoversClass(PhpiidocFactory::class)]
#[CoversMethod(PhpiidocFactory::class, '__invoke')]
final class PhpiidocFactoryTest extends TestCase
{
    #[Test]
    public function buildsAnApplicationNamedPhpiidoc(): void
    {
        $loader = new ContainerCommandLoader($this->createStub(ContainerInterface::class), []);

        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturn($loader);

        $application = (new PhpiidocFactory())($container);

        static::assertInstanceOf(Application::class, $application);
        static::assertSame('phpiidoc', $application->getName());
    }

    #[Test]
    public function requestsTheContainerCommandLoaderFromTheContainer(): void
    {
        $loader = new ContainerCommandLoader($this->createStub(ContainerInterface::class), []);

        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())
            ->method('get')
            ->with(ContainerCommandLoader::class)
            ->willReturn($loader);

        (new PhpiidocFactory())($container);
    }
}
