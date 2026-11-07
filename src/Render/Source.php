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

namespace Phpiidoc\Render;

/**
 * What to render: a file path, or source text with a synthetic path.
 *
 * The two shapes exist because a caller may hold the source already, for
 * example after fetching it, and still needs a path for diagnostics and for
 * relative include resolution. Ruby keeps the same split: the file entry point
 * opens the file and delegates to the string entry point
 * (`lib/asciidoctor/convert.rb:187-189`).
 *
 * A `Source` records where the source is. It does not read it. Reading is the
 * renderer's job, so a source may be constructed for a path that does not
 * exist and the failure is raised by the call that tried to read it.
 *
 * @api
 */
final readonly class Source
{
    /**
     * @param null|string $contents Source text, or `null` when the source is a
     *                              path that has not been read.
     * @param string      $path     The path recorded for diagnostics and for
     *                              relative include resolution.
     */
    private function __construct(
        private ?string $contents,
        private string $path,
    ) {}

    /**
     * A source that names a file to read.
     *
     * The path is used as given. It is not resolved, not canonicalised and not
     * checked for existence here, because resolution belongs to the resolver
     * that owns the base directory.
     */
    public static function file(string $path): self
    {
        return new self(null, $path);
    }

    /**
     * A source that carries its own text.
     *
     * The synthetic path is what diagnostics name and what relative includes
     * resolve against when no base directory is supplied. Ruby carries the same
     * convention for a source that has no file, using the placeholder
     * `'<stdin>'` (`lib/asciidoctor/reader.rb:50`, `:62`); phpiidoc uses
     * `'<string>'` because the source is text rather than a standard input
     * stream. The value is a label and nothing else, so no code parses it.
     */
    public static function string(string $contents, string $path = '<string>'): self
    {
        return new self($contents, $path);
    }

    /**
     * The path recorded for this source.
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * The source text when it was supplied, or `null` when the path has not
     * been read yet.
     */
    public function contents(): ?string
    {
        return $this->contents;
    }

    /**
     * Whether this source names a file to read.
     */
    public function isFile(): bool
    {
        return null === $this->contents;
    }
}
