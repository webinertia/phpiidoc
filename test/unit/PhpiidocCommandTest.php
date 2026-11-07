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

use Phpiidoc\Console\PhpiidocCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(PhpiidocCommand::class)]
#[CoversMethod(PhpiidocCommand::class, 'execute')]
final class PhpiidocCommandTest extends TestCase
{
    #[Test]
    public function returnsSuccessWhenExecuted(): void
    {
        $tester = new CommandTester(new PhpiidocCommand());

        static::assertSame(Command::SUCCESS, $tester->execute([]));
    }
}
