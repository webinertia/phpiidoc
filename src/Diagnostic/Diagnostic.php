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
 * `$sourcePath` and `$line` are optional together and are independent of each
 * other: a defect in a document with no file source has no path, and a defect
 * in a synthesised fragment has no line. `$target` carries the include target
 * or attribute name the diagnostic is about, when there is one.
 *
 * @api
 */
final readonly class Diagnostic
{
    public function __construct(
        public ReasonCode $code,
        public string $message,
        public ?string $sourcePath = null,
        public ?int $line = null,
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
        return $this->code->severity() === Severity::Error;
    }

    public function isWarning(): bool
    {
        return $this->code->severity() === Severity::Warning;
    }

    /**
     * Whether the diagnostic names both a source path and a line.
     */
    public function hasLocation(): bool
    {
        return $this->sourcePath !== null && $this->line !== null;
    }

    /**
     * The source location as `path:line`, or the part of it that is known.
     *
     * Returns an empty string when neither a path nor a line is known. The
     * format is a convenience for reporting and is not contract.
     */
    public function location(): string
    {
        if ($this->sourcePath === null) {
            return $this->line === null ? '' : (string) $this->line;
        }

        return $this->line === null
            ? $this->sourcePath
            : $this->sourcePath . ':' . $this->line;
    }
}
