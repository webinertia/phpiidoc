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

namespace PhpiidocTest\Attribute;

use Phpiidoc\Attribute\AttributeSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AttributeSource::class)]
#[CoversMethod(AttributeSource::class, 'outranks')]
#[CoversMethod(AttributeSource::class, 'precedence')]
final class AttributeSourceTest extends TestCase
{
    #[Test]
    public function ranksOptionAboveHeaderAboveDefault(): void
    {
        static::assertSame(3, AttributeSource::Option->precedence());
        static::assertSame(2, AttributeSource::Header->precedence());
        static::assertSame(1, AttributeSource::Default->precedence());
    }

    #[Test]
    public function anOptionSuppliedValueBeatsAHeaderDeclaration(): void
    {
        static::assertTrue(AttributeSource::Option->outranks(AttributeSource::Header));
        static::assertFalse(AttributeSource::Header->outranks(AttributeSource::Option));
    }

    #[Test]
    public function everySourceOutranksTheDefault(): void
    {
        static::assertTrue(AttributeSource::Option->outranks(AttributeSource::Default));
        static::assertTrue(AttributeSource::Header->outranks(AttributeSource::Default));
        static::assertFalse(AttributeSource::Default->outranks(AttributeSource::Header));
    }

    #[Test]
    public function noSourceOutranksItself(): void
    {
        foreach (AttributeSource::cases() as $source) {
            static::assertFalse($source->outranks($source));
        }
    }

    #[Test]
    public function ordersEveryPairByPrecedence(): void
    {
        foreach (AttributeSource::cases() as $source) {
            foreach (AttributeSource::cases() as $other) {
                static::assertSame(
                    $source->precedence() > $other->precedence(),
                    $source->outranks($other),
                );
            }
        }
    }
}
