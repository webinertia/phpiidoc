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

namespace PhpiidocTest\Render;

use Phpiidoc\Render\Source;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Source::class)]
#[CoversMethod(Source::class, 'file')]
#[CoversMethod(Source::class, 'string')]
#[CoversMethod(Source::class, 'path')]
#[CoversMethod(Source::class, 'contents')]
#[CoversMethod(Source::class, 'isFile')]
final class SourceTest extends TestCase
{
    #[Test]
    public function aFileSourceRecordsThePathAndCarriesNoText(): void
    {
        $source = Source::file('/docs/page.adoc');

        static::assertTrue($source->isFile());
        static::assertSame('/docs/page.adoc', $source->path());
        static::assertNull($source->contents());
    }

    #[Test]
    public function aStringSourceCarriesItsText(): void
    {
        $source = Source::string('= Title');

        static::assertFalse($source->isFile());
        static::assertSame('= Title', $source->contents());
    }

    #[Test]
    public function aStringSourceWithoutAPathIsLabelledAsAString(): void
    {
        static::assertSame('<string>', Source::string('= Title')->path());
    }

    #[Test]
    public function aStringSourceKeepsThePathItWasGiven(): void
    {
        static::assertSame('/docs/page.adoc', Source::string('= Title', '/docs/page.adoc')->path());
    }

    #[Test]
    public function aStringSourceOfNothingIsStillNotAFile(): void
    {
        $source = Source::string('');

        static::assertFalse($source->isFile());
        static::assertSame('', $source->contents());
    }
}
