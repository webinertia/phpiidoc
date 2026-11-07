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

namespace PhpiidocTest\Console\Container;

use Phpiidoc\Console\Container\PhpiidocCommandFactory;
use Phpiidoc\Console\PhpiidocCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

#[CoversClass(PhpiidocCommandFactory::class)]
#[CoversMethod(PhpiidocCommandFactory::class, '__invoke')]
#[CoversClass(PhpiidocCommand::class)]
final class PhpiidocCommandFactoryTest extends TestCase
{
    #[Test]
    public function buildsTheCommandFromTheContainer(): void
    {
        $container = $this->createStub(ContainerInterface::class);

        $command = new PhpiidocCommandFactory()($container);

        static::assertInstanceOf(PhpiidocCommand::class, $command);
    }
}
