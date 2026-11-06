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

/**
 * Turns a document-controlled include target into an absolute path, and decides
 * whether that path is inside the jail.
 *
 * Two jobs, and the split between them is the point of this interface.
 * {@see self::resolve()} does arithmetic. {@see self::isContained()} does
 * containment. Neither does policy, neither reports a diagnostic, and neither
 * opens anything.
 *
 * ## Why the arithmetic and the containment are not one method
 *
 * Ruby does both at once, in a resolver that takes a jail and quietly re-roots
 * an escaping path inside it rather than failing: `recover` defaults to true
 * (`lib/asciidoctor/path_resolver.rb:384-388`) and the include reader never
 * turns it off (`lib/asciidoctor/reader.rb:1263`). The escape is then
 * indistinguishable from a missing file, and Ruby reports it as a missing file
 * (`lib/asciidoctor/reader.rb:1270-1271`).
 *
 * phpiidoc has to keep the two apart, because they are two different defects
 * with two different reason codes and the codes are contract
 * ({@see \Phpiidoc\Diagnostic\ReasonCode}). So the arithmetic is honest: it
 * reports the path the target actually names, including a path outside the
 * jail. The containment test is a separate question, asked separately, and the
 * caller decides which code to report.
 *
 * ## The textual test is not sufficient, and this is why the interface exists
 *
 * Counting `..` segments and rejecting an absolute target is a purely textual
 * operation. Ruby's resolver does exactly that and never touches the
 * filesystem (`lib/asciidoctor/path_resolver.rb:373-486`), and so does the
 * parent segment collapse here. A symlink inside the repository that points
 * outside it defeats every one of those rules: the target has no `..`, it is
 * not absolute, and it normalises to a path that is textually inside the jail.
 * No amount of string arithmetic sees the link.
 *
 * {@see self::isContained()} therefore also resolves the real path of both
 * sides and compares them, and it treats a symlinked component as a component
 * to be resolved rather than as a name. This closes the hole.
 *
 * ## Why the check lives here rather than in the site layer
 *
 * The alternative was to make each site enumerate its documents and refuse any
 * whose real path leaves the repository, on the grounds that enumeration is
 * where the site already knows what it owns. That does not hold: the render
 * entry point accepts a `Source` and a base directory, and a site that renders
 * a document it never enumerated, or that is handed a path by a route
 * parameter, never passes through enumeration at all. The reader is the only
 * component in the whole pipeline that turns a document-controlled string into
 * a path that is about to be opened, so the reader's resolver is the only place
 * the check cannot be bypassed.
 *
 * ## What this interface deliberately does not do
 *
 * It does not read the target, does not test whether the target is a file
 * rather than a directory, and does not decide what to do about either. It does
 * not know about safe mode: an include disabled by safe mode never reaches
 * resolution, because the directive is refused before a target is computed
 * (`lib/asciidoctor/reader.rb:1050-1054`). It does not know about URIs, which
 * are a separate target type with a separate gate
 * (`lib/asciidoctor/reader.rb:1251-1260`).
 *
 * @api
 */
interface IncludeResolverInterface
{
    /**
     * The absolute, normalised path the target names.
     *
     * An absolute target is returned normalised. A relative target is joined to
     * `$fromDir` and then normalised. Normalising means collapsing `.`
     * segments, collapsing `..` against the segment before it, and using one
     * separator. A `..` that would climb above the root is not an error here;
     * it produces a path the containment test will reject
     * (`lib/asciidoctor/path_resolver.rb:431-486`).
     *
     * The include target is resolved against the directory of the file that
     * contains the directive, not against the base directory. That is what
     * makes a relative include inside an included file resolve relative to that
     * file (`lib/asciidoctor/reader.rb:1262-1263`).
     *
     * The result is not checked for existence, and no diagnostic is raised. A
     * path that escapes the jail is returned as the path it names.
     *
     * @param string $target  The include target, already attribute-substituted
     *                        by the reader (`lib/asciidoctor/reader.rb:1027-1030`).
     * @param string $fromDir The absolute directory of the file containing the
     *                        directive.
     */
    public function resolve(string $target, string $fromDir): string;

    /**
     * Whether the path is inside the jail, with symlinks resolved.
     *
     * True when the real path of `$path` is `$baseDir` itself or descends from
     * it, compared by path segment rather than by string prefix, so that
     * `/srv/docs-private` is not inside `/srv/docs`.
     *
     * When `$path` does not exist, the test walks up to the nearest ancestor
     * that does, resolves that, and appends the segments that are left. The
     * result is the same answer a creation would produce, which is what makes
     * the test usable before the read is attempted: the escape is reported
     * instead of the missing file.
     *
     * `$baseDir` must be absolute and must exist. A base directory that is
     * itself reached through a symlink is resolved before comparison, so a site
     * may be configured with either the link or its target.
     */
    public function isContained(string $path, string $baseDir): bool;
}
