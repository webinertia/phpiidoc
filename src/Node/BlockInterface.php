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
 * A node that can hold child nodes.
 *
 * Ruby models this as `AbstractBlock`, which holds the child array, the content
 * model and the converted content of those children
 * (`lib/asciidoctor/abstract_block.rb:6`, `:17`, `:85-87`).
 *
 * @api
 */
interface BlockInterface extends NodeInterface
{
    /**
     * The direct children of this node, in source order.
     *
     * Direct children only, never flattened. Ruby's `blocks` is the child array
     * itself (`lib/asciidoctor/abstract_block.rb:6`, `:40`). Recursing this
     * accessor yields depth-first document order, which is the walk a table of
     * contents builder needs: a section's children include its subsections, and
     * a subsection's own children are reached from there.
     *
     * Returns an empty list, never `null`.
     *
     * @return list<NodeInterface>
     */
    public function getBlocks(): array;

    /**
     * Whether this node has at least one child.
     *
     * Ruby spells this `blocks?` (`lib/asciidoctor/abstract_block.rb:128-130`).
     */
    public function hasBlocks(): bool;

    /**
     * The converted output of this node's children, joined into one string.
     *
     * This is the value a node template reads when it places its children. Ruby
     * converts each child and joins the results with a newline
     * (`lib/asciidoctor/abstract_block.rb:85-87`), and the child conversion is
     * what selects the converter for the child's own context.
     *
     * For a node with no children, returns the empty string.
     */
    public function getContent(): string;
}
