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

namespace Phpiidoc\Substitutor;

/**
 * One step of the substitution pipeline.
 *
 * Ruby dispatches on these as symbols, in the order they appear in the list it
 * is given (`lib/asciidoctor/substitutors.rb:92-113`). The order is not fixed by
 * the type; it is fixed by the list, and the named lists are in
 * {@see SubstitutionGroup}.
 *
 * ## Nine members, eight of them requestable
 *
 * {@see self::Highlight} is not a substitution a document can ask for. It is
 * substituted for {@see self::SpecialCharacters} on a source listing when a
 * syntax highlighter is active (`lib/asciidoctor/substitutors.rb:1309-1312`),
 * and it is absent from the set of valid requests
 * (`lib/asciidoctor/substitutors.rb:39-42`). {@see SubstitutorInterface::resolve()}
 * therefore rejects it, and it exists here because the pipeline has a branch for
 * it and a pipeline with a missing case is a pipeline with an unhandled branch.
 *
 * ## What each one does, in one line
 *
 * - specialcharacters escapes `&`, `<` and `>`
 *   (`lib/asciidoctor/substitutors.rb:8-9`, `:94-95`).
 * - quotes turns the quoting markup into inline elements, which is the one
 *   substitution that is not a string rewrite
 *   (`lib/asciidoctor/substitutors.rb:96-97`).
 * - attributes replaces attribute references (`:98-99`).
 * - replacements turns the replacement characters into their characters
 *   (`:100-101`).
 * - macros turns the macro forms into inline elements or link and image
 *   markup (`:102-103`).
 * - post_replacements handles hard line breaks and the `+` continuation
 *   (`:108-109`, `:891-905`).
 * - callouts turns callout marks into callout inline elements
 *   (`:106-107`, `:922-934`).
 * - highlight runs the syntax highlighter (`:104-105`, `:948-975`).
 *
 * @api
 */
enum Substitution: string
{
    case SpecialCharacters = 'specialcharacters';
    case Quotes = 'quotes';
    case Attributes = 'attributes';
    case Replacements = 'replacements';
    case Macros = 'macros';
    case PostReplacements = 'post_replacements';
    case Callouts = 'callouts';
    case Highlight = 'highlight';
}
