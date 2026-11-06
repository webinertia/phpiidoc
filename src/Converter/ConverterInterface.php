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

namespace Phpiidoc\Converter;

use Phpiidoc\Node\NodeInterface;

/**
 * Converts one node to one string.
 *
 * This is the node conversion contract and nothing else. It takes a node and a
 * transform name, and it returns the output for that node. It does not parse,
 * it does not hold a document, it does not hold a template engine and it does
 * not know what a page is.
 *
 * The shape is Ruby's, deliberately and without modernisation. Ruby declares
 * `convert node, transform = nil, opts = nil` returning a String
 * (`lib/asciidoctor/converter.rb:61-74`), and its dispatch base class declares
 * `convert node, transform = node.node_name, opts = nil`
 * (`lib/asciidoctor/converter.rb:391-397`). The transform names the conversion
 * to apply and defaults to the node's own node name, which is the context as a
 * string (`lib/asciidoctor/abstract_node.rb:24`, `:36`). Here that default is
 * expressed as a null transform, resolved through
 * {@see NodeInterface::getContext()}.
 *
 * A converter is asked to convert a node, and the node arrives with its content
 * already substituted. The parse phase ran the substitution pipeline; a
 * converter that ran it again would substitute twice, and a second pass over
 * entity text turns `&amp;` into `&amp;amp;`. What a converter still decides at
 * the moment it renders is the layout around that content: whether a child is
 * wrapped, what class it carries, and in what order the pieces appear. Ruby
 * reaches the opposite arrangement because its template converter renders a
 * template with the node as its subject
 * (`lib/asciidoctor/converter/template.rb:98`, `:108`, `:110`) and the template
 * then reaches for `content`, which substitutes and converts the children at
 * that point (`lib/asciidoctor/abstract_block.rb:85-87`). phpiidoc moves the
 * substitution half of that into the parse phase and leaves the layout half
 * here, which is why the contract is still node in, string out.
 *
 * @api
 */
interface ConverterInterface
{
    /**
     * The backend name this converter serves, such as `html5`.
     *
     * Ruby exposes the same reader and assigns it in the constructor
     * (`lib/asciidoctor/converter.rb:48-49`, `:57-59`).
     */
    public function backend(): string;

    /**
     * Convert `$node`, or return the empty string when there is nothing to emit.
     *
     * `$transform` names the conversion to apply. A null transform means the
     * node's own context, which is Ruby's default
     * (`lib/asciidoctor/converter.rb:391`). A transform that differs from the
     * context is how a caller asks for an alternative rendering of the same
     * node, such as the outline form of a document.
     *
     * `$opts` carries conversion hints. Ruby's default is `nil` and its
     * dispatch passes the hash through to the handler when it is given
     * (`lib/asciidoctor/converter.rb:391-392`); an empty array here means the
     * same thing as Ruby's nil, which is no options.
     *
     * The return type is `string`, not `?string`, so a converter cannot decline
     * by returning null. Ruby's dispatch warns and returns nil when no handler
     * matches the transform (`lib/asciidoctor/converter.rb:393-396`); phpiidoc
     * does not reproduce that, because a nil folded into a parent's output
     * becomes a silent gap in the markup. Callers test with
     * {@see self::handles()} first, and calling this with a transform the
     * converter does not handle is a programming error, not a supported path.
     *
     * @param array<string, mixed> $opts
     */
    public function convert(
        NodeInterface $node,
        ?string $transform = null,
        array $opts = [],
    ): string;

    /**
     * Whether this converter can convert the named transform.
     *
     * Ruby declares this on the converter module returning true by default
     * (`lib/asciidoctor/converter.rb:76-84`), and implements it on the dispatch
     * base class by asking whether a handler for that transform exists
     * (`lib/asciidoctor/converter.rb:399-401`). It exists so that a converter
     * that handles only part of a document can be composed with another that
     * handles the rest, without either of them guessing.
     */
    public function handles(string $transform): bool;
}
