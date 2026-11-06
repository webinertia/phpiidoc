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

use Phpiidoc\Attribute\MutableAttributesInterface;
use Phpiidoc\Converter\ConverterInterface;
use Phpiidoc\Node\NodeInterface;

/**
 * What the substitutor is allowed to know about the document it is substituting
 * into.
 *
 * The substitutor is mixed into blocks and sections in Ruby, which means it can
 * reach `@document` and everything behind it
 * (`lib/asciidoctor/substitutors.rb:4-7`). That single back-reference is what
 * makes the substitution module impossible to test without a document, and it is
 * the reason a PHP port that copies it ends up with a substitutor that needs the
 * whole parser. This interface is the whole of the reach, named.
 *
 * It is deliberately small. Every method here corresponds to a call the
 * substitutor actually makes, and each one is cited at the method. Nothing is
 * here because a future feature might want it.
 *
 * ## The subject is separate from the document
 *
 * `attr` and `option?` inside the substitutor are asked of the *node* being
 * substituted, and only fall back to the document when the node has no value for
 * the name and the caller passed a fallback name
 * (`lib/asciidoctor/abstract_node.rb:88-90`, `:107-113`). That two-step lookup
 * is why {@see self::getSubject()} and {@see self::getAttributes()} are both
 * here: a substitutor that only had the document would resolve `line-comment`
 * and `subs` from the wrong place.
 *
 * @api
 */
interface SubstitutionContextInterface
{
    /**
     * The node whose text is being substituted.
     *
     * Its own attributes are the first place `attr` and `option?` look
     * (`lib/asciidoctor/abstract_node.rb:88-90`), and it is the parent the
     * inline elements produced by the pipeline are attached to
     * (`lib/asciidoctor/inline.rb:7-25`).
     */
    public function getSubject(): NodeInterface;

    /**
     * The document's attribute store.
     *
     * Writable, not read-only, because substitution writes to it: a
     * `{set:name:value}` reference assigns an attribute
     * (`lib/asciidoctor/substitutors.rb:221-228`), and `{counter:name}` takes the
     * next number in a sequence (`:229-234`). Both are attribute references that
     * appear in text and are resolved while substituting that text, so a
     * substitutor holding only a read view could not implement them.
     */
    public function getAttributes(): MutableAttributesInterface;

    /**
     * The converter for the backend this document is being prepared for.
     *
     * The substitutor needs it because the quotes and macros substitutions do
     * not produce strings, they produce inline elements and convert them
     * immediately: `Inline.new(self, :break, ...).convert` in
     * `lib/asciidoctor/substitutors.rb:898`, `:901`, and the same pattern in
     * `sub_quotes` and `sub_macros`. That conversion is the seam described in
     * the parse-phase reasoning record: the pipeline is where text stops being
     * text, and it needs a converter to do it.
     */
    public function getConverter(): ConverterInterface;

    /**
     * The reference text of a registered anchor, or `null` when the id is not
     * registered.
     *
     * An xref renders its link text from the target's reference text, which is
     * the target's own title with reference-text substitutions applied
     * (`lib/asciidoctor/substitutors.rb:798-826`).
     */
    public function getReferenceText(string $refId): ?string;

    /**
     * The id of the registered anchor whose reference text matches `$text`, or
     * the empty string when there is none.
     *
     * This is the natural cross reference form, where the target is named by its
     * title rather than its id
     * (`lib/asciidoctor/substitutors.rb:826`, `lib/asciidoctor/document.rb:614-635`).
     */
    public function resolveId(string $text): string;

    /**
     * Whether `$path` names this document or a file it includes.
     *
     * An xref to a path that resolves to the document being converted is
     * rendered as a fragment, not as a link to another file
     * (`lib/asciidoctor/substitutors.rb:802`, `:810`).
     */
    public function isSelfReference(string $path): bool;

    /**
     * Register a footnote and return its assigned number.
     *
     * The number comes from the `footnote-number` counter and the footnote is
     * appended to the document's footnote list, which the converter renders as a
     * section at the end of the document
     * (`lib/asciidoctor/substitutors.rb:864-865`, `:873-874`,
     * `lib/asciidoctor/converter/html5.rb:232-238`).
     *
     * A named footnote is registered once and referenced thereafter, in which
     * case the existing number is returned rather than a new one assigned
     * (`lib/asciidoctor/substitutors.rb:859-860`).
     *
     * The return is `int|string` because a counter seeded by an attribute can be
     * a string; Ruby's footnote record accepts either
     * (`lib/asciidoctor/document.rb:89`).
     */
    public function registerFootnote(?string $id, string $text): int|string;
}
