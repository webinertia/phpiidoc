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

use Phpiidoc\Node\ContentModel;

/**
 * The substitution pipeline.
 *
 * This is stage S7 of the parse phase and it is the largest single stage. Ruby's
 * `Substitutors` module is 1553 lines (`lib/asciidoctor/substitutors.rb`), of
 * which `sub_macros` alone is 594 (`:297-890`). It is contracted separately from
 * the parser because it is separately testable: a test can substitute a string
 * and assert on the result without a document, a reader or a node tree.
 *
 * ## Text in, text out
 *
 * {@see self::apply()} takes a string and returns a string
 * (`lib/asciidoctor/substitutors.rb:76-124`). It does not return a tree, and
 * Ruby has no inline tree: inline markup becomes an inline element which is
 * converted on the spot and folded back into the string
 * (`lib/asciidoctor/substitutors.rb:1420-1449`). The parse phase's prepared
 * document therefore holds substituted text, not unresolved markup, which is the
 * decision recorded as D5 in the plan.
 *
 * ## One substitutor per parse, and it is stateful
 *
 * Passthroughs are lifted out of the text before the pipeline runs and put back
 * after, because a passthrough's content must survive the other substitutions
 * untouched. The lifted text is held on the substitutor between the two ends of
 * the outermost call, and the outermost call is the one that clears it
 * (`lib/asciidoctor/substitutors.rb:83-90`, `:115-121`). A substitutor instance
 * is therefore not reentrant and not shareable: one instance per parse, used from
 * one place at a time.
 *
 * ## The substitutions a node gets are decided by the parser, not by the node
 *
 * {@see self::commit()} exists because the choice depends on the content model
 * and the node's own `subs` attribute, both of which the parser has in hand when
 * it builds the node (`lib/asciidoctor/substitutors.rb:1287-1315`). The result is
 * carried on the node and passed back into {@see self::apply()}. The substitutor
 * does not read it from the node, so a caller cannot substitute a node's text
 * with a different set than the one committed to it by accident.
 *
 * @api
 */
interface SubstitutorInterface
{
    /**
     * Apply substitutions to one string.
     *
     * A null `$substitutions` means {@see SubstitutionGroup::Normal}, which is
     * Ruby's default argument (`lib/asciidoctor/substitutors.rb:76`). An empty
     * list means no substitutions and returns the text unchanged, which is a
     * different thing from null (`:77`).
     *
     * Order is the order of `$substitutions` and nothing else (`:92-113`).
     *
     * @param list<Substitution>|null $substitutions
     */
    public function apply(string $text, ?array $substitutions = null): string;

    /**
     * Apply substitutions to a list of lines, returning a list of lines.
     *
     * Ruby accepts a String or an Array and returns the same shape it was given,
     * joining with a line feed and splitting with a limit of -1 so that trailing
     * empty lines survive (`lib/asciidoctor/substitutors.rb:79-81`, `:123`).
     * Verbatim blocks take this path so that callout marks, which are anchored to
     * a line, are matched per line (`lib/asciidoctor/block.rb:114-124`).
     *
     * @param list<string> $lines
     * @param list<Substitution>|null $substitutions
     *
     * @return list<string>
     */
    public function applyLines(array $lines, ?array $substitutions = null): array;

    /**
     * Apply the header substitution set.
     *
     * Escaping and attribute references, and nothing else
     * (`lib/asciidoctor/substitutors.rb:15`, `:142-144`). This set is not a
     * group a document may name, so it has its own entry point; see
     * {@see SubstitutionGroup}.
     */
    public function applyHeader(string $text): string;

    /**
     * Apply the reference-text substitution set.
     *
     * Escaping, quotes and replacements, without macros or attribute references
     * (`lib/asciidoctor/substitutors.rb:18`, `:158-160`). This is the text an xref
     * renders when it has no link text of its own, and it is computed once when
     * the anchor is registered rather than at each reference.
     */
    public function applyReftext(string $text): string;

    /**
     * Resolve a `subs` value into the substitutions it names.
     *
     * Handles the comma-separated list, the `+` and `-` modifiers that add to or
     * remove from the default set, and the single-letter hints that stand for one
     * substitution or one group (`lib/asciidoctor/substitutors.rb:1179-1239`).
     * Names that are not valid for the given position are dropped and reported,
     * not applied (`:1233-1237`).
     *
     * `$defaults` is the set the modifiers apply to, and is null when the value
     * must stand on its own (`:1217`).
     *
     * @param list<Substitution>|null $defaults
     *
     * @return list<Substitution>
     */
    public function resolve(string $spec, ?array $defaults = null): array;

    /**
     * Choose the substitutions a block gets.
     *
     * The default comes from the content model, except for a verse, which is
     * verbatim by content model but substituted as normal text, and a stem, which
     * is raw but still escaped (`lib/asciidoctor/substitutors.rb:1289-1299`). A
     * `subs` attribute on the block overrides the default entirely, resolved
     * against it (`:1302-1306`).
     *
     * `$defaults` overrides the content-model default before the attribute is
     * consulted, which is how a block created with an explicit substitution list
     * carries it (`lib/asciidoctor/block.rb:64-66`,
     * `lib/asciidoctor/parser.rb:421`).
     *
     * @param array<string, mixed> $attributes the block's own attributes
     * @param list<Substitution>|null $defaults
     *
     * @return list<Substitution>
     */
    public function commit(
        ContentModel $model,
        string $context,
        array $attributes,
        ?array $defaults = null,
    ): array;
}
