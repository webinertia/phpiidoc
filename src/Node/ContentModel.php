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
 * How a block's content is held, and therefore how it is substituted.
 *
 * Ruby stores this on every block as `content_model` and switches on it in two
 * places that matter: the substitutions a block defaults to
 * (`lib/asciidoctor/substitutors.rb:1287-1315`) and whether its content is
 * children or text (`lib/asciidoctor/block.rb:108-129`).
 *
 * The model is decided where the block is built, not derived from its context.
 * Ruby has a default table keyed by context
 * (`lib/asciidoctor/block.rb:12-24`) but the parser overrides it at the call
 * site, and the same context can arrive with different models: a `quote` block
 * is compound when it is delimited and simple when it is an indented paragraph
 * with a credit line (`lib/asciidoctor/parser.rb:790`, `:802`), and a `literal`
 * is verbatim from the parser (`lib/asciidoctor/parser.rb:761`) even though the
 * default table says nothing about it. There is consequently no sound
 * `forContext` on this type, and there is none.
 *
 * Sections, the document and lists are compound because `AbstractBlock`'s
 * constructor defaults to compound and they never override it
 * (`lib/asciidoctor/abstract_block.rb:39`).
 *
 * @api
 */
enum ContentModel: string
{
    /** Text that is substituted as one string. */
    case Simple = 'simple';

    /** Source text that keeps its line structure and gets verbatim substitutions. */
    case Verbatim = 'verbatim';

    /** Text that is passed through with no substitutions by default. */
    case Raw = 'raw';

    /** A container whose content is its children. */
    case Compound = 'compound';

    /** A node that carries no content at all. */
    case Empty = 'empty';
}
