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

/**
 * One item of a list.
 *
 * Ruby's `ListItem` is an `AbstractBlock`, so it has children, an id, a title and
 * an attribute hash like any other block, and it adds a text of its own and a
 * marker (`lib/asciidoctor/list.rb:46-62`). The text is substituted when it is
 * read, not when it is set (`lib/asciidoctor/list.rb:76-79`), which under the
 * parse phase's decision D5 means the text here is already substituted.
 *
 * ## Why this is separate from {@see BlockInterface}
 *
 * Because an item has two contents and a block has one. An item's text is its
 * first paragraph; an item's children are the blocks nested under it. A
 * converter renders both, in that order, and it renders the text even when there
 * are no children (`lib/asciidoctor/converter/html5.rb:1007-1013`). Folding the
 * text into `getContent()` would make an item with nested blocks ambiguous, so
 * {@see BlockInterface::getContent()} returns the empty string for an item and
 * the text has its own accessor.
 *
 * ## Description lists are pairs
 *
 * A description list item is a term list and an optional description
 * (`lib/asciidoctor/list.rb:44-45`). Both the terms and the description are
 * themselves `ListItem`s in Ruby, so they are `ListItemInterface` here, and the
 * converter walks terms first and then the description
 * (`lib/asciidoctor/converter/html5.rb:574-590`, `:596-604`). For every other
 * list type {@see self::getTerms()} is empty and {@see self::getDescription()}
 * is null.
 *
 * ## Not contracted in this pass
 *
 * The item marker. Ruby stores it (`lib/asciidoctor/list.rb:51`), the parser
 * assigns it (`lib/asciidoctor/parser.rb:1321`, `:1349`, `:1359`), and no
 * converter reads it. It is extension surface, and this pass does not contract
 * extension surface.
 *
 * @api
 */
interface ListItemInterface extends BlockInterface
{
    /**
     * The item's own text, substituted.
     *
     * Empty, never null, when the item has no text. Ruby distinguishes null from
     * empty here only because a description-only item has no text at all
     * (`lib/asciidoctor/list.rb:77`); the distinction is carried by
     * {@see self::hasText()}.
     */
    public function getText(): string;

    /**
     * Whether the item has text.
     *
     * Ruby's `text?` (`lib/asciidoctor/list.rb:64-68`). A converter asks this
     * before emitting the item's text element
     * (`lib/asciidoctor/converter/html5.rb:586`, `:1007`).
     */
    public function hasText(): bool;

    /**
     * The terms of a description list item, in source order.
     *
     * Empty for every other list type. More than one term per item is legal and
     * each is rendered (`lib/asciidoctor/converter/html5.rb:597-599`).
     *
     * @return list<ListItemInterface>
     */
    public function getTerms(): array;

    /**
     * The description of a description list item, or null when it has none.
     *
     * Null for every other list type. A description with no text but with
     * children is legal, and the converter renders the children
     * (`lib/asciidoctor/converter/html5.rb:585-588`).
     */
    public function getDescription(): ?self;
}
