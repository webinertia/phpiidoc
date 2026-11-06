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

use Phpiidoc\SafeMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SafeMode::class)]
#[CoversMethod(SafeMode::class, 'label')]
#[CoversMethod(SafeMode::class, 'isAtLeast')]
#[CoversMethod(SafeMode::class, 'confinesReads')]
#[CoversMethod(SafeMode::class, 'processesIncludes')]
final class SafeModeTest extends TestCase
{
    #[Test]
    public function carriesTheIntegerValuesRubyAssigns(): void
    {
        static::assertSame(0, SafeMode::Unsafe->value);
        static::assertSame(1, SafeMode::Safe->value);
        static::assertSame(10, SafeMode::Server->value);
        static::assertSame(20, SafeMode::Secure->value);
    }

    #[Test]
    public function labelsTheModesAsRubyPublishesThem(): void
    {
        static::assertSame('unsafe', SafeMode::Unsafe->label());
        static::assertSame('safe', SafeMode::Safe->label());
        static::assertSame('server', SafeMode::Server->label());
        static::assertSame('secure', SafeMode::Secure->label());
    }

    #[Test]
    public function ordersLevelsByRestrictiveness(): void
    {
        foreach (SafeMode::cases() as $mode) {
            foreach (SafeMode::cases() as $other) {
                static::assertSame(
                    $mode->value >= $other->value,
                    $mode->isAtLeast($other),
                );
            }
        }
    }

    #[Test]
    public function confinesReadsAtSafeAndAbove(): void
    {
        static::assertFalse(SafeMode::Unsafe->confinesReads());
        static::assertTrue(SafeMode::Safe->confinesReads());
        static::assertTrue(SafeMode::Server->confinesReads());
        static::assertTrue(SafeMode::Secure->confinesReads());
    }

    #[Test]
    public function processesIncludesBelowSecure(): void
    {
        static::assertTrue(SafeMode::Unsafe->processesIncludes());
        static::assertTrue(SafeMode::Safe->processesIncludes());
        static::assertTrue(SafeMode::Server->processesIncludes());
        static::assertFalse(SafeMode::Secure->processesIncludes());
    }
}
