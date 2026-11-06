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

namespace Phpiidoc\Reader;

use Phpiidoc\Diagnostic\SourceLocation;

/**
 * The line cursor the parser drives, with preprocessing applied as each line is
 * read.
 *
 * ## Why this is a cursor and not a function
 *
 * The obvious contract is `preprocess(string $source): string`: read the file,
 * expand the includes, evaluate the conditionals, hand the result to the block
 * parser. That contract is wrong, and the oracle says so in three places.
 *
 * The reader consults the live attribute store on every line it touches. The
 * maximum include depth is read from the attribute store when the reader is
 * constructed (`lib/asciidoctor/reader.rb:616-622`), but the current depth can
 * be lowered by a single include's own `depth` attribute as that include is
 * pushed (`lib/asciidoctor/reader.rb:711-721`). A conditional directive is
 * evaluated against the attribute store at the moment the line is reached, so
 * an attribute entry written by the parser a hundred lines earlier changes
 * whether a later `ifdef` keeps its body
 * (`lib/asciidoctor/reader.rb:931-984`, `lib/asciidoctor/parser.rb:2111-2118`).
 * An include target is itself substituted from attributes at the line where the
 * directive appears (`lib/asciidoctor/reader.rb:1027-1030`).
 *
 * Those three facts together mean preprocessing cannot run ahead of parsing. It
 * has to interleave with it, one line at a time, against state the parser owns.
 * A pull interface is the only shape that expresses that, so this one is a pull
 * interface.
 *
 * ## What preprocessing does to the line stream
 *
 * Includes are expanded in place. When an include's last line is consumed the
 * cursor returns to the parent at the line after the directive
 * (`lib/asciidoctor/reader.rb:648-662`). Two consequences follow, and both are
 * load-bearing:
 *
 * - `getLocation()` is not monotonic. Its path changes at an include boundary
 *   and its line number restarts inside the included file. A consumer that
 *   wants document order must not sort by location.
 * - The line number is one-based and counts the line at the cursor
 *   (`lib/asciidoctor/reader.rb:33-34`, `:51`). Pushing a line back decrements
 *   it (`lib/asciidoctor/reader.rb:446-473`), so a line that is read, pushed
 *   back and read again reports the same number both times.
 *
 * Conditional directives remove lines. A skipped body never reaches the caller:
 * the reader consumes those lines internally (`lib/asciidoctor/reader.rb:994-996`).
 * The single-line form replaces the following line rather than removing it
 * (`lib/asciidoctor/reader.rb:984-992`).
 *
 * ## Normalisation
 *
 * Every line handed out has had its BOM removed, has been transcoded to UTF-8
 * and has had trailing whitespace stripped (`lib/asciidoctor/helpers.rb:117-134`,
 * `lib/asciidoctor/reader.rb:576-598`). This is not cosmetic: AsciiDoc is
 * whitespace sensitive in the middle of a line and indifferent to it at the end,
 * and a retained trailing space changes how a hard line break, a list
 * continuation and a table cell are detected. One exception, and it is
 * deliberate: an included file whose extension is not an AsciiDoc extension is
 * chomped but not stripped, because its lines are source text, not markup
 * (`lib/asciidoctor/reader.rb:724`).
 *
 * ## Null and the empty string are different
 *
 * `null` means there is no line at the cursor. The empty string means there is a
 * blank line there. Ruby collapses the two in several predicates through
 * `nil_or_empty?`, which is why `next_line_empty?` cannot tell a blank line from
 * the end of the document (`lib/asciidoctor/reader.rb:109-111`). This interface
 * keeps them apart and requires callers to keep them apart. Where Ruby's
 * collapse is observable in output, the collapse happens in the caller, not
 * here.
 *
 * ## What this interface does not do
 *
 * It does not open a path. {@see ReaderFactoryInterface} does that, because a
 * `Source` may be text and because opening a file is a decision about safe mode
 * that belongs at a named seam. It does not resolve include targets:
 * {@see IncludeResolverInterface} does the path arithmetic and the containment
 * test, and this interface applies the policy. It does not parse anything: a
 * line leaves here as a line.
 *
 * @api
 */
interface ReaderInterface
{
    /**
     * Whether a line remains at the cursor.
     *
     * Probing may run the preprocessor over lines that are then discarded, so
     * this is not free.
     */
    public function hasMoreLines(): bool;

    /**
     * Whether the reader is exhausted.
     */
    public function isEmpty(): bool;

    /**
     * The one-based number of the line at the cursor.
     *
     * After a read this is the number of the line that will be read next, not
     * the number of the line just returned. A consumer that needs the location
     * of the line it just read takes it before reading, or uses
     * {@see self::getPreviousLineLocation()}.
     */
    public function getLineNumber(): int;

    /**
     * The location of the line at the cursor.
     *
     * Never null. A document read from a string reports the synthetic path
     * carried by its {@see \Phpiidoc\Render\Source}, which is `<string>` by
     * default. Ruby uses `<stdin>` for the same case
     * (`lib/asciidoctor/reader.rb:50`).
     */
    public function getLocation(): SourceLocation;

    /**
     * The location of the given line in the file currently being read.
     *
     * The line is not validated against the length of that file, because the
     * caller computes it as an offset from the cursor to point a diagnostic at a
     * construct it has already consumed
     * (`lib/asciidoctor/parser.rb:1669`).
     */
    public function getLocationAtLine(int $line): SourceLocation;

    /**
     * The location recorded by the last {@see self::mark()}.
     *
     * Falls back to {@see self::getLocation()} when nothing has been marked
     * (`lib/asciidoctor/reader.rb:483-485`).
     */
    public function getLocationAtMark(): SourceLocation;

    /**
     * The location of the line before the cursor.
     *
     * Used to point a diagnostic at the line that caused a failure detected
     * after it was consumed (`lib/asciidoctor/reader.rb:496-498`).
     */
    public function getPreviousLineLocation(): SourceLocation;

    /**
     * Record the cursor as the mark and return it.
     *
     * The mark is one slot, not a stack. The returned value is the mark itself,
     * so a caller that needs to overwrite and then restore the mark holds the
     * location rather than asking the reader to remember two of them
     * (`lib/asciidoctor/reader.rb:500-506`, `lib/asciidoctor/parser.rb:1253-1275`).
     */
    public function mark(): SourceLocation;

    /**
     * Replace the mark with a location previously returned by
     * {@see self::mark()}.
     */
    public function restoreMark(SourceLocation $mark): void;

    /**
     * The line at the cursor, or null at the end of the document.
     *
     * Does not consume the line.
     */
    public function peekLine(): ?string;

    /**
     * The next `$count` lines at the cursor without consuming them, or every
     * remaining line when `$count` is null.
     *
     * @return list<string>
     */
    public function peekLines(?int $count = null): array;

    /**
     * Whether the line at the cursor is blank or absent.
     *
     * This is the one place the null and empty cases are deliberately merged,
     * because both mean "there is nothing to parse here"
     * (`lib/asciidoctor/reader.rb:109-111`).
     */
    public function nextLineEmpty(): bool;

    /**
     * Consume and return the line at the cursor, or null at the end of the
     * document.
     */
    public function readLine(): ?string;

    /**
     * Consume and return every remaining line.
     *
     * @return list<string>
     */
    public function readLines(): array;

    /**
     * Consume lines until one of the stop conditions in `$options` fires.
     *
     * When a terminator is configured and the document ends before it is found,
     * the reader reports `ReasonCode::UnterminatedDelimitedBlock` at
     * `$options->startLocation` and returns the lines it did read. Ruby warns
     * and continues with the whole remainder of the document
     * (`lib/asciidoctor/reader.rb:424-428`), so the block is still built and
     * still rendered; the diagnostic is what makes the missing delimiter
     * visible.
     *
     * A `$breakWhen` predicate is consulted after the terminator and the
     * configured break conditions, and receives each candidate line before it is
     * appended (`lib/asciidoctor/reader.rb:407`). The predicate is what lets the
     * parser stop at the start of the next block without the reader knowing
     * anything about block syntax.
     *
     * @param (callable(string): bool)|null $breakWhen
     * @return list<string>
     */
    public function readLinesUntil(ReadLinesOptions $options, ?callable $breakWhen = null): array;

    /**
     * Consume and discard the line at the cursor.
     *
     * @return bool Whether there was a line to discard.
     */
    public function advance(): bool;

    /**
     * Push a line back onto the cursor.
     *
     * The line is treated as already processed, so it is not run through the
     * preprocessor again (`lib/asciidoctor/reader.rb:223-236`).
     */
    public function unshiftLine(string $line): void;

    /**
     * Push lines back onto the cursor, in order.
     *
     * @param list<string> $lines
     */
    public function unshiftLines(array $lines): void;

    /**
     * Replace the line at the cursor.
     *
     * Used to rewrite an include directive that will not be processed
     * (`lib/asciidoctor/reader.rb:261-265`, `:1041`, `:1054`).
     */
    public function replaceNextLine(string $line): void;

    /**
     * Consume blank lines at the cursor and return how many were consumed.
     *
     * Ruby returns nothing when the reader is exhausted by the call, losing the
     * count in exactly the case a caller might want it
     * (`lib/asciidoctor/reader.rb:282-292`). This returns the count always.
     */
    public function skipBlankLines(): int;

    /**
     * Consume consecutive comment lines at the cursor.
     *
     * A `///` block comment is consumed to its closing delimiter
     * (`lib/asciidoctor/reader.rb:307-321`).
     */
    public function skipCommentLines(): void;

    /**
     * Consume consecutive comment lines at the cursor and return them.
     *
     * Only the `//` form is recognised, never the `///` block
     * (`lib/asciidoctor/reader.rb:326-337`).
     *
     * @return list<string>
     */
    public function skipLineComments(): array;

    /**
     * Consume every remaining line without reading it.
     */
    public function terminate(): void;

    /**
     * Snapshot the whole reader, including the include stack.
     *
     * Used for a header that is read tentatively and may have to be unread: the
     * document header is consumed, validated, and rolled back when it turns out
     * to be a malformed manpage title
     * (`lib/asciidoctor/parser.rb:233`, `:270`). Restoring rewinds the line
     * stream and the location, which is why this cannot be expressed as a
     * mark and a re-read.
     */
    public function save(): void;

    /**
     * Rewind to the last snapshot and discard it.
     */
    public function restoreSave(): void;

    /**
     * Discard the last snapshot and stay where the cursor is.
     */
    public function discardSave(): void;

    /**
     * The include targets reached so far, keyed by target with its extension
     * removed, mapped to whether the target is visible to interdocument
     * cross references.
     *
     * Ruby records this in the document catalog as it pushes each include
     * (`lib/asciidoctor/reader.rb:693-706`). Nothing in the HTML5 output depends
     * on it; it is here because the catalog is part of the prepared document and
     * the include graph is otherwise unobservable once the lines have been
     * expanded.
     *
     * @return array<string, bool>
     */
    public function getIncludedRootnames(): array;
}
