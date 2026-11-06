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
     * This node's own content, substituted, as one string.
     *
     * Own content means what the node holds directly, not what its children
     * hold. Which content that is follows from the node's content model, and
     * Ruby branches on it in one place (`lib/asciidoctor/block.rb:108-129`): a
     * simple block joins its lines and substitutes the result, a verbatim block
     * substitutes its lines and then strips leading and trailing blank lines, a
     * raw block substitutes with its own group, and a compound block has no
     * content of its own at all.
     *
     * A compound node therefore returns the empty string. Its children are
     * reached through {@see self::getBlocks()} and the caller joins them. Ruby
     * joins them inside `content` by converting each child
     * (`lib/asciidoctor/abstract_block.rb:85-87`), which is the mechanism the
     * parse phase removes: joining is a rendering decision, it needs a converter,
     * and the substitution that used to happen at the same moment has already
     * happened by the time this value is read.
     *
     * The string is already substituted and must not be substituted again. It is
     * empty for a node with no own content, which is the same answer a compound
     * node gives; a caller that needs to tell those apart asks
     * {@see self::hasBlocks()}.
     */
    public function getContent(): string;
}
