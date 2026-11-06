# Implementation Plan: AsciiDoc Parse Phase

**Branch**: `tyrsson-asciidoc-parse-contracts` | **Date**: 2026-11-06 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/001-asciidoc-parse-phase/spec.md`

## Summary

Build the AsciiDoc parse phase: read a source, preprocess includes and conditionals against the live
attribute store, parse the header, sections, blocks, lists and tables into the document tree, run the
substitution pipeline so that content is prepared rather than raw, and return a document that an
HTML5 converter renders by walking the tree. The phase is expressed as the fourteen contracts already
committed under `src/` plus the four amended ones, and the reasoning for every boundary is in
`docs/planning/contracts/parse-phase.md`.

The plan is organised as the eight stages of the specification. Each stage is a component with a
contract, a set of behaviours traced to a file and line in Ruby Asciidoctor, and an acceptance test
drawn from the reference implementation's own fixtures.

## Technical Context

**Language/Version**: PHP 8.4.1 and above, with 8.5 on the matrix. `config.platform.php` is pinned to
`8.4.99`. The package does not claim Windows support; a native Windows result is not evidence.

**Primary Dependencies**: none added by this feature. The contracts under `src/` use only the
language. `laminas/laminas-view` remains the only view-layer dependency and is consumed by the
converter, not by this phase. `webware/webware-tools` supplies the mago configuration and is a
development dependency.

**Storage**: the filesystem, read-only, and only for include targets. The include resolver is the only
component permitted to touch it. Everything else operates on strings already in memory.

**Testing**: PHPUnit 13.4 with `#[Test]`, `#[CoversClass]` and `#[CoversMethod]`, `requireCoverageMetadata`
and `failOnNotice` enabled. Infection for mutation score, capped at 4 threads. PHPBench for the parse
benchmark. Gates run only as `docker compose exec tooling ...`.

**Target Platform**: the Linux tooling container, which is the only environment where a gate result
counts.

**Project Type**: library. `Phpiidoc\` is a Composer package with no entry point of its own.

**Performance Goals**: a document the size of the reference implementation's user manual parses
within the 500ms budget the constitution sets for typical CLI operations. Memory stays proportional
to input size; the reader holds one line plus its marks, not the document.

**Constraints**: zero findings from `mago format`, `mago lint` and `mago analyze`, with no baseline
entry added for new code. 100 percent line and branch coverage per source file. Mutation score at or
above the repository CI contract. ASCII only, LF only. Merge commits, never squash.

**Scale/Scope**: about 11,200 lines of reference surface across fifteen Ruby files, of which this
phase covers about 10,000. The PHP estimate is 6,500 to 9,000 production lines and 8,000 to 12,000
test lines. Fourteen contracts and four amendments already exist; the implementation behind them does
not.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-checked after Phase 1 design.*

| Principle | How this plan satisfies it | Where the risk sits |
|---|---|---|
| I. Code Quality | Every boundary is an interface, and every interface docblock states the reference behaviour and cites it. Modern constructs throughout: enums for the closed sets (`Substitution`, `SubstitutionGroup`, `ContentModel`, `ReadLinesBehaviour`, `ReasonCode`), `readonly` for the option values, constructor promotion, named arguments at call sites. | The reference implementation has methods longer than the 300 line limit (`sub_macros` is 594 lines at `substitutors.rb:297-890`). This plan decomposes them by substitution family, so no PHP method approaches the limit. That is a deliberate structural divergence and it is recorded in `research.md`. |
| II. Testing Standards | Every stage has acceptance tests drawn from the reference implementation's fixtures. Coverage metadata is mandatory, and the mock and stub split follows the house rule. Parity is a DOM comparison, not a string comparison, so a green test cannot be a coincidence of whitespace. | The parity corpus does not exist yet. Harvesting it is task T001 and it blocks every parity test. |
| III. User Experience Consistency | The phase exposes one entry point, `ParserInterface::parse()`, and one reporting channel, the diagnostic collector. A consumer never sees a partially prepared document and never has to ask whether content is substituted. | Passing the converter into `parse()` couples the document to one backend. Accepted deliberately; recorded under Complexity Tracking. |
| IV. Performance Requirements | Single pass over each line; the reader holds a cursor rather than the document; substitutions are compiled once per parse rather than per line. A PHPBench benchmark covers the parse of a fixed corpus. | The forward-reference resolution required by FR-036 forces a second walk over section titles. It is bounded by the number of sections, not by document size, and it is recorded in `research.md`. |

No gate violation requires a justification entry. Two design decisions are unusual enough to be
recorded under Complexity Tracking below, and neither weakens a constitution principle.

## Project Structure

### Documentation (this feature)

```text
specs/001-asciidoc-parse-phase/
|-- plan.md              # This file
|-- research.md          # Phase 0 output: the decisions this plan rests on
|-- data-model.md        # Phase 1 output: the node and content shapes
|-- quickstart.md        # Phase 1 output: how to validate the phase end to end
|-- contracts/
|   `-- README.md        # Pointer to the contracts, which live in src/
|-- checklists/
|   `-- requirements.md  # Specification quality record
`-- tasks.md             # Phase 2 output
```

### Source Code (repository root)

```text
src/
|-- Attribute/           # AttributesInterface, MutableAttributesInterface, AttributeSource
|-- Converter/           # ConverterInterface, ConverterStrategyInterface
|-- Diagnostic/          # Diagnostic, DiagnosticsInterface, DiagnosticCollectorInterface,
|                        # ReasonCode, Severity, SourceLocation
|-- Node/                # NodeInterface, BlockInterface, SectionInterface, InlineInterface,
|                        # DocumentInterface, ListItemInterface, ContentModel
|-- Parser/              # ParserInterface
|-- Reader/              # ReaderInterface, ReaderFactoryInterface, ReadLinesOptions,
|                        # ReadLinesBehaviour, IncludeResolverInterface
|-- Render/              # RendererInterface, ParseOptions, RenderOptions, Source, TocEntry,
|                        # PresentationDataInterface
|-- Substitutor/         # SubstitutorInterface, Substitution, SubstitutionGroup,
|                        # SubstitutionContextInterface
`-- SafeMode.php

test/
|-- unit/                # PhpiidocTest\
|-- integration/         # PhpiidocTestIntegration\
`-- fixtures/            # the parity corpus, harvested from the reference implementation
```

**Structure Decision**: single package, one directory per collaborator, matching the layout the
existing twenty-two contracts already use. The implementation of each contract sits beside it, so a
reader who opens `src/Reader/ReaderInterface.php` finds the implementation in the same directory. No
new top-level directory is introduced.

## Phase 0: Research

`research.md` resolves the decisions this plan depends on. They are not open questions in the
specification's sense; each is a design choice with a recorded alternative, and each is forced by an
observation about the reference implementation rather than by preference.

The three facts the whole design rests on, each with its citation, are:

1. Preprocessing is not a pre-pass. The reader consults the live attribute store on every line, so
   includes and conditionals cannot be resolved before parsing begins.
2. The reference implementation has no inline tree. Substitution is string in, string out, and inline
   markup is produced by converting transient inline nodes inside the substitutor.
3. The reference implementation substitutes at convert time, reaching the converter through the
   document back-reference. This plan substitutes at parse time instead, which is what makes
   conversion a tree walk.

## Phase 1: Design

- `data-model.md` records the node and content shapes, including the two additions this phase forced:
  the list item node and the narrowing of block content to prepared content.
- `contracts/README.md` points at the fourteen contracts and four amendments in `src/`, because the
  contracts are already delivered and duplicating them here would create a second source of truth.
- `quickstart.md` is the run guide: how to run the phase against a fixture, how to compare the result
  with the reference implementation, and how to run the gates.

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|---|---|---|
| `ReaderInterface` carries 27 methods, well above the ten the linter prefers. | The reader is one cursor with a fixed, small surface that the parser drives directly. Splitting it into a peeking interface, a marking interface and an include interface would put three objects in the parser's hands where the reference implementation has one, and every split would need a shared position. The parser calls 19 of the 27; the rest exist because the reference reader publishes them and a split would have to re-export them anyway. | A narrowed interface plus three collaborators was rejected: it multiplies the objects the parser must keep consistent without removing a single behaviour, and it makes the mark and restore protocol span objects. |
| `ParserInterface::parse()` takes a `ConverterInterface`. | The substitution stage needs the converter's identity, because the reference implementation's inline macros delegate to the converter and because a document is prepared for one backend. | Two alternatives were rejected. Dropping the converter argument leaves no way to produce inline markup at parse time, which is the acceptance criterion. A document back-reference to the converter makes the node mutable after construction and puts a service inside data, which is the same coupling with worse visibility. |
| A second walk over section titles, to resolve forward cross references. | A cross reference may name a section defined later in the document, and the reference implementation resolves it because it converts after parsing everything. | Deferring the reference to convert time was rejected: it hands the converter a resolution job, which is exactly the re-parsing this phase exists to prevent. |
