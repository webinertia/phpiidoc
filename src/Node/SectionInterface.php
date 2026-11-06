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

namespace Phpiidoc\Node;

use Override;

/**
 * A section of a document.
 *
 * A section is a block: it holds child blocks and it holds subsections, both in
 * the same child list. Ruby models it as `Section < AbstractBlock`
 * (`lib/asciidoctor/section.rb:23`).
 *
 * A section is the only node kind whose id is guaranteed. A caller that builds
 * links from a document reads {@see self::getId()} and can rely on it being
 * non-empty, because ids are generated from titles by default and the one
 * configuration that would disable generation is refused rather than honoured.
 * Generation is on by default because `sectids` is a default attribute
 * (`lib/asciidoctor.rb:378`), and Ruby documents that generation is disabled by
 * unsetting that attribute (`lib/asciidoctor/section.rb:190`).
 *
 * @api
 */
interface SectionInterface extends BlockInterface
{
    /**
     * The nesting depth of this section, zero-based.
     *
     * Zero for a top-level section, one for its child, and so on. Ruby stores a
     * one-based level, one more than the parent's, or one when there is no
     * section parent (`lib/asciidoctor/section.rb:46-55`), and its html5
     * converter renders a level of zero as `<h1 class="sect0">` and any other
     * level as `<h{level + 1}>` (`lib/asciidoctor/converter/html5.rb:434-439`).
     *
     * phpiidoc exposes the level minus one, so the rendered heading level is
     * `getLevel() + 2`: a top-level section has a level of zero here and is
     * emitted as an `<h2>`, exactly as Ruby emits it. A consumer that compares
     * against heading levels adds two, not one.
     */
    public function getLevel(): int;

    /**
     * The section id. Never `null` and never empty.
     *
     * An explicit id is used verbatim, including its case and punctuation, and
     * is never prefixed, separated or filtered. Ruby takes an explicit id from a
     * block anchor line, from an id in the block attribute list, or from a
     * trailing anchor on the title line
     * (`lib/asciidoctor/parser.rb:1657-1667`, `:1797-1806`, `:2064-2072`), and
     * generates one from the title otherwise
     * (`lib/asciidoctor/section.rb:63-65`, `:198-226`).
     *
     * A duplicate explicit id is kept as written and reported as a warning, not
     * renumbered (`lib/asciidoctor/parser.rb:1668-1670`).
     *
     * This narrows {@see NodeInterface::getId()} from nullable to non-null.
     */
    #[Override]
    public function getId(): string;

    /**
     * Whether this section belongs to a special section, such as an appendix,
     * a preface or a colophon.
     *
     * Ruby propagates the flag from the parent section
     * (`lib/asciidoctor/section.rb:30-31`, `:48-52`).
     */
    public function isSpecial(): bool;
}
