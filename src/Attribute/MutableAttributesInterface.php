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

namespace Phpiidoc\Attribute;

/**
 * The attribute store the parse phase writes to.
 *
 * {@see AttributesInterface} is the read side and it is what a converter and a
 * substitutor are given. It is not enough for the parse phase, because the parse
 * phase is the thing that fills it: the reader consults the store on every line
 * and the parser writes to it as it reads, so an attribute entry four hundred
 * lines into a document changes how the nine hundred lines after it are read
 * (`lib/asciidoctor/parser.rb:2167-2187`). A read-only view cannot express that
 * and a value object cannot either, so the writable half is a separate contract.
 *
 * ## Why the reader needs a writer
 *
 * Because the reader is where `ifdef` and include depth are decided, and both
 * read attributes that the body may have set. `max-include-depth` is read when
 * the reader is constructed and again on every include
 * (`lib/asciidoctor/reader.rb:616-622`, `:711-721`), and `ifdef` tests
 * `attributes.key?` at the line the directive appears on
 * (`lib/asciidoctor/reader.rb:931-996`). An `ifdef` above an attribute entry
 * takes the false branch; the same `ifdef` below it takes the true branch. This
 * is the reason preprocessing cannot be a pre-pass and the reason this interface
 * has a `set` that is callable from the reader.
 *
 * ## Locking is enforced here, not by the caller
 *
 * An attribute supplied by the render call is locked and cannot be reassigned by
 * the document (`lib/asciidoctor/document.rb:876-877`, `:924-926`). A header
 * assignment to a locked name is neither applied nor an error
 * (`lib/asciidoctor/document.rb:876-877`), which is why `set()` reports success
 * as a boolean instead of throwing. Callers do not test the lock themselves:
 * there is one implementation of the rule and it lives behind this method.
 *
 * ## Attribute value substitution
 *
 * A value assigned during parsing has attribute references substituted in it
 * before it is stored (`lib/asciidoctor/document.rb:878`), so
 * `:a: 1` followed by `:b: {a}` stores `b` as `1`. The substitution is the
 * substitutor's, which is why an implementation of this interface is constructed
 * with one rather than doing the work itself. That is a construction concern and
 * is not part of this surface.
 *
 * @api
 */
interface MutableAttributesInterface extends AttributesInterface
{
    /**
     * Assign an attribute for the remainder of the parse.
     *
     * Returns false and changes nothing when the name is locked. Returns true
     * otherwise, with the substituted value stored.
     *
     * A value of the empty string is a set attribute in its boolean on state,
     * not an unset one; unsetting is {@see self::unset()}
     * (`lib/asciidoctor/document.rb:873`).
     */
    public function set(string $name, mixed $value): bool;

    /**
     * Unset an attribute for the remainder of the parse.
     *
     * Returns false and changes nothing when the name is locked, true otherwise
     * (`lib/asciidoctor/document.rb:905-917`).
     */
    public function unset(string $name): bool;

    /**
     * Freeze the current state as the header state.
     *
     * Called once, when the header ends (`lib/asciidoctor/document.rb:839-844`).
     * From that point the store distinguishes two states, and
     * {@see self::restoreHeader()} returns to this one.
     */
    public function saveHeader(): void;

    /**
     * Return to the header state.
     *
     * Ruby calls this after the body has been parsed and before conversion
     * (`lib/asciidoctor/document.rb:543`), with the stated intent of restoring
     * "the attributes in header" (`:859-863`). The consequence for a converter is
     * that an attribute entry in the body does not reach it: the body entry
     * affected how the body was parsed, and then it went away.
     */
    public function restoreHeader(): void;

    /**
     * Apply attribute entries that were deferred to a block.
     *
     * An attribute entry written in a block's metadata position is not applied
     * where it is read. It is carried on the block and applied when the block's
     * content is generated (`lib/asciidoctor/document.rb:846-857`,
     * `lib/asciidoctor/abstract_block.rb:76`). The entries are a list of name and
     * value pairs, where a null value means the entry unsets the name, which is
     * how Ruby's `negate` reads (`lib/asciidoctor/document.rb:849-856`).
     *
     * The shape is an array rather than a type because these entries never leave
     * the parser and the block it is building. A type is worth adding when
     * something other than the parser needs to name one.
     *
     * @param list<array{name: string, value: ?string}> $entries
     */
    public function playback(array $entries): void;

    /**
     * Take the next number in the named counter's sequence.
     *
     * The sequence starts from the counter's stored value, else from the
     * attribute of the same name, else from `$seed`, else from 1, and the result
     * is stored both as the counter and, unless the name is locked, as the
     * attribute (`lib/asciidoctor/document.rb:569-580`). A seed that is a string
     * of digits becomes an integer (`:574`).
     *
     * This is parse-time state, not presentation state: footnote numbers and
     * appendix letters are assigned while parsing and a converter reads the
     * assigned value off the node rather than counting again
     * (`lib/asciidoctor/document.rb:588-590`).
     */
    public function counter(string $name, int|string|null $seed = null): int|string;
}
