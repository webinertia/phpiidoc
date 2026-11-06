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

use function in_array;

/**
 * The options for one {@see ReaderInterface::readLinesUntil()} call.
 *
 * Ruby passes this as a hash literal at each of its twenty-odd call sites
 * (`lib/asciidoctor/reader.rb:387-430`). The keys below are that hash, with two
 * deliberate differences.
 *
 * The first is that the object is immutable. Ruby writes back into the hash
 * while it runs, setting `preserve_last_line` when a list continuation ends the
 * read (`lib/asciidoctor/reader.rb:406`). That write is internal only: no caller
 * reads it back, because the two call sites that care about a preserved
 * continuation line pass the flag in themselves
 * (`lib/asciidoctor/parser.rb:1503`, `:1553`). The mutation therefore has no
 * consumer and the flag is an input here.
 *
 * The second is the cursor. Ruby accepts `cursor: :at_mark` as a sentinel and
 * resolves it against the reader's mark (`lib/asciidoctor/reader.rb:424-425`),
 * which exists only because the mark is reader state rather than a value. Here
 * the caller holds the location and passes it, so `$startLocation` is either a
 * location or nothing.
 *
 * The terminator is always a literal line, never a pattern. Ruby permits a
 * Regexp but every call site passes the delimiter line it just matched
 * (`lib/asciidoctor/parser.rb:873`, `:1037`, `lib/asciidoctor/reader.rb:314`),
 * and a pattern would have to re-implement `==` to stay parity.
 *
 * @api
 */
final readonly class ReadLinesOptions
{
    /**
     * @param ?string $terminator Stop when a line equals this string. A
     *        terminator switches off {@see ReadLinesBehaviour::BreakOnBlankLines}
     *        and {@see ReadLinesBehaviour::BreakOnListContinuation}, which
     *        cannot apply to a delimited construct
     *        (`lib/asciidoctor/reader.rb:393-400`).
     * @param list<ReadLinesBehaviour> $behaviours The optional behaviours this
     *        call carries. Empty means the read stops only at the terminator or
     *        at the end of the document.
     * @param ?string $context The construct name used in the unterminated
     *        block diagnostic. Ruby defaults it to the terminator string
     *        (`lib/asciidoctor/reader.rb:424`); an explicit null suppresses the
     *        diagnostic, which is what a description list term does
     *        (`lib/asciidoctor/parser.rb:1470`).
     * @param ?SourceLocation $startLocation Where the construct started, used
     *        as the diagnostic location. Null means the cursor at the time of
     *        the call.
     */
    public function __construct(
        public ?string $terminator = null,
        public array $behaviours = [],
        public ?string $context = null,
        public ?SourceLocation $startLocation = null,
    ) {}

    /**
     * Whether this call carries the given behaviour.
     */
    public function has(ReadLinesBehaviour $behaviour): bool
    {
        return in_array($behaviour, $this->behaviours, strict: true);
    }
}
