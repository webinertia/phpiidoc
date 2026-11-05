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
use Phpiidoc\Console\Container\PhpiidocCommandFactory;
use Phpiidoc\Console\PhpiidocCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Webware\Console\ConsoleInterface;

#[CoversClass(ConfigProvider::class)]
#[CoversMethod(ConfigProvider::class, '__invoke')]
#[CoversMethod(ConfigProvider::class, 'getDependencies')]
final class ConfigProviderTest extends TestCase
{
    #[Test]
    public function providesTheCommandFactoryAndContributesTheCommand(): void
    {
        $expected = [
            'dependencies'          => [
                'factories' => [
                    PhpiidocCommand::class => PhpiidocCommandFactory::class,
                ],
            ],
            ConsoleInterface::class => [
                'commands' => [
                    'phpiidoc:build' => PhpiidocCommand::class,
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
                    PhpiidocCommand::class => PhpiidocCommandFactory::class,
                ],
            ],
            new ConfigProvider()->getDependencies(),
        );
    }

    #[Test]
    public function doesNotBindTheSymfonyConsoleApplication(): void
    {
        $dependencies = new ConfigProvider()->getDependencies();

        static::assertArrayNotHasKey(Application::class, $dependencies['factories']);
    }

    #[Test]
    public function contributesTheBuildCommandUnderTheConsoleKey(): void
    {
        $config = new ConfigProvider()->__invoke();

        static::assertSame(
            PhpiidocCommand::class,
            $config[ConsoleInterface::class]['commands']['phpiidoc:build'],
        );
    }
}
