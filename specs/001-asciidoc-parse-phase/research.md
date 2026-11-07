# Research: AsciiDoc Parse Phase

**Feature**: [spec.md](./spec.md) | **Plan**: [plan.md](./plan.md) | **Date**: 2026-11-06

Every decision below is stated as a choice, the reason for it, and the alternative that was rejected.
The behavioural evidence, with file and line citations against Ruby Asciidoctor, is in
`docs/planning/contracts/parse-phase.md`; this document does not repeat those citations except where
a decision turns on one.

## D5: The parse phase runs the substitutions

**Decision**: `ParserInterface::parse()` runs the substitutions, and the document it returns holds
prepared content. The converter performs no text substitution.

**Rationale**: The acceptance criterion for the phase is that a converter renders without re-parsing.
The reference implementation substitutes inside `Block#content`, which is called during conversion,
and reaches the converter through the document back-reference. Moving that work earlier is the whole
point of the phase: it is what lets a converter be a pure function of the tree.

Three consequences follow and all three are accepted:

1. `parse()` takes a converter, because inline macros produce markup and the converter decides what
   markup.
2. A document is prepared for one backend. The first pass renders HTML5 only, so this costs nothing
   today, and it is recorded as a non-goal for any second backend.
3. A forward cross reference forces content substitution to happen after the whole tree exists,
   which is why the prepare stage is separate from the parse stages.

**Alternatives considered**: substituting inside the converter, as the reference implementation does.
Rejected because it makes the converter responsible for parsing, which is the duplication this
feature exists to remove. Also considered: a lazy content model where a block substitutes on first
read. Rejected because lazy content means a node can be asked for content before the attribute store
is final, and the failure mode is a document that renders differently depending on traversal order.

## D6: Block content means the block's own prepared content

**Decision**: `BlockInterface::getContent()` returns the block's own prepared content. A compound
block returns an empty string, and the converter joins children by walking the tree.

**Rationale**: In the reference implementation `AbstractBlock#content` maps children through
`convert`, so content and conversion are the same operation. Once conversion is a tree walk, content
and children cannot both be the answer to one question. Splitting them is what makes a compound block
a container rather than a renderer.

**Alternatives considered**: keeping the reference semantics and passing a converter into
`getContent()`. Rejected because it makes every content read a conversion, so a converter cannot
inspect a block without rendering it. Also considered: dropping `getContent()` entirely. Rejected
because simple, verbatim and raw blocks have content and no children, and removing the method would
force them to pretend to have children.

## D7: The parse entry point is one call with three arguments

**Decision**: `parse(Source $source, ParseOptions $options, ConverterInterface $converter): DocumentInterface`.

**Rationale**: The three arguments are exactly the state the phase needs and nothing else. `Source`
carries the path or the text and does not read. `ParseOptions` carries safe mode, the initial
attributes, the base directory, the include depth limit and the source map flag. The converter is the
D5 consequence. Diagnostics leave through a collector held by the parser, not through the return
value, so a caller that wants them supplies a collector and a caller that does not is unaffected.

**Alternatives considered**: returning a result object holding the document and the diagnostics.
Rejected because it makes every caller unwrap a wrapper to reach the document, and because the
collector is already the write side of an interface the rest of the package reads. Also considered:
a parser constructed with its options and called with only the source. Rejected because options
belong to a parse, not to a parser, and a long-lived parser with per-parse options is a mutable
object with a lifetime question.

## D8: Include containment is the include resolver's job, and it resolves real paths

**Decision**: `IncludeResolverInterface` answers both `resolve()` and `isContained()`, and
`isContained()` compares real paths segment by segment, walking to the nearest existing ancestor when
the target does not exist. A target that escapes is reported as an escape, never as a missing file.

**Rationale**: The reference implementation's safe-mode normalisation is purely textual: it counts
`..` segments and never touches the filesystem. A symlink inside the base directory that points
outside it defeats every textual rule, because no amount of counting sees a symlink. The site
enumeration layer cannot close this, because the site never sees the resolved include target; only
the component that turns a target into a path can check the path it produced. Reporting an escape as
a missing file would also be wrong, because it hides the containment decision inside a not-found
error and an operator cannot tell an authoring mistake from an attack.

**Alternatives considered**: putting the check in the site's enumeration layer. Rejected on the
grounds above. Also considered: refusing every symlink. Rejected because a symlink that stays inside
the base directory is legitimate and the reference implementation reads it.

## D9: Safe mode stops processing includes at secure, not at server

**Decision**: `SafeMode::processesIncludes()` is `! isAtLeast(Secure)`.

**Rationale**: The reference implementation tests `doc.safe >= SafeMode::SECURE` before refusing an
include, so at `server` an include is still read and the base-directory jail is what limits it. An
earlier reading of the levels treated `server` as "includes forbidden", which would have refused
documents the reference implementation renders.

**Alternatives considered**: none. This is a correction, and the existing test that asserted the old
behaviour was corrected with it.

## D2: The shape of the converter seam stays deferred

**Decision**: not decided by this feature, and not needed by it.

**Rationale**: The seam question is about how a converter is selected and configured by a host, which
is the renderer's problem. The parse phase needs one thing from a converter, which is its identity at
substitution time, and `ConverterInterface` supplies that. Deciding the seam now would decide a
question the renderer owns, on evidence the parse phase does not have.

**Alternatives considered**: resolving it as part of this feature. Rejected as scope creep with a
real cost: a wrong seam decision would have to be unwound in the renderer, which is the layer that
consumes it.

## The reader is one cursor, not three collaborators

**Decision**: `ReaderInterface` is one interface with 27 methods, of which the parser calls 19.

**Rationale**: The reference reader is a single object holding the current line, the marks taken on
it and the include stack. Splitting it would spread one position across several objects, and every
method that advances the cursor would have to advance all of them.

**Alternatives considered**: a peeking interface, a marking interface and an include interface.
Rejected because the split removes no behaviour and adds a consistency problem. The linter's
`too-many-methods` threshold is a style default, not a design constraint, and the reader is the case
where the default does not fit.

## Per-call reading behaviour is a value, not seven booleans

**Decision**: `ReadLinesOptions` carries a terminator, a list of `ReadLinesBehaviour` cases, a
context name and a start location. `ReadLinesBehaviour` has seven cases.

**Rationale**: The reference implementation passes seven keyword arguments to `read_lines_until`.
Seven positional booleans at a call site is unreadable and unorderable, and an enum list makes the
combinations that actually occur explicit. It also keeps the constructor inside the linter's
parameter limit without suppressing anything.

**Alternatives considered**: a boolean bag or a bitmask. Rejected because both lose the case names at
the call site, and a bitmask cannot be type checked.

## Content model is a closed set

**Decision**: `ContentModel` is an enum with five cases: `Simple`, `Verbatim`, `Raw`, `Compound`,
`Empty`.

**Rationale**: The set decides which substitution group applies, and the reference implementation
decides it with a handful of predicates over style names. A closed set makes the decision total, so
the substitutor's `match` has no default arm and a new block type cannot silently fall into the wrong
group.

**Alternatives considered**: a `forContext()` helper on the enum. Rejected because it would put the
block-to-group mapping in the enum, and the mapping depends on style names the block owns.

## List items are their own node type

**Decision**: `ListItemInterface` extends `BlockInterface` and adds `getText()`, `hasText()`,
`getTerms()` and `getDescription(): ?self`.

**Rationale**: A description list item is a tuple of a term list and a description, and the existing
block model cannot express a node with two content parts. The converter iterates that tuple, so
without the node the converter would have to split text it should have been handed already split.

**Alternatives considered**: a list node with a `getItems()` accessor returning blocks. Rejected
because the reference list node is a block whose blocks are its items, so the accessor would be a
rename of `getBlocks()` and would not express the description tuple either.

## The long reference methods are decomposed by substitution family

**Decision**: the PHP substitutor is organised as one method per substitution family, plus a driver
that applies an ordered group. No PHP method is expected to exceed 300 lines.

**Rationale**: The reference implementation's `sub_macros` is 594 lines, and `apply_subs` is a single
method handling eight substitutions. The constitution caps a method at 300 lines with a complexity
limit of 10. The decomposition is along the substitution enum, so each method answers to one case and
the ordering lives in the group.

**Alternatives considered**: porting the reference structure method for method. Rejected because it
would breach the complexity gate on day one and because the reference structure exists for Ruby's
regex engine, not for a design reason.

## Parity is measured by DOM comparison

**Decision**: the parity tests compare the rendered HTML by DOM structure and text content, not by
string equality, against goldens harvested from the reference implementation's own test fixtures.

**Rationale**: Byte equality fails on whitespace that no reader can see and passes on nothing extra.
A DOM comparison asserts what the reader sees. Harvesting from the reference implementation's
fixtures rather than writing new inputs means the corpus is not biased by this implementation's
assumptions.

**Alternatives considered**: string equality. Rejected for the reason above. Also considered: writing
a fresh corpus. Rejected because a corpus written by the implementer tests the implementer's reading
of the specification rather than the reference behaviour.
