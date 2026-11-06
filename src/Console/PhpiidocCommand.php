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

namespace Phpiidoc\Console;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Webware\MessageBus\MessageBusInterface;

/**
 * The build entry point for this package.
 *
 * The command carries the bus it will dispatch through, and nothing else, so
 * the build pipeline can be added without changing the wiring around it.
 *
 * @api
 */
#[AsCommand(
    name       : 'phpiidoc:build',
    description: 'Build the documentation set for the current project',
)]
final class PhpiidocCommand extends Command
{
    /**
     * @throws LogicException When the command definition is not valid.
     */
    public function __construct(
        public private(set) MessageBusInterface $messageBus {
            get => $this->messageBus;
            set(MessageBusInterface $value) => $this->messageBus = $value;
        },
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return Command::SUCCESS;
    }
}
