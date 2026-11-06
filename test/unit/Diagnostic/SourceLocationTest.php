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

use Phpiidoc\Diagnostic\SourceLocation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SourceLocation::class)]
#[CoversMethod(SourceLocation::class, '__toString')]
final class SourceLocationTest extends TestCase
{
    #[Test]
    public function rendersThePathAndLineWhenTheLineIsKnown(): void
    {
        $location = new SourceLocation('/docs/page.adoc', 12);

        static::assertSame('/docs/page.adoc:12', (string) $location);
    }

    #[Test]
    public function rendersThePathAloneWhenTheLineIsUnknown(): void
    {
        $location = new SourceLocation('<string>');

        static::assertNull($location->line);
        static::assertSame('<string>', (string) $location);
    }
}
