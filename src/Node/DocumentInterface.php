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

use Phpiidoc\Diagnostic\DiagnosticsInterface;

/**
 * A parsed document.
 *
 * The document is the root of the node tree and it is itself a block: its
 * children are the preamble, the sections and any trailing blocks. Ruby models
 * it as `Document < AbstractBlock` and makes the document node its own document
 * (`lib/asciidoctor/document.rb:1-20`, `lib/asciidoctor/abstract_node.rb:31-32`).
 *
 * A document is handed to a caller by the render entry point. It is the only
 * node a caller is given directly; every other node is reached by walking
 * {@see BlockInterface::getBlocks()}.
 *
 * @api
 */
interface DocumentInterface extends BlockInterface
{
    /**
     * The resolved document title, or `null` when the document has none.
     *
     * The title comes from the `title` attribute, which the document header
     * sets, and falls back to the first section's title. Ruby resolves it that
     * way in `doctitle` (`lib/asciidoctor/document.rb:735-742`).
     *
     * This is not {@see NodeInterface::getTitle()}, which returns the block
     * title of a node. For a document the two differ: the block title is unset
     * while the document title is resolved from the header or the first section.
     */
    public function getDocTitle(): ?string;

    /**
     * The diagnostics raised while reading and parsing this document.
     *
     * Never `null`. A document that parsed without incident yields a collection
     * with no entries, which is different from a collection that could not be
     * produced.
     */
    public function getDiagnostics(): DiagnosticsInterface;
}
