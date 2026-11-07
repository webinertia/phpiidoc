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

use Phpiidoc\Diagnostic\Diagnostic;
use Phpiidoc\Diagnostic\ReasonCode;
use Phpiidoc\Diagnostic\Severity;
use Phpiidoc\Diagnostic\SourceLocation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Diagnostic::class)]
#[CoversMethod(Diagnostic::class, 'severity')]
#[CoversMethod(Diagnostic::class, 'isError')]
#[CoversMethod(Diagnostic::class, 'isWarning')]
#[CoversMethod(Diagnostic::class, 'hasLocation')]
final class DiagnosticTest extends TestCase
{
    #[Test]
    public function inheritsSeverityFromItsCode(): void
    {
        $error   = new Diagnostic(ReasonCode::IncludeTargetNotFound, 'missing');
        $warning = new Diagnostic(ReasonCode::DuplicateSectionId, 'duplicate');

        static::assertSame(Severity::Error, $error->severity());
        static::assertSame(Severity::Warning, $warning->severity());
    }

    #[Test]
    public function reportsWhetherItIsAnErrorOrAWarning(): void
    {
        $error   = new Diagnostic(ReasonCode::IncludeTargetNotFound, 'missing');
        $warning = new Diagnostic(ReasonCode::DuplicateSectionId, 'duplicate');

        static::assertTrue($error->isError());
        static::assertFalse($error->isWarning());
        static::assertFalse($warning->isError());
        static::assertTrue($warning->isWarning());
    }

    #[Test]
    public function reportsWhetherItCarriesASourceLocation(): void
    {
        $located = new Diagnostic(
            ReasonCode::IncludeTargetNotFound,
            'missing',
            new SourceLocation('/docs/page.adoc', 12),
        );
        $unlocated = new Diagnostic(ReasonCode::IncludeTargetNotFound, 'missing');

        static::assertTrue($located->hasLocation());
        static::assertFalse($unlocated->hasLocation());
    }

    #[Test]
    public function carriesTheTargetAndCorrectiveActionItWasGiven(): void
    {
        $diagnostic = new Diagnostic(
            ReasonCode::IncludeTargetEscapesBaseDir,
            'target escapes the base directory',
            new SourceLocation('/docs/page.adoc', 3),
            '../outside.adoc',
            'Move the target inside the base directory.',
        );

        static::assertSame('../outside.adoc', $diagnostic->target);
        static::assertSame('Move the target inside the base directory.', $diagnostic->correctiveAction);
    }

    #[Test]
    public function defaultsTheOptionalMembersToNull(): void
    {
        $diagnostic = new Diagnostic(ReasonCode::UnknownBlockContext, 'unknown');

        static::assertNull($diagnostic->location);
        static::assertNull($diagnostic->target);
        static::assertNull($diagnostic->correctiveAction);
    }
}
