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

namespace PhpiidocTestIntegration;

use Phpiidoc\Console\Container\PhpiidocCommandFactory;
use Phpiidoc\Console\PhpiidocCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Application;
use Webware\MessageBus\MessageBusInterface;

use function file_exists;
use function unlink;

/**
 * End-to-end proof that the aggregated configuration and the service manager
 * together build a container that can resolve this package's console command.
 */
#[CoversClass(PhpiidocCommand::class)]
#[CoversClass(PhpiidocCommandFactory::class)]
final class ContainerWiringTest extends TestCase
{
    private const CONFIG_CACHE_FILE = __DIR__ . '/../../data/cache/config-cache.php';

    /**
     * The aggregator caches the merged configuration on disk, so a stale cache
     * would let these tests pass against configuration that no longer exists.
     */
    protected function setUp(): void
    {
        if (file_exists(self::CONFIG_CACHE_FILE)) {
            unlink(self::CONFIG_CACHE_FILE);
        }
    }

    #[Test]
    public function buildsAContainerFromTheAggregatedConfiguration(): void
    {
        static::assertInstanceOf(ContainerInterface::class, $this->buildContainer());
    }

    /**
     * Building the command forces the bus, the event dispatcher and the
     * listener provider aggregate to resolve, so this is the whole wiring.
     */
    #[Test]
    public function resolvesTheBuildCommandFromTheRealContainer(): void
    {
        $container = $this->buildContainer();

        static::assertInstanceOf(ContainerInterface::class, $container);

        $command = $container->get(PhpiidocCommand::class);

        static::assertInstanceOf(PhpiidocCommand::class, $command);
        static::assertSame('phpiidoc:build', $command->getName());
        static::assertInstanceOf(MessageBusInterface::class, $command->messageBus);
    }

    #[Test]
    public function leavesTheConsoleApplicationWithTheConsoleProvider(): void
    {
        $container = $this->buildContainer();

        static::assertInstanceOf(ContainerInterface::class, $container);

        $application = $container->get(Application::class);

        static::assertInstanceOf(Application::class, $application);
        static::assertNotInstanceOf(PhpiidocCommand::class, $application);
    }

    #[Test]
    public function exposesTheMergedConfigurationAsTheConfigService(): void
    {
        $container = $this->buildContainer();

        static::assertInstanceOf(ContainerInterface::class, $container);

        static::assertIsArray($container->get('config'));
    }

    private function buildContainer(): mixed
    {
        return require __DIR__ . '/../../config/container.php';
    }
}
