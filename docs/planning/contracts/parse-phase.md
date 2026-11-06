# Contract: The parse phase

**Version** 0.1 (pre-1.0)

**Status** Draft for owner review

**Consumer** The render entry point, and any caller that needs a prepared document without going
through it. A site layer that wants to inspect the tree, rewrite it, or render it twice.

## Scope

This contract governs the AsciiDoc parse phase end to end: reading a source, preprocessing it,
building blocks and sections, building lists, running the substitution pipeline, assigning ids,
registering references, and returning a document that a converter renders without re-parsing.

It governs the contracts added for that phase and the amendments the phase forces on contracts that
already existed.

It does not govern conversion, rendering, presentation data, attribute precedence, diagnostics
severity policy, or the CLI. Those are recorded in `html-output.md`, `render-entry.md`,
`attributes.md`, `diagnostics.md` and `docs/planning/phpiidoc-cli-requirements.md`.

## The acceptance criterion

A parser is finished when it produces a document that a converter renders into the expected HTML
without re-parsing anything. Every decision below is judged against that sentence, including the
ones that look like they are about something else.

## The decision that shapes the phase: substitution runs during the parse

Ruby substitutes at convert time. `Block#content` applies substitutions for a simple block and
converts children for a compound one (`lib/asciidoctor/block.rb:108-129`), and
`AbstractBlock#content` converts each child and joins the results
(`lib/asciidoctor/abstract_block.rb:85-87`). A converter therefore receives a node whose content is
still source text and asks for `content`, which is the moment the work happens.

phpiidoc does the opposite. The parse phase runs the substitution pipeline and the document handed
to a converter has content already substituted. The consequences, all of them accepted:

1. `ParserInterface::parse()` takes a `ConverterInterface`, because inline substitution converts
   transient inline nodes and reaches the converter while the parse is running
   (`lib/asciidoctor/substitutors.rb:894-905`, `:1420-1449`).
2. A parsed document is prepared for one backend. Re-parsing for a second backend is cheap relative
   to rendering, and phpiidoc has one backend.
3. Section ids are generated in the substitution stage rather than the section stage, because Ruby
   generates them from the converted title (`lib/asciidoctor/section.rb:63-65`, `:198-226`). A title
   containing `&` produces a different id than its source text does, and generating ids earlier
   would produce the wrong one.
4. The paragraph in `ConverterInterface` that described lazy content is replaced. A converter that
   substitutes again would substitute twice.

The alternative, a two-phase API with a mutable tree in the middle, was rejected because it puts
the same work behind a second boundary and leaves the caller responsible for calling the second
half. That is the arrangement the acceptance criterion forbids.

## The stages

The phase is eight stages. The names are Ruby's subsystems, not invented phases, and the order is
the order Ruby runs them in.

| Stage | What it does | Ruby | Load-bearing for HTML |
|---|---|---|---|
| S1 Read | Open the source, hold lines, expose a cursor that can be marked, restored and rewound | `lib/asciidoctor/reader.rb:5-607` | yes |
| S2 Preprocess | Resolve includes, evaluate conditionals, record attribute entries, all while the parser reads | `lib/asciidoctor/reader.rb:608-1379` | yes |
| S3 Header | Document title, author line, revision line, header attribute entries, header save point | `lib/asciidoctor/parser.rb:127-211`, `lib/asciidoctor/document.rb:839-845` | yes |
| S4 Sections | Section titles, levels, ids, numbering, level offset | `lib/asciidoctor/parser.rb:322-503`, `:1615-1683`, `:1789-1831` | yes |
| S5 Blocks | Block metadata, delimited blocks, paragraphs, admonitions, quotes, listings, literals | `lib/asciidoctor/parser.rb:504-961`, `:1016-1094` | yes |
| S6 Lists and tables | List detection, list items, description lists, tables | `lib/asciidoctor/parser.rb:1112-1614`, `:2330-2601`, `lib/asciidoctor/list.rb` | lists yes, tables no |
| S7 Prepare | Substitution, inline construction, ids, xrefs, footnotes, callouts, counters | `lib/asciidoctor/substitutors.rb:7-1553`, `lib/asciidoctor/section.rb:198-226`, `lib/asciidoctor/document.rb:560-635` | yes |
| S8 Return | Restore the header attribute save point, finalise, hand back the document | `lib/asciidoctor/document.rb:522-568` | yes |

S1 and S2 are not a pre-pass. The reader consults the live attribute store on every line: the
include depth limit at construction (`lib/asciidoctor/reader.rb:616-622`), conditionals as each
directive is seen (`:931-996`), include targets substituted at the line that names them
(`:1027-1030`), and attribute entries the parser writes mid-body
(`lib/asciidoctor/parser.rb:2167-2187`). A `preprocess(source): string` contract is therefore wrong,
and the reader is a pull interface the parser drives.

## Surface

### The entry point

```php
namespace Phpiidoc\Parser;

interface ParserInterface
{
    public function parse(
        Source $source,
        ParseOptions $options,
        ConverterInterface $converter,
    ): DocumentInterface;
}
```

One call, one document, no state kept. Ruby's `Document#parse` memoises and returns the same
document twice (`lib/asciidoctor/document.rb:523-525`); here the options are an argument, so two
calls with different options are two documents and neither is cached.

### Reading and preprocessing

```php
namespace Phpiidoc\Reader;

interface ReaderFactoryInterface
{
    public function create(
        Source $source,
        ParseOptions $options,
        DiagnosticCollectorInterface $diagnostics,
        IncludeResolverInterface $resolver,
    ): ReaderInterface;
}
```

Construction fixes two things that cannot be deferred: the include depth limit
(`lib/asciidoctor/reader.rb:616-622`) and the base directory, which is both the root relative
includes resolve against and the jail that confines them (`lib/asciidoctor/abstract_node.rb:465-477`).
An include resolves relative to the file that named it, and the jail stays the document's base
directory (`lib/asciidoctor/reader.rb:1262-1263`).

```php
namespace Phpiidoc\Reader;

interface IncludeResolverInterface
{
    public function resolve(string $target, string $fromDir): string;

    public function isContained(string $path, string $baseDir): bool;
}
```

`resolve()` is path arithmetic and touches no filesystem, which is what Ruby's resolver does. It is
separate from the reader because includes are the only place the parse phase touches disk, and
because the symlink gap (D8) needs a place to be closed.

`ReaderInterface` is the pull cursor plus preprocessing, 29 methods in five groups:

| Group | Methods |
|---|---|
| Position | `getLineNumber`, `getLocation`, `getLocationAtLine`, `getLocationAtMark`, `getPreviousLineLocation` |
| Marks | `mark`, `restoreMark`, `save`, `restoreSave`, `discardSave` |
| Reading | `peekLine`, `peekLines`, `readLine`, `readLines`, `readLinesUntil`, `advance`, `nextLineEmpty`, `hasMoreLines`, `isEmpty` |
| Rewinding | `unshiftLine`, `unshiftLines`, `replaceNextLine` |
| Skipping and termination | `skipBlankLines`, `skipCommentLines`, `skipLineComments`, `terminate` |
| Includes | `getIncludedRootnames` |

`ReadLinesOptions` replaces Ruby's options hash (`lib/asciidoctor/reader.rb:387-430`). Ruby writes
back into that hash (`:406`) and uses a `cursor: :at_mark` sentinel (`:425`); neither survives into a
typed object, and both are replaced by an explicit value and an explicit method.

### Substitution

```php
namespace Phpiidoc\Substitutor;

interface SubstitutorInterface
{
    public function apply(string $text, ?array $substitutions = null): string;

    public function applyLines(array $lines, ?array $substitutions = null): array;

    public function applyHeader(string $text): string;

    public function applyReftext(string $text): string;

    public function resolve(string $spec, ?array $defaults = null): array;

    public function commit(
        ContentModel $model,
        ?array $substitutions = null,
        bool $verse = false,
        bool $stem = false,
    ): array;
}
```

`apply()` mirrors `apply_subs` (`lib/asciidoctor/substitutors.rb:76-124`): it short-circuits on an
empty or absent list (`:77`), joins an array with a line feed (`:79-81`), extracts passthroughs when
macros are in the list (`:83-90`), restores them and clears the placeholder table only at the
outermost call (`:115-121`), and splits with a `-1` limit so trailing empty fields survive (`:123`).
`applyLines()` is the array-preserving form, because a block's lines must stay separate until the
block decides how to join them.

`resolve()` is `resolve_subs` (`:1179-1239`): `+` and `-` modifiers (`:1188-1197`), the inline
`verbatim` special case (`:1201-1202`), group and hint expansion (`:1203-1214`), defaults
materialised only when a modifier appears (`:1217`), and invalid entries dropped with a warning
(`:1233-1237`).

`commit()` is `commit_subs` (`:1287-1315`): the content model decides the default group, a `subs`
attribute overrides the default rather than the other way round (`:1302-1306`), and a source
listing swaps `specialcharacters` for `highlight` when a highlighter is active (`:1309-1312`).

`Substitution` is an enum of eight cases. Ruby has a ninth, `:highlight`, which is not
document-requestable (`:39-42`) and is produced internally; it is a case here but it is not
reachable from a `subs` value. `SubstitutionGroup` is an enum of four, matching `SUB_GROUPS`
exactly (`:21-26`). `HEADER_SUBS` and `REFTEXT_SUBS` are constants, not group names (`:15`, `:18`),
so they are methods on the substitutor rather than cases.

`SubstitutionContextInterface` names the whole of Ruby's reach into `@document` from inside the
substitutor: the subject node, the attribute store, the converter, reference text lookup, id
resolution, self-reference detection, and footnote registration. Those seven are what
`lib/asciidoctor/substitutors.rb` actually calls; nothing else is on the interface, and
`DocumentInterface` is not widened to carry them.

### Attribute writes

`MutableAttributesInterface` extends the existing read-only `AttributesInterface` with `set()`,
`unset()`, `saveHeader()`, `restoreHeader()`, `playback()` and `counter()`. It exists because the
existing attribute contracts are results and the parse phase needs a store. The behaviours it
carries are locked names (`lib/asciidoctor/document.rb:876-877`), value substitution on set
(`:878`), playback of a block's own attribute entries before that block's content is substituted
(`:846-857`, `lib/asciidoctor/abstract_block.rb:75`), the header save point (`:839-845`), and
counter seeding (`:569-580`).

### Nodes the parser builds

`ContentModel` is an enum of `Simple`, `Verbatim`, `Raw`, `Compound` and `Empty`. It is a separate
value rather than a method on a node because the same context takes different models at different
build sites: a quote block is compound at `lib/asciidoctor/parser.rb:790` and simple at `:802`, and
a literal is verbatim at `:761`. A `forContext()` helper would therefore be wrong, and there is
none.

`ListItemInterface` extends `BlockInterface` and adds `getText()`, `hasText()`, `getTerms()` and
`getDescription()`. An item has two contents where a block has one: its text and its children. A
description list item is a term list plus an optional description (`lib/asciidoctor/list.rb:44-45`),
and the converter walks both (`lib/asciidoctor/converter/html5.rb:574-590`, `:596-604`).

`ListInterface` was planned and is not written. `List#items` and `List#content` are aliases of
`blocks` (`lib/asciidoctor/list.rb:5-11`), so a list accessor would rename
`BlockInterface::getBlocks()`, and `list_marker_keyword` is a lookup of
`ORDERED_LIST_KEYWORDS[@style]` (`lib/asciidoctor/abstract_block.rb:271-273`) that is derivable from
the node's style and is not parse state. The item marker is written by the parser
(`lib/asciidoctor/parser.rb:1321`, `:1349`, `:1359`) and read by no converter, so it is not
contracted either.

### Diagnostics

`DiagnosticCollectorInterface` has `report()` and `collect()`. It exists because
`DiagnosticsInterface` is a result: it is countable, iterable and read-only, and a phase that is
still running cannot report into a result. Widening it with `report()` would let any consumer of a
finished document append to it.

Source order is the collector's responsibility. It appends and never sorts, because the order a
defect was found in is information.

## Amendments to contracts already published

| Contract | Change | Why |
|---|---|---|
| `Node\BlockInterface::getContent()` | Narrowed to the node's own prepared content. A compound node returns the empty string. | Joining children needs a converter, and substitution has already happened. See D6. |
| `Converter\ConverterInterface` | The lazy-content paragraph is replaced. The converter never substitutes. | See the decision above. |
| `SafeMode::processesIncludes()` | Now false only at `Secure`, not at `Server`. | Ruby stops at `SECURE` (`lib/asciidoctor/reader.rb:1050`); at `SERVER` an include is still read and merely jailed. See D9. |
| `Diagnostic\ReasonCode` | Gains `UnterminatedDelimitedBlock`, a `Warning`. | The reader reports it and the code did not exist. Ruby warns and keeps the lines read so far (`lib/asciidoctor/reader.rb:424-427`), so nothing is discarded. |

## Guarantees to consumers

1. The returned document is finished. Every node's content is substituted, every id is assigned,
   every reference is resolved or reported, and no converter step re-reads a source.
2. Section ids are generated from the converted title, so a document rendered by phpiidoc has the
   same ids as the same document rendered by Asciidoctor.
3. Every node's own attributes are played back into the document attribute store while its content
   is substituted, so an attribute defined by a block is visible to that block.
4. The attribute store is restored to the header save point before the document is returned. Body
   entries are not visible to a caller afterwards.
5. Every defect found during the parse is on the document's diagnostics. There is no second channel.
6. `ParseOptions::$sourcemap` decides whether nodes carry a line number. With it off, the path is
   still known and the line is null.
7. An unreadable include is a diagnostic on the document, not an exception. An unreadable document
   source is neither: it is a caller error and is not turned into a diagnostic.
8. Calling `parse()` twice produces two documents with independent attribute state. Nothing is
   memoised on the parser.
9. Adding a substitution type, a reason code or a contract method is a minor change. Removing or
   renaming one, or changing the order of a substitution group, is a breaking change.

## Non-guarantees

- The internal shape of the reader's line buffer, its look-ahead, or how marks are stored.
- Whether the parser recurses or iterates, and in what order it visits nodes.
- The exact text of a diagnostic message. Codes are contract; messages are not.
- Table row and cell parsing. Tables are recognised, consumed and preserved as raw text with an
  `unsupported_construct` diagnostic.
- Extension points: custom converters, include processors, tree processors, preprocessors.
- Anything about conversion or rendering. A parsed document is input to that contract, not part of
  this one.

## Stability

This contract is pre-1.0. Adding a method to a contract added here, adding a substitution type, or
adding a reason code is a minor change. Removing or renaming a method, changing the order of a
substitution group, or changing which stage owns a behaviour is a breaking change.

`ContentModel` case names are contract. They are the vocabulary a converter switches on.

## Settled owner decisions

### D5. Substitution is a parse-phase stage

Settled: the parse phase runs the substitution pipeline. A parsed document is prepared for one
backend, and `parse()` takes the converter for that reason.

### D6. `getContent()` stays, narrowed

Settled: keep `getContent()` and narrow it to the node's own prepared content. A compound node
returns the empty string and the converter walks the children.

The three options offered were dropping the method, keeping it with a document back-reference, and
keeping it and passing the converter as an argument. All three were about how a node reaches a
converter to render its children. Under D5 a node no longer needs to: substitution has happened, so
the only remaining reason to reach a converter was to join child output, which is a rendering
decision that belongs on the converter. The method survives with a smaller meaning and no
back-reference, which answers the question rather than deferring it.

### D7. The converter is an argument to the parse entry point

Settled: `parse(Source, ParseOptions, ConverterInterface): DocumentInterface`.

### D8. Symlink containment belongs to the include resolver

Settled: it belongs to `IncludeResolverInterface`, not to a site's enumeration layer.

The six safe-mode normalisation rules are textual and mirror Ruby's `PathResolver`, which does the
same arithmetic and never touches the filesystem. A symlink inside the repository pointing outside
the jail defeats all six, because no amount of `..` counting sees a symlink. The resolver is the
component that decides whether a path is inside the jail, so the containment check lives there:
after the textual resolution, compare the real path of the target against the real path of the base
directory, walking to the nearest existing ancestor when the target does not exist. A failure is
`include_target_escapes_base_dir`, never collapsed into not-found.

A site enumeration layer cannot close this, because it never sees the resolved include target: the
include is resolved while the reader runs, from a path the site never named.

### D9. `SafeMode::processesIncludes()` was wrong at `Server`

Settled: the method now returns false only at `Secure` and above. Ruby's test is
`doc.safe >= SafeMode::SECURE` (`lib/asciidoctor/reader.rb:1050`), so at `SERVER` an include is read
normally and the jail is what limits where from. The previous docblock claimed include processing
was forbidden at `Server`, which was a misreading, and it is corrected rather than preserved.

Where Ruby rewrites a `SECURE` include directive into a link (`:1052-1054`), phpiidoc reports
`include_disabled_by_safe_mode`, for the same reason every other include failure is an error: a
rewritten line is a silent hole that no build fails on.

### D2. The shape of seam 2 stays deferred

Not decided by this contract, and deliberately not a precondition for it. `render-entry.md:304`
records the deferral and it stands.

## Deliberately not implemented in pass 1

Each omission is a decision with a consequence, not an unfinished item.

| Not implemented | Consequence |
|---|---|
| Table row and cell parsing | A table is recognised and its raw text is preserved with `unsupported_construct`. A document with a table renders the table's source as text. Tables are the largest remaining subsystem (`lib/asciidoctor/table.rb`, 745 lines) and none of it is needed to prove the parse phase. |
| STEM blocks | A stem block is parsed as a raw block. Its content is substituted with no substitutions and rendered as text rather than through a formula renderer. |
| `ifeval` | Not evaluated. A document using it fails loudly with `unsupported_construct` rather than silently including or excluding the wrong branch. Silent wrongness in a conditional is worse than a failed build. |
| Include `tag` and `tags` | Not supported. A tagged include fails with an error rather than including the whole file, because including the whole file is a silent wrong document. |
| Manpage doctype | The doctype is accepted and treated as an article. A manpage document loses its manpage-specific structure. |
| Docinfo files | Not read. A document that relies on docinfo for stylesheets or scripts renders without them. |
| Custom extensions, include processors, tree processors, preprocessors | No extension API. A site cannot extend the parser, and nothing in the parse phase is designed around an extension hook that does not exist. |
| Compat mode | Not modelled. Ruby's compatibility behaviours are off in a modern document anyway (`lib/asciidoctor.rb` compliance defaults), and supporting them would double the substitution surface. |
| Syntax highlighting | Not implemented. A source listing is substituted with `specialcharacters`, and `highlight` is never produced, so `commit()` cannot return it. `source-highlighter` is accepted and ignored with `unsupported_attribute_ignored`. |
| Index terms and bibliography catalogs | Index terms are recorded as text and no catalog is built. A document with `indexterm` renders the macro as text. |
| PDF, DocBook and manpage converters | Out of scope for phpiidoc entirely, not deferred. |

## Volume

Measured against the oracle, not estimated from it.

| File | Lines |
|---|---|
| `lib/asciidoctor/reader.rb` | 1379 |
| `lib/asciidoctor/parser.rb` | 2807 |
| `lib/asciidoctor/substitutors.rb` | 1553 |
| `lib/asciidoctor/table.rb` | 745 |
| `lib/asciidoctor/abstract_block.rb` | 518 |
| `lib/asciidoctor/abstract_node.rb` | 574 |
| `lib/asciidoctor/document.rb` | 1414 |
| `lib/asciidoctor/path_resolver.rb` | 545 |
| `lib/asciidoctor/section.rb` | 228 |
| `lib/asciidoctor/list.rb` | 112 |
| `lib/asciidoctor/attribute_list.rb` | 218 |
| `lib/asciidoctor/block.rb` | 144 |
| `lib/asciidoctor/inline.rb` | 77 |
| `lib/asciidoctor/rx.rb` | 741 |
| `lib/asciidoctor/callouts.rb` | 116 |

About 11,200 lines of oracle surface, about 10,000 of it in pass 1. The PHP estimate is 6,500 to
9,000 production lines and 8,000 to 12,000 test lines.

## Evidence

All citations are to the local Ruby Asciidoctor clone, version `2.1.0.alpha.0`.

| Claim | Ruby evidence |
|---|---|
| `Document#parse` creates the reader if it has none, parses, restores attributes and memoises. | `lib/asciidoctor/document.rb:522-556` |
| The parse entry is a section loop that returns the document. | `lib/asciidoctor/parser.rb:98-113` |
| Preprocessing is interleaved with parsing: conditionals test the live attribute store. | `lib/asciidoctor/reader.rb:931-996` |
| Include targets are substituted at the line that names them. | `lib/asciidoctor/reader.rb:1027-1030` |
| Attribute entries are written by the parser mid-body. | `lib/asciidoctor/parser.rb:2167-2187` |
| The include depth limit is read at construction, and zero or less disables includes. | `lib/asciidoctor/reader.rb:616-622` |
| The base directory is the root and the jail, fixed once. | `lib/asciidoctor/abstract_node.rb:465-477` |
| An include resolves relative to the including file, and the jail stays the base directory. | `lib/asciidoctor/reader.rb:1262-1263` |
| Include processing stops at `SECURE`, not at `SERVER`. | `lib/asciidoctor/reader.rb:1050-1054` |
| An unterminated delimited block warns and keeps the lines read so far. | `lib/asciidoctor/reader.rb:424-427` |
| `read_lines_until` takes a ten-key options hash and writes back into it. | `lib/asciidoctor/reader.rb:387-430` |
| Substitution is string in, string out, and inline markup is produced by converting transient nodes. | `lib/asciidoctor/substitutors.rb:76-124`, `:894-905`, `:1420-1449` |
| The substitution constants, groups and hints. | `lib/asciidoctor/substitutors.rb:14-42` |
| `resolve_subs` handles modifiers, groups, hints and invalid entries. | `lib/asciidoctor/substitutors.rb:1179-1239` |
| `commit_subs` picks a default from the content model, honours a `subs` attribute, and swaps in `highlight`. | `lib/asciidoctor/substitutors.rb:1287-1315` |
| `sub_attributes` handles `{set:}`, `{counter:}` and the four `attribute-missing` behaviours. | `lib/asciidoctor/substitutors.rb:212-276` |
| `Block#content` branches on the content model. | `lib/asciidoctor/block.rb:108-129` |
| `DEFAULT_CONTENT_MODEL` defaults to simple and is overridden at the build site. | `lib/asciidoctor/block.rb:12-24`, `lib/asciidoctor/parser.rb:761`, `:790`, `:802`, `:898` |
| `AbstractBlock#content` converts children and joins them. | `lib/asciidoctor/abstract_block.rb:85-87` |
| `AbstractBlock#convert` plays back the block's attributes before converting. | `lib/asciidoctor/abstract_block.rb:75` |
| A converter is reached through the document back-reference. | `lib/asciidoctor/abstract_node.rb:29-35` |
| `list_marker_keyword` is a style lookup. | `lib/asciidoctor/abstract_block.rb:271-273` |
| A list's items and content are aliases of its blocks. | `lib/asciidoctor/list.rb:5-11` |
| A description list item is a term list plus an optional description. | `lib/asciidoctor/list.rb:44-45` |
| A list item has text and a marker, and its text is substituted when read. | `lib/asciidoctor/list.rb:46-79` |
| The item marker is assigned by the parser and read by no converter. | `lib/asciidoctor/parser.rb:1321`, `:1349`, `:1359` |
| The converter iterates a description list as term, description pairs. | `lib/asciidoctor/converter/html5.rb:574-590`, `:596-604` |
| The converter renders an item's text and then its children. | `lib/asciidoctor/converter/html5.rb:998-1010`, `:748-758` |
| Section ids are generated from the converted title. | `lib/asciidoctor/section.rb:63-65`, `:198-226` |
| Counters, reference registration and id resolution. | `lib/asciidoctor/document.rb:560-635` |
| The header attribute save point and its restore. | `lib/asciidoctor/document.rb:839-845`, `:543` |
| Playback of a block's attribute entries. | `lib/asciidoctor/document.rb:846-857` |
| Option attributes are locked against later writes. | `lib/asciidoctor/document.rb:876-877` |
| Source locations are captured only when `sourcemap` is on. | `lib/asciidoctor/document.rb:330`, `:462`, `:507`, `:530`, `lib/asciidoctor/abstract_block.rb:67-69` |
