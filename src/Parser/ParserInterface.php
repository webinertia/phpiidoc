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

namespace Phpiidoc\Parser;

use Phpiidoc\Converter\ConverterInterface;
use Phpiidoc\Node\DocumentInterface;
use Phpiidoc\Render\ParseOptions;
use Phpiidoc\Render\Source;

/**
 * The entry point of the parse phase.
 *
 * A caller hands over a source, the parse options and a converter, and receives
 * a document. The document is finished: a converter renders it into HTML without
 * re-reading, re-parsing or re-substituting anything. That is the acceptance
 * criterion for the phase and the reason every decision below is written the way
 * it is.
 *
 * Ruby's equivalent is `Document#parse`, which creates the reader if it does not
 * have one (`lib/asciidoctor/document.rb:526-531`), runs the preprocessors, calls
 * `Parser.parse` (`:540`), restores the attribute save point (`:543`), and
 * memoises the result (`:523-525`). `Parser.parse` is the loop that reads
 * sections until the reader is exhausted (`lib/asciidoctor/parser.rb:98-113`).
 *
 * ## Why the converter is an argument
 *
 * Because content is substituted during the parse, and substitution cannot run
 * without a converter. Ruby substitutes at convert time: an inline node is built
 * and immediately converted inside the substitutor
 * (`lib/asciidoctor/substitutors.rb:894-905`, `:1420-1449`), and it reaches the
 * converter through the document back-reference rather than through an argument
 * (`lib/asciidoctor/abstract_node.rb:29-35`). phpiidoc substitutes during the
 * parse so that a converter never has to, which means the converter has to be
 * present while the parse runs. Passing it here is the alternative to giving
 * every node a document back-reference, and it is the smaller of the two.
 *
 * The consequence is deliberate and stated: a parsed document is prepared for
 * one backend. Re-parsing for a second backend is cheap relative to rendering,
 * and phpiidoc has one backend.
 *
 * ## What the returned document guarantees
 *
 * The parser drives the stages named in the reasoning record: reading and
 * preprocessing through {@see \Phpiidoc\Reader\ReaderInterface}, block and
 * inline construction, substitution through
 * {@see \Phpiidoc\Substitutor\SubstitutorInterface}, and document finalisation.
 * On return:
 *
 * - Every node's content is substituted, and each node's own attributes were
 *   played back into the document attribute store while its content was
 *   substituted (`lib/asciidoctor/abstract_block.rb:75`), so an attribute
 *   defined by a block is visible to that block's own substitutions.
 * - Section ids are assigned, and they are assigned from the converted title,
 *   because Ruby generates a section id from the title after substitutions
 *   (`lib/asciidoctor/section.rb:63-65`, `:198-226`). A title containing `&` or
 *   `<` therefore produces a different id than the source text would.
 * - Reference ids are registered, counters are advanced, footnotes are numbered
 *   and callout ids are consumed (`lib/asciidoctor/document.rb:560-635`).
 * - The attribute store is restored to its header save point, so entries made in
 *   the body are not visible to a caller afterwards
 *   (`lib/asciidoctor/document.rb:543`, `:839-845`).
 * - Every defect found on the way is on the document's diagnostics. There is no
 *   second channel and nothing is returned by reference.
 *
 * ## Source locations
 *
 * When `ParseOptions::$sourcemap` is `false`, nodes carry no line number. Ruby
 * captures a cursor only when the option is set (`lib/asciidoctor/document.rb:330`,
 * `:462`, `:507`, `:530`) and reports a nil line otherwise
 * (`lib/asciidoctor/abstract_block.rb:67-69`). The path is still known, because
 * includes mean it is not the same for every node.
 *
 * ## What this call does not do
 *
 * It does not read a document source that cannot be read. An unreadable document
 * source is a caller error rather than a document defect: there is nothing to
 * report it against, so it is not turned into a diagnostic here. Every other
 * failure, including an include that cannot be read, is a diagnostic on the
 * returned document.
 *
 * ## Why this is an interface
 *
 * Not because a second parser is expected. There is one, and this contract does
 * not anticipate a second. It exists so that the render entry point depends on
 * the phase rather than on the class, and so that the acceptance criterion has a
 * named seam to be tested at: a test can parse a source and assert on the tree a
 * converter receives, without a renderer and without HTML.
 *
 * @api
 */
interface ParserInterface
{
    /**
     * Parse one source into a document.
     *
     * Each call produces a new document with its own attribute state. Ruby
     * memoises and returns the same document twice (`lib/asciidoctor/document.rb:523-525`),
     * which is safe only because a document cannot be parsed with different
     * options after the fact. Here the options are an argument, so two calls with
     * different options are two documents and neither is cached.
     *
     * @param Source $source The document source. It is read here if it is a file.
     * @param ParseOptions $options Safe mode, attribute overrides, base directory,
     *     include depth and source map, fixed for the whole parse.
     * @param ConverterInterface $converter The converter that inline substitution
     *     reaches while the parse runs. It is not asked to convert any block
     *     here; only the inline transforms substitution produces.
     */
    public function parse(
        Source $source,
        ParseOptions $options,
        ConverterInterface $converter,
    ): DocumentInterface;
}
