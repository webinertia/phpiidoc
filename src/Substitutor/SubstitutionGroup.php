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
 * A named list of substitutions.
 *
 * These are the four names a document can use in a `subs` attribute
 * (`lib/asciidoctor/substitutors.rb:21-26`). The order of
 * {@see self::substitutions()} is the order the pipeline runs them in, and it is
 * not alphabetical and not arbitrary: special characters are escaped before
 * quotes are looked for, and macros run after attributes have been replaced, so
 * an attribute whose value contains a macro is expanded as a macro
 * (`lib/asciidoctor/substitutors.rb:14-19`).
 *
 * ## Two lists that are not groups
 *
 * Ruby also names `HEADER_SUBS` and `REFTEXT_SUBS`
 * (`lib/asciidoctor/substitutors.rb:15`, `:18`), but they are not in `SUB_GROUPS`
 * and a document cannot ask for them by name: `subs=header` resolves to the
 * invalid list and is warned about (`lib/asciidoctor/substitutors.rb:1233-1237`).
 * They are used only through the dedicated entry points
 * {@see SubstitutorInterface::applyHeader()} and
 * {@see SubstitutorInterface::applyReftext()}. They are therefore not members of
 * this type, because a type that says "a name a document may use" must not carry
 * names a document may not use.
 *
 * @api
 */
enum SubstitutionGroup: string
{
    /** No substitutions. */
    case None = 'none';

    /** The full inline pipeline. */
    case Normal = 'normal';

    /** Source text: escaping and callouts only. */
    case Verbatim = 'verbatim';

    /** Escaping only. */
    case SpecialChars = 'specialchars';

    /**
     * The substitutions of this group, in pipeline order.
     *
     * @return list<Substitution>
     */
    public function substitutions(): array
    {
        return match ($this) {
            self::None => [],
            self::Normal => [
                Substitution::SpecialCharacters,
                Substitution::Quotes,
                Substitution::Attributes,
                Substitution::Replacements,
                Substitution::Macros,
                Substitution::PostReplacements,
            ],
            self::Verbatim => [
                Substitution::SpecialCharacters,
                Substitution::Callouts,
            ],
            self::SpecialChars => [
                Substitution::SpecialCharacters,
            ],
        };
    }
}
