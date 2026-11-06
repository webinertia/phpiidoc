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

use Phpiidoc\SafeMode;

/**
 * The keys that govern reading and parsing, before any node is rendered.
 *
 * These are separated from {@see RenderOptions} because a different phase
 * consumes them. A parse entry point accepts this type alone, so it cannot be
 * handed a render-phase key and silently ignore it. The split is structural
 * rather than documentary: no key here is read by rendering, and no render key
 * is read by parsing.
 *
 * `$safeMode` defaults to {@see SafeMode::Safe}, which is not Ruby's default.
 * Ruby resolves the API safe mode to `SECURE` when the caller supplies none
 * (`lib/asciidoctor/document.rb:319-327`), and at `SECURE` and above Ruby
 * refuses to read any file, so an include that is not a URI cannot be inlined
 * at all. phpiidoc keeps the jail and drops the refusal, which is what makes
 * `SAFE` the usable default. Safe mode governs which reason code applies, never
 * the severity of an include diagnostic.
 *
 * `$attributes` are the caller's attribute overrides. They win over anything
 * the document header declares, and a name they set is locked against a header
 * write. Ruby expresses the same rule by keeping the render call's attributes in
 * a separate overrides map and returning early from `set_attribute` when the
 * name is in it, noting that a direct assignment "effectively makes the
 * attribute a constant" (`lib/asciidoctor/document.rb:296-315`, `:876-877`,
 * `:919-926`). This is precedence, not a whitelist: a name this array does not
 * carry is still settable by the header.
 *
 * `$baseDir` is two things at once, and they are the same directory. It is the
 * root that relative include targets resolve against, and it is the jail that
 * confines every read. Ruby joins the two as well, defaulting both the start
 * directory and the jail to the document's base directory once safe mode
 * reaches `SAFE` (`lib/asciidoctor/abstract_node.rb:465-477`, jail at
 * `:472-475`). A target may leave the directory of the page that named it and
 * remain inside the jail; that is permitted, and it is why the root is a key of
 * its own rather than being derived from the source path.
 *
 * `$maxIncludeDepth` defaults to 64, matching Ruby's fallback when the
 * `max-include-depth` attribute is unset (`lib/asciidoctor/reader.rb:616`).
 * `0` disables include processing, which Ruby treats as includes being switched
 * off rather than as a depth of zero.
 *
 * `$sourcemap` retains block source locations on the document model. Diagnostics
 * carry a source location whether or not it is set, so this governs the model
 * only, matching Ruby's `sourcemap` option (`lib/asciidoctor/document.rb:330`).
 *
 * @api
 */
final readonly class ParseOptions
{
    /**
     * @param SafeMode                        $safeMode        Confinement for reads and include processing.
     * @param array<string, null|bool|string> $attributes      Attribute overrides supplied by the caller.
     * @param null|string                     $baseDir         Root for relative resolution and the read jail.
     * @param int                             $maxIncludeDepth Include nesting limit. `0` disables includes.
     * @param bool                            $sourcemap       Retain block source locations on the model.
     */
    public function __construct(
        public SafeMode $safeMode = SafeMode::Safe,
        public array $attributes = [],
        public ?string $baseDir = null,
        public int $maxIncludeDepth = 64,
        public bool $sourcemap = false,
    ) {}
}
