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

namespace PhpiidocTest\Diagnostic;

use Phpiidoc\Diagnostic\Severity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Severity::class)]
final class SeverityTest extends TestCase
{
    #[Test]
    public function pinsTheStringValues(): void
    {
        static::assertSame('error', Severity::Error->value);
        static::assertSame('warning', Severity::Warning->value);
        static::assertSame('info', Severity::Info->value);
    }
}
