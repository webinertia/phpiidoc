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

namespace Phpiidoc\Reader;

use Phpiidoc\Diagnostic\DiagnosticCollectorInterface;
use Phpiidoc\Render\ParseOptions;
use Phpiidoc\Render\Source;

/**
 * Builds the reader for one parse.
 *
 * This is the seam where a document stops being a name and becomes lines.
 * {@see Source} deliberately does not read, because a source may be text and
 * because whether a path may be opened at all is a decision that belongs at a
 * named place. Ruby makes the same split: the file is opened at the outermost
 * entry point and read into a string, with `docfile`, `docdir` and `docname`
 * derived from the path as it goes (`lib/asciidoctor/load.rb:57-76`), and the
 * reader is then constructed over that string
 * (`lib/asciidoctor/document.rb:506`).
 *
 * ## What construction decides, and why it cannot be deferred
 *
 * Two settings are read at construction rather than per line.
 *
 * The maximum include depth comes from the `max-include-depth` attribute at the
 * moment the reader is built, and a non-positive value switches include
 * processing off entirely rather than meaning a depth of zero
 * (`lib/asciidoctor/reader.rb:616-622`). Ruby supplies the attribute from the
 * API overrides, defaulting to 64 (`lib/asciidoctor/document.rb:380-381`), which
 * is where {@see ParseOptions::$maxIncludeDepth} comes from.
 *
 * The base directory is fixed at construction because it is both the root that
 * relative includes resolve against and the jail that confines them
 * (`lib/asciidoctor/abstract_node.rb:465-477`). It is fixed once and not
 * recomputed per include: an include resolves relative to the file that named
 * it, but the jail stays the document's base directory
 * (`lib/asciidoctor/reader.rb:1262-1263`).
 *
 * Everything else the reader consults, it consults while reading, because it
 * has to: see {@see ReaderInterface} for why preprocessing cannot run ahead of
 * parsing.
 *
 * ## Safe mode is applied here, once
 *
 * A `Source` that names a path is opened by this factory, and the safe mode is
 * consulted before the open. At `SECURE` and above an include directive is not
 * processed at all (`lib/asciidoctor/reader.rb:1050-1054`), and Ruby's own
 * documentation is explicit that a secure document "disallows the document from
 * attempting to read files" (`lib/asciidoctor/document.rb:163`). A site that
 * wants a document read from disk therefore cannot be at `SECURE`, which is why
 * phpiidoc's default is `SAFE` rather than Ruby's `SECURE`.
 *
 * ## Why this is an interface
 *
 * Because two of the three reasons to replace the reader are real today. A site
 * that serves documents from a store rather than a checkout has no path for
 * {@see Source::file()} to name. A test that exercises include depth,
 * conditionals or safe mode should be able to drive a reader without a parser
 * and without a filesystem, and the factory is what a container substitutes to
 * make that possible. The third reason, a different preprocessor, is not
 * supported and is not why this exists.
 *
 * @api
 */
interface ReaderFactoryInterface
{
    /**
     * Open the source if it is a path and return a reader over it.
     *
     * The returned reader is positioned at its first line and has run no
     * preprocessing yet. Opening the source may fail: a path that cannot be read
     * is reported to `$diagnostics` and the reader is empty rather than the call
     * failing, because a failed include is a diagnostic on the document and not
     * an exception out of the parse. The document source itself is the one
     * exception, because there is no document to carry the diagnostic.
     *
     * The base directory used for resolution and jailing is
     * `$options->baseDir`, falling back to the directory of the source path when
     * that is null and the source is a file. A source with neither is read
     * against the working directory, matching Ruby
     * (`lib/asciidoctor/document.rb:393-399`).
     */
    public function create(
        Source $source,
        ParseOptions $options,
        DiagnosticCollectorInterface $diagnostics,
        IncludeResolverInterface $resolver,
    ): ReaderInterface;
}
