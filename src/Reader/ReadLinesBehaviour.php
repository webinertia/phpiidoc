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

/**
 * One optional behaviour of a {@see ReaderInterface::readLinesUntil()} call.
 *
 * Ruby models these as seven independent boolean keys of the options hash
 * (`lib/asciidoctor/reader.rb:387-430`). They are independent here too: a call
 * carries a set of them, and any combination is legal except where a case says
 * otherwise.
 *
 * @api
 */
enum ReadLinesBehaviour
{
    /**
     * Stop before the first empty line.
     *
     * Cannot apply when a terminator is set, because a delimited construct ends
     * at its delimiter rather than at a blank line
     * (`lib/asciidoctor/reader.rb:393-400`).
     */
    case BreakOnBlankLines;

    /**
     * Stop before a bare list continuation marker, and push that marker back
     * onto the reader so the caller sees it
     * (`lib/asciidoctor/reader.rb:406`, `:409-412`).
     */
    case BreakOnListContinuation;

    /**
     * Drop lines that start with `//` but not `///`, which is an ordinary line
     * comment rather than a block comment
     * (`lib/asciidoctor/reader.rb:415`).
     */
    case SkipLineComments;

    /**
     * Consume the line at the cursor before reading anything.
     *
     * Used for the opening line of a delimited block and for the opening line
     * of a comment block (`lib/asciidoctor/reader.rb:403`).
     */
    case SkipFirstLine;

    /**
     * Include the line that stopped the read in the result
     * (`lib/asciidoctor/reader.rb:408`).
     */
    case ReadLastLine;

    /**
     * Push the line that stopped the read back onto the reader instead of
     * consuming it (`lib/asciidoctor/reader.rb:409-412`).
     */
    case PreserveLastLine;

    /**
     * Read the lines without running the preprocessor over them, for a source
     * file that is not AsciiDoc (`lib/asciidoctor/reader.rb:389-392`).
     */
    case SkipProcessing;
}
