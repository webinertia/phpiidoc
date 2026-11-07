# Feature Specification: AsciiDoc Parse Phase

**Feature Branch**: `tyrsson-asciidoc-parse-contracts`

**Created**: 2026-11-06

**Status**: Draft

**Input**: User description: "Here is the part I need you to plan, spec and contract for me. The
simplest way to say it is the ascii part. Parsing the files, and preparing the data for conversion.
I need it built to contracts so that I can then wire that into the application architecture I have
planned and so that we can then consume that prepared data with laminas-view for rendering into
html."

## Scope

This feature is the AsciiDoc parse phase of phpiidoc: reading and preprocessing a source, parsing it
into the document tree, running the substitutions, and returning a document whose content a
converter renders without re-parsing anything. The conversion itself, and the laminas-view layer
above it, are out of scope. The reasoning record for every requirement below is
`docs/planning/contracts/parse-phase.md`.

The phase is expressed as eight stages. Each stage names what it consumes and what it produces.

| Stage | Consumes | Produces |
|---|---|---|
| S1 Read | a source and the parse options | a line cursor over one source |
| S2 Preprocess | the cursor | lines with includes inlined and conditionals resolved |
| S3 Header | the preprocessed cursor | document attributes, document title, header nodes |
| S4 Sections | the post-header cursor | section nodes with levels and titles |
| S5 Blocks | the section body cursor | block nodes with a content model |
| S6 Lists and tables | the block cursor | list nodes and list item nodes |
| S7 Prepare | the assembled tree | substituted content, generated section ids, resolved references |
| S8 Return | the prepared tree | the document handed to a converter |

## User Scenarios & Testing *(mandatory)*

### User Story 1 - A document with includes and conditionals parses to the lines the author meant (Priority: P1)

An author writes a document that pulls in other files and guards parts of them with `ifdef`,
`ifndef` and `ifeval`. The parse phase resolves those directives against the attributes in force at
the moment each directive is read, so the author gets the document they described rather than a
document assembled from a snapshot taken before parsing began.

**Why this priority**: Every later stage reads from this cursor. If preprocessing is wrong, the tree
is wrong, and the defect surfaces in the rendered HTML with no trace back to its cause.

**Independent Test**: A document that includes one file, defines an attribute before the include and
another inside it, and uses `ifdef` around a paragraph, parses to exactly the expected line sequence
and attribute state.

**Acceptance Scenarios**:

1. **Given** a document whose body includes a file that does not exist, **When** it is parsed,
   **Then** the directive is reported as a diagnostic and parsing continues without it.
2. **Given** an attribute is set by an attribute entry inside an included file, **When** a later
   `ifdef` in the including file tests that attribute, **Then** the test sees it.
3. **Given** nested includes deeper than the configured maximum, **When** the depth is exceeded,
   **Then** the excess include is reported and not read.
4. **Given** safe mode `secure`, **When** an include directive is reached, **Then** it is not read
   and the document reports that includes are disabled at this level.

---

### User Story 2 - A parsed document carries a complete structural tree (Priority: P1)

An author writes a document with a header, nested sections, delimited blocks, paragraphs, lists and
attribute entries. The parse phase produces the document tree the existing node contracts describe,
with each node knowing its own type, level, title, attributes and source location.

**Why this priority**: The tree is the artifact the converter consumes. A structural gap is a
rendering gap.

**Independent Test**: A document with two section levels, a delimited listing block and an unordered
list parses to a tree whose shape matches the expected node sequence at every level.

**Acceptance Scenarios**:

1. **Given** a document with a header, **When** it is parsed, **Then** the header nodes and the
   document attributes are on the document, and the body begins after the header.
2. **Given** a section title, **When** it is parsed, **Then** the section node records its level,
   its title text and its source location, and it is a child of the section that encloses it.
3. **Given** a delimited block whose terminator never appears, **When** it is parsed, **Then** the
   block is still produced and the unterminated block is reported as a warning.
4. **Given** an unordered list with nested items, **When** it is parsed, **Then** each item is a list
   item node whose parent is the list node that owns it.

---

### User Story 3 - Conversion is a tree walk, not a second parse (Priority: P1)

A converter receives a document whose block content has already been substituted. Text
substitutions, attribute references, inline macros and replacements have all been applied, section
ids have been generated, and cross references have been resolved. The converter decides only markup.

**Why this priority**: This is the acceptance criterion for the whole phase. A converter that has to
re-parse is a converter that duplicates the parse phase.

**Independent Test**: A document containing an attribute reference, a bold phrase, an inline image
macro and a cross reference to a later section converts to the expected markup with the converter
performing no text substitution of its own.

**Acceptance Scenarios**:

1. **Given** a paragraph containing `{attr}` where the attribute is defined, **When** the document is
   parsed, **Then** the paragraph content holds the resolved value and not the reference.
2. **Given** a cross reference to a section defined later in the document, **When** the document is
   parsed, **Then** the reference resolves to the target's generated id and the target's text.
3. **Given** a compound block such as a section, **When** a converter asks the section for its own
   content, **Then** the section returns an empty string and the converter walks the children.
4. **Given** a verbatim block, **When** it is parsed, **Then** only the verbatim substitution group
   has been applied, and special characters and quotes have not.

---

### User Story 4 - Problems are reported, never silently dropped (Priority: P2)

A consumer of the phase, whether the CLI or a site layer, needs to know what was wrong with a
document without reading its HTML. Every recoverable problem becomes a diagnostic with a reason
code, a severity and a source location, and the parse phase returns the document alongside them.

**Why this priority**: A silent drop turns an authoring mistake into a rendering mystery. It is P2
because it does not change the tree, only the account of how the tree was built.

**Independent Test**: A document with an unknown macro, a missing include and an unterminated
delimited block parses to a tree and reports three diagnostics with distinct reason codes and
accurate line numbers.

**Acceptance Scenarios**:

1. **Given** a recoverable problem, **When** it is encountered, **Then** a diagnostic carries a
   reason code, a severity and the location of the line that caused it.
2. **Given** a source location, **When** it is reported, **Then** it identifies the file the line
   came from, including for lines that arrived through an include.
3. **Given** a document with no problems, **When** it is parsed, **Then** no diagnostics are
   reported.

---

### User Story 5 - An included path cannot escape the document's base directory (Priority: P2)

A site operator renders documents that other people wrote. In safe mode, an include target that
resolves outside the base directory is refused, and refusal survives a symbolic link, which pure path
arithmetic cannot see.

**Why this priority**: It is a containment boundary rather than a rendering feature, but it is the
only boundary that a path-only check leaves open.

**Independent Test**: A document whose include target is a symbolic link pointing outside the base
directory is refused in safe mode and read in unsafe mode.

**Acceptance Scenarios**:

1. **Given** safe mode `safe` and an include target that leaves the base directory by counting
   parent segments, **When** the directive is read, **Then** it is refused and reported.
2. **Given** safe mode `safe` and an include target that is a symbolic link to a path outside the
   base directory, **When** the directive is read, **Then** it is refused and reported as an escape
   rather than as a missing file.
3. **Given** safe mode `unsafe`, **When** the same include target is read, **Then** it is read.

---

### User Story 6 - Every node can be traced back to its source line (Priority: P3)

When the rendered HTML is wrong, the person fixing it needs to know which line of which file produced
the node. With the source map enabled, every node carries the location it came from.

**Why this priority**: It is a debuggability feature, not a rendering feature, and it can be enabled
per parse.

**Independent Test**: A document assembled from two files parses with the source map enabled and
every node's location names the file it actually came from.

**Acceptance Scenarios**:

1. **Given** the source map is enabled, **When** a node is produced from an included file, **Then**
   its location names that file and not the document source.
2. **Given** the source map is disabled, **When** a node is produced, **Then** it still records a
   location, and the parse does not fail.

---

### Edge Cases

- A document with a byte order mark, or with lines that end in CRLF, parses as though neither were
  present.
- A document that is only a header, or only a body, or empty, parses without error and produces a
  document with the parts it has.
- An include of the document that includes it terminates instead of recursing without limit.
- An attribute entry after a blank line is body text, not an attribute; an attribute entry before
  the first blank line belongs to the header.
- A block whose terminator is longer than required still closes.
- A list item whose continuation is indented and followed by a blank line keeps its continuation.
- A macro whose target is empty is left as text rather than becoming an empty node.
- A table is recognised and preserved as a block, and its rows and cells are not parsed in this
  phase.
- A document with an unsupported directive fails loudly at the point it is read rather than being
  silently ignored.

## Requirements *(mandatory)*

### Functional Requirements

Each requirement cites its stage. The argument for each one, with the reference implementation's
file and line citations, is in `docs/planning/contracts/parse-phase.md`.

**S1 Read**

- **FR-001**: The phase MUST accept a source that is either a path or literal text, and MUST NOT
  read a path until parsing begins.
- **FR-002**: The phase MUST present a source as a line cursor that supports peeking without
  consuming, marking and restoring a position, and pushing lines back onto the front.
- **FR-003**: The cursor MUST support reading until a terminator, until a blank line, until a list
  continuation, and until a caller-supplied condition, as declared per call rather than as
  constructor state.
- **FR-004**: The cursor MUST normalise line endings and strip a leading byte order mark.

**S2 Preprocess**

- **FR-005**: The phase MUST resolve `include::[]` at the line that names it, substituting the target
  with the attributes in force at that line.
- **FR-006**: The phase MUST evaluate `ifdef`, `ifndef` and `ifeval` against the attribute store as it
  stands when the directive is read, not against a snapshot.
- **FR-007**: The phase MUST enforce a maximum include depth, defaulting to the value the reference
  implementation uses, and MUST report an include that exceeds it.
- **FR-008**: The phase MUST honour safe mode when deciding whether to process an include, and MUST
  stop processing them at `secure` and above.
- **FR-009**: The phase MUST refuse an include whose target leaves the base directory in safe mode,
  and MUST perform that check on the resolved real path as well as on the textual path.
- **FR-010**: The phase MUST report an unreadable include target as a diagnostic and continue.
- **FR-011**: The phase MUST terminate a self-referential include rather than recursing without
  limit.

**S3 Header**

- **FR-012**: The phase MUST parse the document header before the body and MUST place the title,
  the author line and the revision line on the document.
- **FR-013**: The phase MUST record header attribute entries in the attribute store, and MUST keep
  them separable from body attribute entries so they can be restored after parsing.
- **FR-014**: The phase MUST treat attribute entries after the first blank line as body content.

**S4 Sections**

- **FR-015**: The phase MUST produce a section node for each section title, recording its level, its
  title text, its source location and its attributes.
- **FR-016**: The phase MUST nest sections by level, closing sections whose level is reached or
  exceeded.
- **FR-017**: The phase MUST honour the document's section level limit when deciding whether a title
  is a section or a paragraph.
- **FR-018**: The phase MUST record a section's id when the document supplies one, and MUST leave
  generation of a missing id to the prepare stage.

**S5 Blocks**

- **FR-019**: The phase MUST recognise paragraph, literal, listing, source, quote, sidebar, example,
  open, pass, comment and table blocks, and MUST record the block's content model.
- **FR-020**: The phase MUST apply a delimited block's attribute list to the block and MUST honour
  the styles that change how its content is substituted.
- **FR-021**: The phase MUST report a delimited block whose terminator never appears, and MUST still
  produce the block.
- **FR-022**: The phase MUST record block metadata, including id, title, role and options, on the
  node rather than in its content.
- **FR-023**: The phase MUST treat a comment block as absent from the tree.
- **FR-024**: The phase MUST apply the block title, and any preceding attribute list, to the block
  that follows.

**S6 Lists and tables**

- **FR-025**: The phase MUST produce a list node and one list item node per item for unordered,
  ordered and description lists.
- **FR-026**: The phase MUST record a description list item's terms and its description, and MUST
  allow an item to have more than one term.
- **FR-027**: The phase MUST support item continuation, nested lists, and attached blocks.
- **FR-028**: The phase MUST recognise a table block, and MUST NOT parse its rows and cells in this
  phase.
- **FR-029**: The phase MUST report a callout list whose numbers do not correspond to callouts in
  the preceding block.

**S7 Prepare**

- **FR-030**: The phase MUST run the substitutions during parsing, and the document handed to
  a converter MUST hold already substituted content.
- **FR-031**: The phase MUST apply the normal substitution group to simple content, the verbatim
  group to verbatim content, and no substitution to raw content.
- **FR-032**: The phase MUST apply special character substitution and quote substitution as separate,
  orderable substitutions, and MUST preserve their order.
- **FR-033**: The phase MUST resolve attribute references, including intrinsic attributes and
  attribute references that resolve to other references.
- **FR-034**: The phase MUST resolve inline macros for images, links, cross references, footnotes and
  anchors, and MUST leave an unknown macro as text.
- **FR-035**: The phase MUST generate a section id from the converted title when the document does not
  supply one, and MUST keep generated ids unique within the document.
- **FR-036**: The phase MUST resolve a cross reference to a target that appears later in the
  document, using that target's id and text.
- **FR-037**: The phase MUST number callouts in a verbatim block and MUST record the callout list
  that consumes them.
- **FR-038**: The phase MUST apply header substitution to header content and reference-text
  substitution to reference text, and MUST NOT apply either to body content.
- **FR-039**: The phase MUST give a converter no way to ask for unsubstituted content, because after
  this phase there is none.

**S8 Return**

- **FR-040**: The phase MUST return the document with its attributes restored to the state the
  document declared, so that one parse cannot leak state into the next.
- **FR-041**: The phase MUST return the document together with the diagnostics collected during
  parsing, through the diagnostic collector the caller supplies.
- **FR-042**: The phase MUST accept the converter as an argument, because the identity of the
  converter is what the substitution stage needs, and MUST NOT read a converter from global state.
- **FR-043**: The phase MUST record a source location on every node it produces, and MUST name the
  file a line came from when the source map is enabled.
- **FR-044**: The phase MUST NOT re-parse any part of the document after the document is returned.

### Key Entities

- **Document**: the root of the tree. Carries the title, author line, revision line, attributes,
  sections, blocks, footnotes and the document-level counters. It is what a converter receives.
- **Section**: a node with a level, a title, an id, and children that are sections or blocks.
- **Block**: a node with a content model, a style, an attribute list and either prepared content or
  children. Compound blocks hold children and return no content of their own.
- **List item**: a node with text, terms and an optional description. A description list item holds
  a term list and a description.
- **Content model**: the closed set that decides how a block's content is produced: simple,
  verbatim, raw, compound, empty.
- **Source**: a path or literal text, plus the base directory and the safe mode that apply to it.
- **Reader**: the line cursor. Holds the current line, the marks taken on it, the include stack and
  the depth limit.
- **Include resolver**: turns an include target into a path and answers whether that path is
  contained by the base directory.
- **Substitution context**: the document state a substitution reads: attributes, the converter, the
  reference resolver and the footnote registry.
- **Diagnostic**: a reason code, a severity and a source location.
- **Source location**: the file, the line and the column a node or diagnostic came from.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A document parsed by this phase converts to the expected HTML with the converter
  performing zero text substitutions and zero document reads of its own.
- **SC-002**: For the parity corpus, the rendered HTML matches the reference implementation's HTML
  on DOM structure for 100 percent of the corpus, and on text content for 100 percent of it.
- **SC-003**: Every source file added by this feature reaches 100 percent line coverage and 100
  percent branch coverage, and the mutation score gate defined in the repository CI contract is met.
- **SC-004**: Every recoverable problem the reference implementation reports is reported by this
  phase with an equivalent reason code, and no problem it reports is silently dropped.
- **SC-005**: In safe mode, no include target outside the base directory is read, including through a
  symbolic link, measured by a containment test that resolves real paths.
- **SC-006**: Parsing a document twice in the same process produces identical trees and identical
  diagnostics, measured by comparing both runs.
- **SC-007**: A document the size of the reference implementation's own user manual parses within the
  response-time budget the constitution sets for typical operations.

## Assumptions

- The node contracts already in `src/` are the target of this phase and are not redesigned for their
  own sake. The two exceptions are the list item node, which the model could not express, and the
  narrowing of block content to prepared content, which the acceptance criterion forces.
- The first pass renders HTML5 only. A document is therefore prepared for one backend, and the
  substitution stage is allowed to depend on the converter's identity.
- The reference implementation for every behavioural requirement is Ruby Asciidoctor, and every such
  requirement is argued from a file and line citation in
  `docs/planning/contracts/parse-phase.md`.
- Table row and cell parsing, stem blocks, include tag filtering, manpage doctype, docinfo files,
  custom extensions, compat mode, syntax highlighting, index and bibliography catalogues, and
  non-HTML converters are out of scope for the first pass. Each omission and its consequence is
  recorded in the omission table of the reasoning record.
- Diagnostics are reported through a collector supplied by the caller, so the phase does not decide
  how a caller presents them.
- The phase does not read the filesystem for anything except include targets, and the include
  resolver is the only component that does.
