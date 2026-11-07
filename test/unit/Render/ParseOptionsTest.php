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

use Phpiidoc\Render\ParseOptions;
use Phpiidoc\SafeMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ParseOptions::class)]
final class ParseOptionsTest extends TestCase
{
    #[Test]
    public function defaultsToTheSettledValues(): void
    {
        $options = new ParseOptions();

        static::assertSame(SafeMode::Safe, $options->safeMode);
        static::assertSame([], $options->attributes);
        static::assertNull($options->baseDir);
        static::assertSame(64, $options->maxIncludeDepth);
        static::assertFalse($options->sourcemap);
    }

    #[Test]
    public function keepsWhatItWasGiven(): void
    {
        $options = new ParseOptions(
            SafeMode::Server,
            ['idprefix' => 'x'],
            '/repo/docs',
            8,
            true,
        );

        static::assertSame(SafeMode::Server, $options->safeMode);
        static::assertSame(['idprefix' => 'x'], $options->attributes);
        static::assertSame('/repo/docs', $options->baseDir);
        static::assertSame(8, $options->maxIncludeDepth);
        static::assertTrue($options->sourcemap);
    }
}
