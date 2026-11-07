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

namespace Phpiidoc\Diagnostic;

/**
 * A single diagnostic collected while parsing or rendering.
 *
 * A diagnostic is a report, not an exception. Every diagnostic collected during
 * a call is returned on the result, whether or not the call ultimately fails,
 * so a caller can report every defect in one pass instead of fixing them one
 * exception at a time.
 *
 * Severity is not a constructor argument. It is read from the code
 * ({@see ReasonCode::severity()}), so a diagnostic cannot contradict the code
 * it reports.
 *
 * `$location` is optional because a defect may be found before any source
 * position is known, and a document read from a string has no path at all.
 * `$target` carries the include target or attribute name the diagnostic is
 * about, when there is one, because that is the value a caller needs in order
 * to act and it cannot be recovered from the message.
 *
 * @api
 */
final readonly class Diagnostic
{
    public function __construct(
        public ReasonCode $code,
        public string $message,
        public ?SourceLocation $location = null,
        public ?string $target = null,
        public ?string $correctiveAction = null,
    ) {}

    /**
     * The severity this diagnostic inherits from its code.
     */
    public function severity(): Severity
    {
        return $this->code->severity();
    }

    public function isError(): bool
    {
        return Severity::Error === $this->code->severity();
    }

    public function isWarning(): bool
    {
        return Severity::Warning === $this->code->severity();
    }

    /**
     * Whether the diagnostic carries a source location.
     */
    public function hasLocation(): bool
    {
        return null !== $this->location;
    }
}
