# Contract: Consumer Conformance

**Version** 0.1 (pre-1.0)

**Status** Draft for owner review

**Consumer** `webinertia/asciidoc-php-multi-repo` and the per-repository `doc-target` build action

## Scope

This contract governs what any consumer must do to render correctly against the six contracts in
this directory. It is the checklist a consumer is held to when it adopts phpiidoc.

It is not a phpiidoc surface. Nothing here changes phpiidoc's behaviour, and nothing here may be
used to justify changing one of the other contracts. Where phpiidoc's design and a consumer's
current behaviour diverge, phpiidoc's behaviour is authoritative and the divergence is recorded
here as a consumer change. The aggregator is a consumer, not a constraint.

Two consumers are in scope: `webinertia/asciidoc-php-multi-repo`, used throughout as the worked
example, and the per-repository `doc-target` build action, which builds a repository's own pages
from the same sources.

This contract does not govern the aggregator's report schema, its CLI, its discovery logic, or
its site assembly. It states only where those must change because of phpiidoc.

## Surface

### The general rule: delete detection, do not duplicate it

Where phpiidoc can detect a condition, the consumer deletes its own detection of that condition
rather than keeping a parallel one.

The reason is concrete. The aggregator's `assertIncludesResolve()` and the retired resolver's
containment behaviour disagree today: the pre-scan tests `is_file` relative to a base it computes
itself (`PageRenderer.php:228-240`), while the resolver recovers escapes silently. Neither is a
containment check, and the pre-scan's success has been read as evidence that containment works.
Two detectors disagreeing is how that false confidence arose.

A consumer therefore conforms when it:

- deletes a pre-scan whose condition phpiidoc reports as a diagnostic,
- maps phpiidoc's diagnostic onto its own report rather than raising its own exception for the
  same condition, and
- keeps only checks phpiidoc cannot make, such as "this repository declares no entry document".

### Conformance items

#### 1. `base_dir` becomes a jail root and must move to the source root

**Requirement.** A consumer must pass the repository source root as `baseDir`, not the directory
of the page being rendered.

**Why.** Under `Safe` and above, `baseDir` is the jail root: Ruby sets `start ||= doc.base_dir`
and `jail ||= doc.base_dir` when `safe >= SafeMode::SAFE`
(`lib/asciidoctor/abstract_node.rb:466-475`). A page that includes `../shared.adoc` leaves the
jail when `baseDir` is that page's own directory.

**Current behaviour.** `PageRenderer.php:110` passes
`$this->baseDirectory(dirname($source->page()->absolutePath()))`, which is the page directory,
rewritten relative to the process working directory.

**Conforming change.** Pass the prepared source root. The aggregator's fetch and prepare stages
already know the repository root, so no new concept is needed; the renderer simply stops
deriving the base from the page path.

**Also required.** The aggregator deletes its own containment check rather than reimplementing
it. `assertIncludesResolve()` tests that a target exists relative to the page directory
(`PageRenderer.php:228-240`), which is a weaker test than "inside the root". Once the jail exists,
that check is redundant for anything the resolver sees and wrong for anything it does not.
`include-outside-root` is produced by phpiidoc, not by a consumer-side pre-scan.

**Evidence.** `PageRenderer.php:110`, `PageRenderer.php:228-240`,
`lib/asciidoctor/abstract_node.rb:466-475`.

#### 2. The `baseDirectory()` workaround and the jail are mutually unsafe

**Requirement.** A consumer must delete any rewrite of an absolute base into a process-relative
one in the same change that introduces the jail.

**Why.** `PageRenderer.php:258-272` rewrites an absolute base into a cwd-relative path to dodge a
Windows drive-prefix bug in the retired resolver. Keeping that rewrite while adding a jail
anchors the jail to the process working directory instead of the source root, which changes what
is inside the jail depending on where the process was started.

**Current behaviour.** Two notions of base coexist: `PageRenderer.php:110` uses
`baseDirectory()`, while `assertIncludesResolve()` computes its own base at `PageRenderer.php:228`
and never consults it.

**Conforming change.** Delete the rewrite. Pass an absolute source root. Delete the second base
computation along with the pre-scan.

**Evidence.** `PageRenderer.php:110`, `PageRenderer.php:228`, `PageRenderer.php:258-272`.

#### 3. `IncludeOutsideRoot` has no producer today

**Requirement.** A consumer must not treat the presence of a reason code as evidence that the
condition is detected.

**Why.** `ReasonCode::IncludeOutsideRoot` is declared at `src/Report/ReasonCode.php:19` and has
no producer anywhere in the repository; the published report schema advertises it at
`contracts/report.schema.json:97`. The pre-scan only tests `is_file`
(`PageRenderer.php:237-240`) and never tests containment, and the retired resolver recovered
escapes silently. Until phpiidoc emits the code, the report contract advertises a condition that
cannot occur, and no test fails if containment breaks.

**Conforming change.** Wire the code to phpiidoc's `include_target_escapes_base_dir` diagnostic
(see `contracts/diagnostics.md`). That diagnostic exists only if open decision D4 in
`contracts/safe-mode.md` is resolved in favour of detecting the escape before resolution.

**Evidence.** `src/Report/ReasonCode.php:19`, `contracts/report.schema.json:97`,
`PageRenderer.php:237-240`.

#### 4. Deleting the pre-scan moves error attribution into phpiidoc

**Requirement.** A consumer must map phpiidoc's reason codes onto its own closed reason-code set,
and must decide whether it surfaces phpiidoc's corrective text or its own.

**Why.** `assertIncludesResolve()` raises through `failure()` at `PageRenderer.php:353-355` with
its own message and corrective action. Once phpiidoc owns the detection, those messages come from
phpiidoc unless the consumer supplies its own. `ReasonCode`'s docblock states that adding a case
without adding it to the published schema breaks the contract test
(`src/Report/ReasonCode.php:7-12`), and the schema is versioned
(`contracts/report.schema.json:10`).

**Required mapping artifact.** The consumer must publish a table like this one before the
cutover, and the table decides whether `schemaVersion` bumps.

| phpiidoc diagnostic | Aggregator reason code | Notes |
|---|---|---|
| `include_target_not_found` | `render-failed` | Existing code. Message carries the target and the source path. |
| `include_depth_exceeded` | `render-failed` | Existing code. |
| `include_disabled_by_safe_mode` | `render-failed` | Existing code. Should be unreachable once the default is `safe`. |
| `include_disabled_by_depth_zero` | `render-failed` | Existing code. Should be unreachable with the default limit. |
| `include_uri_not_allowed` | `render-failed` | Existing code. |
| `include_target_escapes_base_dir` | `include-outside-root` | First producer for the existing code. |
| `section_missing_id` | `toc-target-missing` | Existing code, re-pointed. |
| `unknown_block_context` | `render-failed` | Existing code. |
| `unsupported_construct` | `render-failed` | Existing code. |
| `duplicate_section_id` | none | Warning. Reported in the log, not in the report. |
| `unresolved_attribute_reference` | none | Warning. |
| `unsupported_attribute_ignored` | none | Warning. |

With this mapping the aggregator's ten reason codes are unchanged and `schemaVersion` does not
bump. Any mapping that needs an eleventh code does bump it.

**Decision required from the consumer.** Whether the report shows phpiidoc's `correctiveAction`
or the aggregator's own copy. phpiidoc's text couples user-facing wording to a dependency
version, which is convenient now and awkward when the dependency rewords a message. The
recommendation is that the aggregator keeps its own copy for the codes it already has and uses
phpiidoc's text only for codes it has no copy for.

**Evidence.** `PageRenderer.php:353-355`, `src/Report/ReasonCode.php:7-12`,
`contracts/report.schema.json:10`, `contracts/report.schema.json:92-97`.

#### 5. `<article class="doc">` is owned by phpiidoc

**Requirement.** A consumer must not wrap the fragment in a second article element and must not
strip the wrapper phpiidoc returns.

**Why.** Today the aggregator owns the wrapper: `SiteConverter::ARTICLE_OPEN` and `ARTICLE_CLOSE`
at `SiteConverter.php:36` and `:38`, applied at `:71`, while the outer page is rendered by the
page shell at `PageRenderer.php:211`. Ruby's embedded transform appends `node.content` with no
wrapper at all (`lib/asciidoctor/converter/html5.rb:329`).

**Conforming change.** phpiidoc returns the wrapped fragment. The aggregator deletes
`ARTICLE_OPEN`, `ARTICLE_CLOSE` and its document-level `convertDocument()` override, and inserts
the fragment into its page shell. The aggregator's CSS keeps targeting `.doc`, which is why the
wrapper must stay.

**Evidence.** `SiteConverter.php:36`, `:38`, `:71`, `PageRenderer.php:211`,
`lib/asciidoctor/converter/html5.rb:329`.

#### 6. `SiteConverter` and `lastDocument()` disappear

**Requirement.** A consumer must read the document from the render result and keep an equivalent
assertion that a document was produced.

**Why.** `PageRenderer.php:111` injects the converter and `:114` reads
`$converter->lastDocument()`. The `instanceof Document` guard at `:116-117` throws when the
converter produced nothing. Losing the guard turns a converter regression into a blank page
instead of a failed build.

**Conforming change.** Read `$result->document` and assert it is a `DocumentInterface`. Keep the
failure path. Delete the converter injection.

**Evidence.** `PageRenderer.php:111`, `:114`, `:116-117`, `SiteConverter.php:40`, `:64`,
`:83-85`.

#### 7. TOC extraction can move into phpiidoc

**Requirement.** A consumer should use `RenderResult::$toc` rather than walking the document
itself, and must retire its own extractor when it does.

**Why.** `TocExtractor.php:52-56` walks `getBlocks()` depth-first, computes `getLevel() + 1`, and
filters against configured heading levels. phpiidoc returns the same structure on the result
(`contracts/document-model.md`). With `sectids` present by default (`lib/asciidoctor.rb:378`), a
section without an id can only arise from unsetting `sectids` or from a malformed explicit
anchor, both of which phpiidoc reports as diagnostics. A consumer-side "section has no id" check
therefore becomes dead code in the same way `IncludeOutsideRoot` already is.

**Conforming change.** Delete `TocExtractor` and read `$result->toc`. The aggregator's
`toc-target-missing` code is re-pointed at phpiidoc's `section_missing_id` diagnostic (item 4).

**Evidence.** `TocExtractor.php:52-56`, `lib/asciidoctor.rb:378`, `lib/asciidoctor/section.rb:190`.

#### 8. TOC level semantics are a silent off-by-one

**Requirement.** A consumer that keeps its own level filter must not add one to a level that is
already one-based.

**Why.** `getLevel()` is zero-based and `TocExtractor.php:53` adds one to compare against heading
levels. phpiidoc keeps zero-based levels (`contracts/document-model.md`), so the `+1` stays. If a
consumer assumes one-based levels, every configured `tocLevels` threshold shifts by one, which
changes which entries appear without failing a build.

**Conforming change.** Read `TocEntry::$level` as zero-based, or compare against
`getLevel() + 1`, exactly as today.

**Evidence.** `TocExtractor.php:53`, `lib/asciidoctor/section.rb:46-51`.

#### 9. `hljs` is not emitted, and the aggregator's comment is wrong

**Requirement.** A consumer must not depend on an `hljs` token in a source block class.

**Why.** `hasCodeBlock()` at `PageRenderer.php:373` matches
`class="language-(?!mermaid)` and does not require `hljs`, while its docblock at
`PageRenderer.php:366` claims the converter writes `class="language-php hljs"`. The attribute
policy sets `source-highlighter` to `false` (`AttributePolicy.php:62`), so `hljs` cannot come from
that attribute, and Ruby emits `class="language-php" data-lang="php"` when no highlighter is
configured (`lib/asciidoctor/converter/html5.rb:685`).

**Conforming change.** Nothing functional. The aggregator corrects its docblock. The
load-bearing markers are `language-<lang>`, `data-lang="<lang>"`, and either `language-mermaid`
or `data-lang="mermaid"` for Mermaid.

**Evidence.** `PageRenderer.php:366`, `:373`, `:381`, `AttributePolicy.php:62`,
`lib/asciidoctor/converter/html5.rb:685`.

#### 10. `source-highlighter` is the first precedence case that bites

**Requirement.** The consumer must rely on option-attribute precedence for
`source-highlighter`, or validate that no document header sets it.

**Why.** `AttributePolicy.php:62` sets `source-highlighter` to `false` so the site highlights
client-side. If a document header wins, a repository can enable a highlighter, phpiidoc emits
highlighting spans inside a Mermaid block, and the diagram loader that reads the raw text no
longer matches what it finds. `hasMermaid()`'s comment records that this destroys the diagram.

**Conforming change.** If option attributes win (the recommendation in `contracts/attributes.md`,
open decision D3), nothing changes. If the header wins, the consumer needs a validation pass that
rejects a header setting `source-highlighter`.

**Evidence.** `AttributePolicy.php:62`, `PageRenderer.php:381`,
`lib/asciidoctor/document.rb:876-877`, `:924-926`.

#### 11. `idprefix`, `idseparator` and `sectids` are the second precedence case

**Requirement.** The consumer must rely on option-attribute precedence for these three, or
validate that no document header sets them.

**Why.** The aggregator sets none of them and relies on the defaults. Ruby defaults `idprefix`
and `idseparator` to `_` (`lib/asciidoctor/section.rb:198-226`). The aggregator builds TOC links
and cross-page anchors from ids, so a repository that sets `:idprefix:` in its header changes
every id in that repository if the header wins.

**Conforming change.** If option attributes win, the consumer pins the three and is safe. If the
header wins, the consumer needs a validation pass that rejects a document header setting any of
them.

**Evidence.** `lib/asciidoctor/section.rb:198-226`, `lib/asciidoctor.rb:378`,
`contracts/document-model.md`.

#### 12. The per-repository build action is a second consumer

**Requirement.** The `doc-target` build action must adopt the same `baseDir`, the same fragment
shape and the same safe mode as the aggregate site.

**Why.** If it does not, the same document renders differently in the repository's own pages and
in the aggregate site, and the difference appears as broken anchors, missing assets or a page
that renders where the other fails.

**Conforming change.** Write the action against these contracts, not against the aggregator's
current implementation. In particular it must not copy the page-directory base or the article
wrapper, both of which the aggregator is losing.

**Evidence.** `contracts/render-entry.md`, `contracts/html-output.md`,
`contracts/safe-mode.md`.

## Guarantees to consumers

1. Every requirement above is satisfied by consuming only the six contracts in this directory.
   No requirement depends on undocumented phpiidoc behaviour.
2. Where a consumer deletes a check, the replacement diagnostic is named, so a deletion is
   verifiable by a test that asserts the code appears.
3. The reason-code mapping in item 4 keeps the aggregator's ten existing codes, so a conforming
   cutover does not bump `schemaVersion`.
4. A consumer that conforms renders the same document identically to every other conforming
   consumer.

## Non-guarantees

- The aggregator's internal architecture. Item 7 and item 6 name classes to delete, not a target
  design.
- The aggregator's report schema beyond the mapping table.
- The per-repository action's interface.
- The order in which a consumer adopts these items. Only that a partial adoption is not a
  conformance claim.
- The aggregator's user-facing wording.

## Stability

This contract is pre-1.0 and is expected to change more often than the six phpiidoc contracts,
because it tracks consumer migrations. Items are added as consumers are audited and removed when
a consumer has conformed.

An item may not be removed to accommodate a consumer's current behaviour. If a consumer cannot
conform, the divergence is recorded as a consumer defect, not as a relaxation here.

## Evidence

Ruby citations are to the local Ruby Asciidoctor clone, version `2.1.0.alpha.0`. Consumer
citations are to `webinertia/asciidoc-php-multi-repo`.

| Claim | Evidence |
|---|---|
| At `SAFE` and above the jail is `base_dir`. | `lib/asciidoctor/abstract_node.rb:466-475` |
| A source listing without a highlighter emits `class="language-LANG" data-lang="LANG"` with no `hljs`. | `lib/asciidoctor/converter/html5.rb:685` |
| Embedded output appends content with no article wrapper. | `lib/asciidoctor/converter/html5.rb:329` |
| `sectids` is on by default. | `lib/asciidoctor.rb:378` |
| Unsetting `sectids` disables generation. | `lib/asciidoctor/section.rb:190` |
| Section levels are stored zero-based. | `lib/asciidoctor/section.rb:46-51` |
| `idprefix` and `idseparator` default to `_`. | `lib/asciidoctor/section.rb:198-226` |
| Option attributes beat header declarations. | `lib/asciidoctor/document.rb:876-877`, `:924-926` |
| The aggregator passes the page directory as `base_dir`. | `PageRenderer.php:110` |
| The aggregator rewrites an absolute base into a cwd-relative one. | `PageRenderer.php:258-272` |
| The aggregator's pre-scan computes a second base. | `PageRenderer.php:228` |
| The pre-scan tests existence, not containment. | `PageRenderer.php:237-240` |
| `IncludeOutsideRoot` is declared and unproduced. | `src/Report/ReasonCode.php:19` |
| The report schema publishes `include-outside-root`. | `contracts/report.schema.json:97` |
| The reason-code set is closed and schema-coupled. | `src/Report/ReasonCode.php:7-12` |
| The aggregator raises through `failure()`. | `PageRenderer.php:353-355` |
| The aggregator owns the article wrapper. | `SiteConverter.php:36`, `:38`, `:71` |
| The aggregator reads `lastDocument()`. | `PageRenderer.php:114` |
| The aggregator guards the document type. | `PageRenderer.php:116-117` |
| The aggregator renders its own page shell. | `PageRenderer.php:211` |
| The TOC extractor adds one to `getLevel()`. | `TocExtractor.php:53` |
| The TOC extractor walks blocks depth-first. | `TocExtractor.php:52-56` |
| The code-block matcher does not require `hljs`. | `PageRenderer.php:373` |
| The Mermaid matcher accepts either marker. | `PageRenderer.php:381` |
| The attribute policy disables the highlighter. | `AttributePolicy.php:62` |
| The site config selects `safe`. | `site.json`, `contracts/site.schema.json:91-94` |
