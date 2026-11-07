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
 * The state that every node of a parsed document exposes.
 *
 * A node is a section, a block or an inline element. The three are distinguished
 * by the `$context` property, which holds the AsciiDoc context token as a
 * lowercase string: `document`, `section`, `paragraph`, `listing`, `literal`,
 * `pass`, `admonition`, `example`, `sidebar`, `open`, `quote`, `verse`, `ulist`,
 * `olist`, `dlist`, `table`, `image`, `toc`, `floating_title`, `page_break`,
 * `thematic_break`, and the `inline_*` forms for inline elements.
 *
 * Ruby keeps the same four members on every node: the attribute hash, the
 * context, the id and the node name, all on `AbstractNode`
 * (`lib/asciidoctor/abstract_node.rb:12-24`). The `$context` property mirrors
 * Ruby's `context` Symbol and its `node_name` String, the latter assigned from
 * the context in the constructor (`:36`).
 *
 * Source position is a widening of the Ruby surface. Ruby tracks it on blocks
 * only, through `source_location` (`lib/asciidoctor/abstract_block.rb:29`) and
 * exposes it as `file` and `lineno` (`:62-69`). phpiidoc declares the two
 * accessors here so a caller that has a node in hand does not have to branch on
 * its kind, and returns `null` for nodes that carry no position.
 *
 * @api
 */
interface NodeInterface
{
    /**
     * The node id, or `null` when the node has none.
     *
     * Ruby's `id` is a plain get/set accessor that defaults to `nil`
     * (`lib/asciidoctor/abstract_node.rb:21`, `:42`). Blocks may or may not have
     * an id; only {@see SectionInterface} guarantees one.
     */
    public function getId(): ?string;

    /**
     * The node title, or `null` when the node has none.
     *
     * A block title is the source title with title substitutions applied
     * (`lib/asciidoctor/abstract_block.rb:288-291`). A section title is the same
     * accessor. Inline elements have text, not a title
     * (`lib/asciidoctor/inline.rb:7`), so they return `null`.
     *
     * The document title is not this accessor; see
     * {@see DocumentInterface::getDocTitle()}.
     */
    public function getTitle(): ?string;

    /**
     * The AsciiDoc context token for this node, lowercased.
     *
     * Ruby's `context` is a Symbol and `node_name` is the same value as a String
     * (`lib/asciidoctor/abstract_node.rb:15`, `:24`, `:36`). phpiidoc exposes the
     * string form, so `'section'` and not `:section`.
     *
     * A concrete node supplies the property with the context token as the
     * initializer:
     *
     *     public private(set) string $context = 'section';
     *
     * The initializer makes the value effectively read-only: the declaring class
     * owns it, and an assignment from outside its scope is an error. Because the
     * declaration here is abstract, a class that omits the property does not
     * load at all, which is why the token is declared on the interface rather
     * than inherited as a default from a base class.
     */
    public string $context { get; }

    /**
     * The source file this node came from, or `null` when it is unknown.
     *
     * Ruby returns `nil` here when source tracking is off, which is the default
     * and is controlled by the `sourcemap` option
     * (`lib/asciidoctor/abstract_block.rb:27-29`, `:62-64`).
     */
    public function getSourcePath(): ?string;

    /**
     * The one-based source line this node started on, or `null` when unknown.
     *
     * One-based because Ruby reports the line as counted by the reader
     * (`lib/asciidoctor/abstract_block.rb:66-69`).
     */
    public function getSourceLine(): ?int;

    /**
     * The attributes set on this node itself, keyed by name.
     *
     * This is the node's own attribute hash, not the document's resolved set
     * (`lib/asciidoctor/abstract_node.rb:12`, `:38`). Attribute precedence
     * between a render call and a document header is a document-level concern
     * and is not decided here.
     *
     * @return array<string, mixed>
     */
    public function getAttributes(): array;

    /**
     * The named attribute on this node, or `$default` when it is not set.
     */
    public function getAttribute(string $name, mixed $default = null): mixed;

    /**
     * Whether the named attribute is set on this node.
     *
     * An attribute set to the empty string is set, which is how AsciiDoc
     * expresses a boolean attribute in its default on state
     * (`lib/asciidoctor/abstract_block.rb:296-298`).
     */
    public function hasAttribute(string $name): bool;
}
