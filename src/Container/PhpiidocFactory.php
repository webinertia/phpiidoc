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

namespace Phpiidoc\Container;

use Composer\InstalledVersions;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\CommandLoader\ContainerCommandLoader;

/**
 * Builds phpiidoc's own console application.
 *
 * This factory is owned by the package rather than reusing
 * {@see \Webware\Console\Container\SymfonyFactory} because phpiidoc is also
 * intended to be usable as a plain service inside a Mezzio host application.
 * In that context the host already owns its console identity, and this package
 * must not impose webware-console's name, version, or menu command on it.
 *
 * @internal
 */
final readonly class PhpiidocFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container): Application
    {
        $application = new Application(
            name   : 'phpiidoc',
            version: InstalledVersions::getPrettyVersion('phpiidoc/phpiidoc') ?? 'dev',
        );

        $application->setCommandLoader($container->get(ContainerCommandLoader::class));

        return $application;
    }
}
