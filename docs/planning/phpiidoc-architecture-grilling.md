# phpiidoc architecture: grilling results

Status: architecture settled, four questions parked pending the scaffold.
Source: nine rounds of grilling plus a read-only audit of both implementations.
Purpose: durable record so these answers survive session compaction and are available
as SpecKit input.

Nothing in this document is a plan. It records decisions and evidence only.

---

## 1. Authority: what is the specification and what is not

**Ruby is the specification.** `tyrsson/asciidoctor` is the oracle and the only
authority on correct output. Where Ruby and any other implementation disagree, there
is no discussion.

- Local clone: `C:\Users\jsmit\github.com\tyrsson\asciidoctor`
- Pinned at `dc37f7ca8c0852abf4408cf3c6ae1c9b5ec30d01` (upstream `main`)
- `VERSION = '2.1.0.alpha.0'` (unreleased alpha, and unchanged on upstream)
- The fork had **zero unique commits**; it was a pure mirror 98 commits behind. It has
  been fast-forwarded and pushed, so fork and upstream now agree.

**`webinertia/asciidoc-php` is one possible interpretation.** It was produced by a
much less capable agent and is consulted, never trusted.

- Its tests are self-referential: the same agent wrote the implementation and the
  assertions, so a green suite proves the code does what that agent believed, not that
  it is correct. Green there means nothing.
- It is useful for exactly two things: a map of where the AsciiDoc spec is hard (its
  file sizes are a failure inventory, not a completeness measure), and worked examples
  of how a naive translation goes wrong.
- Nothing in it is trusted by default, including the converter contract. That contract
  is keepable only because it copies Ruby's, not because asciidoc-php wrote it.

---

## 2. Hard constraints (owner, stated as WILL BE)

- Html5 rendering **will be** handled by `laminas-view`.
- Execution scaffolding **will be** provided by `webware/message-bus`.
- The system **will be** built to interfaces and their contracts.
- `laminas-config-aggregator` **will** handle configuration aggregation.
- `laminas-servicemanager` **will be** the PSR-11 container and will manage service
  creation context.
- Rebuild using the components already in the dependency list.
- Everything not listed above is open to discussion and compromise.

---

## 3. Architecture

### Layers

A layer is a subsystem (Reader, Parser, Substitutor, Converter, and so on). Reader,
Parser and Converter are not the whole list. An AST and a document model will be
required, built to interfaces.

### Execution flow

```
webware-console entry
  -> CLI command executed
  -> message bus middleware pipeline
  -> PSR-14 dispatch from pre middleware
  -> pipeline stages (Reader, Parser, Substitutor, ...)
  -> command handler
  -> converter strategy
  -> rendered HTML document
     or a laminas-view model seeded with the rendered HTML when handing to the site builder
```

### Pipeline versus events

- The middleware pipeline is the execution pipeline.
- PSR-14 events are for cross-cutting concerns and side-channel communication.
- **Any layer may dispatch events.**
- **Templates are the one place events are forbidden** during rendering. The exception
  is a listener that is itself doing the rendering, which is not how laminas-view
  works.
- View helpers are classes, can have their own factories, and may in principle depend
  on the dispatcher.

### Configuration

Standard laminas/mezzio aggregation: service mappings and factories, the laminas-view
helper plugin manager configuration, and the template map that helpers render for node
types, blocks, or whatever the node model ends up being called.

### Converter selection

The bus handler strategy selects which converter strategy is called. There is no
`CompositeConverter`-style decorator wrapping the seam; the converter strategy owns
`laminas-view`.

### The two seams

1. Document model to converter.
2. Converter output to the site generator (the collaborator).

The second seam is the deliverable's center of gravity: a standard way for phpiidoc to
hand its rendered presentation data to the collaborator or collaborators.

---

## 4. Scope and parity

- **All AsciiDoc writing features at 100 percent.** Admonitions, macros, and everything
  else a writer can type must work.
- The only accepted parity loss is **which converter strategies ship in v1**.
- **Html5 is the only hard requirement for v1.** Its completeness therefore sets v1's
  size.
- Ruby core ships `html5`, `docbook5` and `manpage`. There is no PDF in core; that is a
  separate Prawn gem.
- **Multi-repo is not AsciiDoc and is not in v1.** It builds on top of phpiidoc.
  Careful planning now should make it much simpler later without adding v1 scope.
- The plan may be considered complete while 1.0.x is still moving.

---

## 5. Parity measurement

- **Structural equality via DOM tree comparison. Not byte-identical.** Byte-identical
  is not practical.
- A normalisation layer sits between the two trees so that attribute order and similar
  incidental differences do not produce false failures. Candidates: core `ext-dom`'s
  `Dom\HTMLDocument` (PHP 8.4 HTML5 parser) or `symfony/dom-crawler`. If Symfony is
  used it is a **dev dependency only**.
- The `<meta name="generator" content="Asciidoctor ...">` element is **excluded** from
  comparison.
- Structural elements are what matter. Head links are head links and script tags are
  script tags. Incidental detail with no bearing on rendering parity is not worth
  failing a test over.

### Why byte-identical was rejected

`html5.rb` is 1385 lines and contains only 25 escaped newlines. The oracle's output
whitespace is carried by multi-line `%(...)` literals, meaning it is encoded in the
Ruby source's own line layout. Reproducing that through view templates would make every
template's incidental whitespace significant, and whitespace defects are not logic
defects, so they would not show up as coverage gaps.

---

## 6. Acceptance

- Acceptance is **per subsystem**, not per plan. Is the Runtime done? The Reader? The
  Parser? The Converter?
- "Done" means **100 percent PHPUnit line and branch coverage**, plus all required
  processing modeled.
- Branch coverage matters because the MSI depends on it: mutation testing can only
  cover what tests cover.
- **MSI 95 is a leftover artifact** of the webware-tools onboarding of the repo template
  and has been disowned. The phpiidoc constitution moves to 100 percent line and branch.
- The constitution's 300-line method ceiling and cyclomatic-complexity-under-10 limit
  are **mago defaults** and are suppressed. They impose no decomposition constraint.
  The consequence is that complexity suppression belongs in the repo's mago config as a
  standing decision, not a per-PR negotiation. The general rule that new code is never
  baselined still governs every other finding.
- Test style: `#[Test]` attribute with no `test` prefix and descriptive names, testdox
  output, and assertions carrying failure messages.
- Fixtures are **per element**, so a failure names itself.

---

## 7. Oracle, corpus and goldens

- **Ruby lives in its own Dockerfile**, not the default tooling runtime. Regenerating
  goldens means overriding compose to add Ruby back, with zero other changes.
- **Goldens are committed artifacts.** Ruby is only needed to regenerate them.
- **Corpus seeding:** harvest the maintainer-curated input and expected-HTML heredoc
  pairs out of Asciidoctor's own tests, everything reasonable to harvest for which an
  equal PHP representation can be produced. Skip the rest. The remainder is authored
  from the spec manifest.
- **Harvesting carries a triage step, not a bulk copy.**
- **Manifest:** the features are enumerated from the AsciiDoc language and syntax
  documentation, then cross-checked against what the Ruby implementation supports. Gaps
  are filled as they are reached.
- The oracle harness and corpus **may live in the phpiidoc repository** where most
  convenient for development, and can be removed just before tagging 1.0.

### Accuracy claim for the oracle

There is no ready-made corpus of standalone AsciiDoc documents. The clone contains:

- 25 test files, **41,087 lines** of Ruby test code
- only **27 `.adoc` fixture files** out of 76 fixture files
- `features/` holding four `.feature` files (`open_block`, `pass_block`,
  `text_formatting`, `xref`), so the cucumber suite is vestigial and cannot serve as
  the completeness manifest

The accuracy claim therefore lives in the 41,087 lines of tests, which hold input and
expected HTML as heredoc pairs curated by Asciidoctor's maintainers. That is a
**stronger** claim than goldens generated by running the oracle, because every
expectation has been reviewed by a human who knows the spec. It is also exactly the
shape of a golden corpus, just embedded in Ruby rather than sitting in files. Many of
those pairs exercise the Ruby API rather than a clean document conversion, which is why
harvesting requires triage.

---

## 8. Build order

1. **Scaffold first, driven entirely by the owner.** The scaffold is kept as the
   foundation, so it has to stay clean.
2. The scaffold answers most of the parked questions with high certainty.
3. Then the SpecKit architecture plan, written against the standing scaffold.
4. Then follow-on plans per subsystem. The roadmap falls out of the architecture plan
   rather than being enumerated in it.
5. The constitution rewrite to 100 percent line and branch is the first SpecKit move.

---

## 9. Parked questions

These are explicitly open. SpecKit must carry them as unknowns, not assumptions.

1. **Which components are middleware and which are middleware collaborators.** Not
   answerable by discussion; it needs the scaffold to react to.
2. **Whether a template receives a node, or a node plus already-substituted string
   content.** This is the AST question reframed for laminas-view, and it decides what
   the template map keys on.
3. **Whether Substitutors is its own subsystem with its own acceptance gate**, or a
   phase inside the Parser. Depends on the lowered representation chosen.
4. **How the bus handler selects the converter strategy and how that strategy is
   decoupled from the command handler.**

---

## 10. Audit findings (evidence the plan depends on)

1. **phpiidoc is a harness, not code.** `src/` holds exactly one file,
   `ConfigProvider.php`, returning empty `factories`. Two tests, both for it. The
   dependencies are already correct: config-aggregator, servicemanager, laminas-view,
   diactoros, psr/container, message-bus, messagebus-event, webware-console. There is
   nothing to migrate; "foundation" means infrastructure, not accumulated code.
2. **Asciidoctor has no AST.** It has a block tree whose `content` is a String, with
   substitutions applied lazily at convert time. `Inline` is a leaf holding already
   substituted text. The oracle's semantics are string-and-lazy, which is why building
   a true AST diverges from it exactly where parity is hardest to prove.
3. **The inline gap is producer-side only.** `Html5Converter` implements 13
   `convertInline*` methods and `Inline` is constructed in roughly thirty unit tests,
   but `SubstitutorsTrait` contains no node construction at all and every method returns
   `string`. The converter half exists; the producer half does not.
4. **Substitutors is the largest gap.** Ruby 1437 lines against PHP 390 across
   `SubstitutorsTrait` (248), `LinkMacros` (142) and `Rx` (120). Parity is won or lost
   there.
5. **Ruby has a converter factory and registry** (`register_for`, `CustomFactory`,
   `DefaultFactory`, lazy `PROVIDED` autoloading) plus `TemplateConverter` (253 lines)
   and `CompositeConverter` (43) on the same seam. PHP has a flat interface. The
   template path is a proven design with a reference implementation to copy.
6. **Dispatch differs.** Ruby resolves `send "convert_#{transform}"` and warns plus
   returns nil on a missing handler. asciidoc-php made dispatch static for PHPStan
   level 10. At full parity that is on the order of 100 transforms.
7. **The oracle's converter contract is the best-preserved thing in asciidoc-php.**
   `convert(AbstractNode, ?string $transform, array $opts): string` plus
   `handles(string): bool` mirrors Ruby's `convert node, transform = node.node_name,
   opts = nil`.
8. **Size ratios, used as a failure inventory rather than a completeness measure:**
   Ruby `parser.rb` 2617 vs PHP 1121; Ruby `reader.rb` 1279 vs PHP 767; Ruby
   `table.rb` 657 vs PHP 100.

---

## 11. Superseded decisions

- **D7 (rendering never dispatches) is reversed.** Any layer may dispatch events. Only
  templates are forbidden, because laminas-view does not render through listeners.
  View helpers may depend on the dispatcher.
- The earlier D1 to D9 set in `rendering-decisions.md` is historical context only. The
  user restarted the planning process, and D1 to D9 were not re-affirmed as constraints.

---

## 12. Fleet conventions that apply

- ASCII only. No em dash (U+2014) or en dash (U+2013) in authored output.
- Gates run in the tooling container (`docker compose exec tooling ...`), never native
  Windows PHP. The target environment is Linux.
- mago is invoked directly, never proxied through composer scripts.
- Guard findings are never baselineable.
- Never suppress a mago finding without asking.
- Merge commits, never squash.
- Never delete Docker volumes.
- Never reword a merged SpecKit plan.
- `#[Test]` attribute, `static::assert*`.
- Infection threads capped at 4.
