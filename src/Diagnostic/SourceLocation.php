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
 * Where a diagnostic was raised.
 *
 * A path without a line is a legal location: a document read from a string has
 * no source file, and a defect found while resolving an include is known by
 * path before any line is counted. The line, when present, is one-based,
 * because it is the line as counted by the reader
 * (`lib/asciidoctor/abstract_block.rb:66-69`).
 *
 * @api
 */
final readonly class SourceLocation
{
    public function __construct(
        public string $path,
        public ?int $line = null,
    ) {}

    /**
     * The location rendered as `path:line`, or the path alone when the line is
     * unknown.
     *
     * The format is a convenience for reporting and is not contract.
     */
    public function __toString(): string
    {
        if (null === $this->line) {
            return $this->path;
        }

        return "{$this->path}:{$this->line}";
    }
}
