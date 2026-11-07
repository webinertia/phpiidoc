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

use Phpiidoc\Diagnostic\ReasonCode;
use Phpiidoc\Diagnostic\Severity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReasonCode::class)]
#[CoversMethod(ReasonCode::class, 'severity')]
#[CoversMethod(ReasonCode::class, 'isIncludeCode')]
final class ReasonCodeTest extends TestCase
{
    #[Test]
    public function pinsTheCodeStrings(): void
    {
        static::assertSame('include_target_not_found', ReasonCode::IncludeTargetNotFound->value);
        static::assertSame('include_disabled_by_safe_mode', ReasonCode::IncludeDisabledBySafeMode->value);
        static::assertSame('include_depth_exceeded', ReasonCode::IncludeDepthExceeded->value);
        static::assertSame('include_disabled_by_depth_zero', ReasonCode::IncludeDisabledByDepthZero->value);
        static::assertSame('include_uri_not_allowed', ReasonCode::IncludeUriNotAllowed->value);
        static::assertSame('include_target_escapes_base_dir', ReasonCode::IncludeTargetEscapesBaseDir->value);
        static::assertSame('section_missing_id', ReasonCode::SectionMissingId->value);
        static::assertSame('unknown_block_context', ReasonCode::UnknownBlockContext->value);
        static::assertSame('unsupported_construct', ReasonCode::UnsupportedConstruct->value);
        static::assertSame('duplicate_section_id', ReasonCode::DuplicateSectionId->value);
        static::assertSame('unresolved_attribute_reference', ReasonCode::UnresolvedAttributeReference->value);
        static::assertSame('unsupported_attribute_ignored', ReasonCode::UnsupportedAttributeIgnored->value);
    }

    #[Test]
    public function warnsOnlyForTheThreeRecoverableCodes(): void
    {
        static::assertSame(Severity::Warning, ReasonCode::DuplicateSectionId->severity());
        static::assertSame(Severity::Warning, ReasonCode::UnresolvedAttributeReference->severity());
        static::assertSame(Severity::Warning, ReasonCode::UnsupportedAttributeIgnored->severity());
    }

    #[Test]
    public function assignsErrorToEveryOtherCode(): void
    {
        static::assertSame(Severity::Error, ReasonCode::IncludeTargetNotFound->severity());
        static::assertSame(Severity::Error, ReasonCode::IncludeDisabledBySafeMode->severity());
        static::assertSame(Severity::Error, ReasonCode::IncludeDepthExceeded->severity());
        static::assertSame(Severity::Error, ReasonCode::IncludeDisabledByDepthZero->severity());
        static::assertSame(Severity::Error, ReasonCode::IncludeUriNotAllowed->severity());
        static::assertSame(Severity::Error, ReasonCode::IncludeTargetEscapesBaseDir->severity());
        static::assertSame(Severity::Error, ReasonCode::SectionMissingId->severity());
        static::assertSame(Severity::Error, ReasonCode::UnknownBlockContext->severity());
        static::assertSame(Severity::Error, ReasonCode::UnsupportedConstruct->severity());
    }

    #[Test]
    public function treatsEveryIncludeCodeAsAnError(): void
    {
        $includes = [
            ReasonCode::IncludeTargetNotFound,
            ReasonCode::IncludeDisabledBySafeMode,
            ReasonCode::IncludeDepthExceeded,
            ReasonCode::IncludeDisabledByDepthZero,
            ReasonCode::IncludeUriNotAllowed,
            ReasonCode::IncludeTargetEscapesBaseDir,
        ];

        static::assertCount(6, $includes);

        foreach ($includes as $code) {
            static::assertTrue($code->isIncludeCode());
            static::assertSame(Severity::Error, $code->severity());
        }
    }

    #[Test]
    public function identifiesTheSixIncludeCodesAndNoOthers(): void
    {
        static::assertTrue(ReasonCode::IncludeTargetNotFound->isIncludeCode());
        static::assertTrue(ReasonCode::IncludeDisabledBySafeMode->isIncludeCode());
        static::assertTrue(ReasonCode::IncludeDepthExceeded->isIncludeCode());
        static::assertTrue(ReasonCode::IncludeDisabledByDepthZero->isIncludeCode());
        static::assertTrue(ReasonCode::IncludeUriNotAllowed->isIncludeCode());
        static::assertTrue(ReasonCode::IncludeTargetEscapesBaseDir->isIncludeCode());
        static::assertFalse(ReasonCode::SectionMissingId->isIncludeCode());
        static::assertFalse(ReasonCode::UnknownBlockContext->isIncludeCode());
        static::assertFalse(ReasonCode::UnsupportedConstruct->isIncludeCode());
        static::assertFalse(ReasonCode::DuplicateSectionId->isIncludeCode());
        static::assertFalse(ReasonCode::UnresolvedAttributeReference->isIncludeCode());
        static::assertFalse(ReasonCode::UnsupportedAttributeIgnored->isIncludeCode());
    }
}
