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

namespace Phpiidoc\Console\Container;

use Phpiidoc\Console\PhpiidocCommand;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\Console\Exception\LogicException;
use Webware\MessageBus\MessageBusInterface;

/**
 * @internal
 */
final readonly class PhpiidocCommandFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws LogicException When the command definition is not valid.
     */
    public function __invoke(ContainerInterface $container): PhpiidocCommand
    {
        return new PhpiidocCommand(
            messageBus: $container->get(MessageBusInterface::class),
        );
    }
}
