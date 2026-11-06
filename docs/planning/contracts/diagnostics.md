# Contract: Diagnostics

**Version** 0.1 (pre-1.0)

**Status** Draft for owner review

**Consumer** Any caller that handles a render failure. The retired
`webinertia/asciidoc-php-multi-repo` is one example.

## Scope

This contract governs the failure surface of the HTML5 renderer: the stable reason codes, the
severity model, the fields each diagnostic carries, the rule that decides whether a defect
raises an exception or is collected, and how a caller turns collected diagnostics into a hard
failure.

It does not govern the text of exception messages, the CLI exit codes and stderr formatting
(recorded in `docs/planning/phpiidoc-cli-requirements.md`), or the content of Ruby's log output.

This contract records one settled owner decision that diverges from Ruby. Ruby treats every
include defect as a log line and continues. phpiidoc treats every include defect as a hard
diagnostic. The divergence is deliberate and permanent. See Include diagnostics and
docs/planning/contracts/safe-mode.md.

## Surface

### Diagnostic value object

```php
namespace Phpiidoc\Diagnostic;

final readonly class Diagnostic
{
    public function __construct(
        public string $code,
        public Severity $severity,
        public string $message,
        public ?string $sourcePath = null,
        public ?int $line = null,
        public ?string $target = null,
        public ?string $correctiveAction = null,
    ) {}
}

enum Severity: string
{
    case Error = 'error';
    case Warning = 'warning';
    case Info = 'info';
}
```

| Field | Meaning | May be `null` |
|---|---|---|
| `code` | Stable machine-readable reason code from the set below. | No |
| `severity` | `error`, `warning` or `info`. | No |
| `message` | Human-readable description. Wording is not contract. | No |
| `sourcePath` | The file the defect was found in, after include resolution. | Yes, for defects that are not tied to a file. |
| `line` | One-based line number within `sourcePath`. | Yes, when the position is unknown. |
| `target` | The offending value: the include target, the block context, the section title. | Yes, when there is no offending value. |
| `correctiveAction` | One sentence telling the reader what to change. | Yes, for defects with no user action. |

### Collection

```php
namespace Phpiidoc\Diagnostic;

final readonly class Diagnostics implements \Countable, \IteratorAggregate
{
    /** @return list<Diagnostic> */
    public function all(): array;

    /** @return list<Diagnostic> */
    public function errors(): array;

    /** @return list<Diagnostic> */
    public function warnings(): array;

    public function hasErrors(): bool;

    public function hasCode(string $code): bool;

    public function first(): ?Diagnostic;
}
```

`all()` returns diagnostics in source order: by `sourcePath` in the order files were read, then
by `line`, then by the order they were raised at the same position. Parse diagnostics precede
render diagnostics at the same position.

### Reason codes

| Code | Severity | Meaning | `target` carries |
|---|---|---|---|
| `include_target_not_found` | `error` | An `include::` directive names a file that does not exist, is not a regular file, or cannot be read. | The include target as written. |
| `include_depth_exceeded` | `error` | An include chain nests deeper than `maxIncludeDepth`. | The include target that would have exceeded the limit. |
| `include_disabled_by_safe_mode` | `error` | An `include::` directive was encountered at a safe mode that does not process includes. | The include target as written. |
| `include_disabled_by_depth_zero` | `error` | An `include::` directive was encountered with `maxIncludeDepth = 0`. | The include target as written. |
| `include_uri_not_allowed` | `error` | An `include::` directive names a URI and the safe mode does not permit URI reads. | The URI. |
| `include_target_escapes_base_dir` | `error` | The resolved include path falls outside `baseDir` at a safe mode that enforces the jail. | The resolved path. |
| `section_missing_id` | `error` | A section that belongs in the table of contents has no id. | The section title. |
| `unknown_block_context` | `error` | A delimited or attributed block declares a context that the parser does not recognize. | The context token. |
| `unsupported_construct` | `error` | A construct is recognized as AsciiDoc but is not implemented in v1. | The construct name. |
| `duplicate_section_id` | `warning` | Two sections resolve to the same id. The second one keeps the id. | The duplicated id. |
| `unresolved_attribute_reference` | `warning` | An attribute reference appears in a position where it cannot be resolved and the reference is dropped. | The attribute name. |
| `unsupported_attribute_ignored` | `warning` | An option attribute is recognized as out of scope and is not applied. | The attribute name. |

Every code in this table is contract. Adding a code is a minor change. Removing a code,
renaming a code, or changing a code's severity is a breaking change.

### Severity semantics

- `error` means the rendered page is not trustworthy. Under the default `throwOnError = true`
  the call raises `RenderFailed`. Under `throwOnError = false` the result is returned and the
  caller decides.
- `warning` means the page renders and the defect is recoverable. Rendering never stops for a
  warning.
- `info` means a note about a deliberate transformation. Rendering never stops.

### Exception versus diagnostic

The rule is about where the defect lives.

1. **Environment and configuration defects raise.** The caller asked for something the renderer
   cannot do, or gave it something it cannot read. There is no page to describe. These raise
   before or at the start of parsing:
   - `SourceNotReadable` for a missing, non-regular or unreadable source file.
   - `UnsupportedOption` for a rejected `backend`, `doctype`, `headerFooter` or
     `maxIncludeDepth` value.
2. **Document defects are collected.** The source was read and parsed far enough to describe
   what is wrong with it. Every defect in the Reason codes table is collected, never raised
   during the walk.
3. **Collection then escalation.** At the end of `render()`, when `throwOnError` is `true` and
   the collection `hasErrors()`, the renderer raises `RenderFailed` carrying the whole
   collection. Every defect found in that pass is available on the exception, so a caller fixes
   them in one round rather than one per run.

`ParserInterface::parse()` never escalates. It returns the document with its diagnostics
attached.

### Reporting a collection

```php
if ($diagnostics->hasErrors()) {
    foreach ($diagnostics->errors() as $diagnostic) {
        report($diagnostic->code, $diagnostic->sourcePath, $diagnostic->line, $diagnostic->target);
    }
}
```

Turning that collection into a thrown exception belongs to the entry point that runs the parse
and the render. That entry point, and the type it throws, are deferred with the renderer
contracts, so this contract fixes collection and severity only.

### Include diagnostics

The four include codes share one policy, and that policy is settled by the owner: **an include
that cannot be inlined is a hard diagnostic in every safe mode.** Safe mode does not govern the
severity of any include diagnostic. It only governs which of the codes applies.

| Situation | Code | Ruby behaviour being rejected |
|---|---|---|
| Target missing or unreadable | `include_target_not_found` | Replaces the directive with the literal text `Unresolved directive in <path> - include::<target>[<attrlist>]` and continues (`reader.rb:1269-1272`). |
| Depth limit reached | `include_depth_exceeded` | Logs an error and continues (`reader.rb:1055-1059`). |
| Safe mode does not process includes | `include_disabled_by_safe_mode` | Rewrites the directive to `link:target[role=include]` and continues (`reader.rb:1048-1054`). |
| Depth limit set to zero | `include_disabled_by_depth_zero` | Disables include functionality entirely (`reader.rb:616-622`). |
| URI include refused | `include_uri_not_allowed` | Warns and emits a link (`reader.rb:1251-1258`). |
| Resolved path outside the jail | `include_target_escapes_base_dir` | The jail is `base_dir` (`abstract_node.rb:466-475`), but the resolver recovers by re-rooting the path inside the jail and then fails as not found (`path_resolver.rb:384-388`, `reader.rb:1263`). |

The codes are separate rather than one `include_failed` code because the causes and the
corrective actions differ. A missing file is fixed by creating or renaming it. A depth
exhaustion is fixed by flattening the include chain or raising the limit. A disabled include is
fixed by changing the safe mode or the limit. A caller that wants one switch still has one:
`hasErrors()`.

Containment is checked before resolution, not after. Ruby's resolver defaults `recover` to true,
so an escaping path is silently re-rooted inside the jail and then fails as not found
(`path_resolver.rb:384-388`); `reader.rb:1263` passes only the target name, so includes always
take the recover path. That turns a policy violation into an indistinguishable missing file.

phpiidoc therefore computes the candidate path, normalises it, tests it against `baseDir`, and
emits `include_target_escapes_base_dir` before attempting any read. The ordering is contract and
is stated identically in `docs/planning/contracts/safe-mode.md` D4 and in "Detection before resolution" there:

1. Resolve attribute references in the target.
2. Build the candidate: the target itself when absolute, otherwise the directory of the including
   file joined with the target.
3. Normalise the candidate and `baseDir` by the same rules.
4. If the candidate is not contained in `baseDir`, emit `include_target_escapes_base_dir` and
   stop.
5. Only then test existence, and emit `include_target_not_found` if it is missing.

Consequences that are contract:

- A target that both escapes the jail and does not exist reports the escape, never not-found.
  The two codes never collapse into one another.
- Containment is against `baseDir`, not the including file's directory. A target that leaves the
  page directory and stays inside `baseDir` is legal and produces no diagnostic.
- Detection applies at `Safe` and above, where the jail exists. Below `Safe` there is no jail, so
  `include_target_escapes_base_dir` cannot occur.

The corrective actions are fixed per code:

| Code | `correctiveAction` |
|---|---|
| `include_target_not_found` | Create the file at the resolved path, or correct the target. |
| `include_depth_exceeded` | Flatten the include chain, or raise `maxIncludeDepth`. |
| `include_disabled_by_safe_mode` | Lower the safe mode to `safe` or `unsafe`. |
| `include_disabled_by_depth_zero` | Raise `maxIncludeDepth` above zero. |
| `include_uri_not_allowed` | Inline the content, or lower the safe mode. |
| `include_target_escapes_base_dir` | Move the target under `baseDir`, or change `baseDir`. |

## Guarantees to consumers

1. Every diagnostic carries a `code` from the table above. A consumer may branch on `code` and
   may persist it in a build log. Codes do not change meaning.
2. Every include defect is an `error`. There is no safe mode, no option and no attribute that
   downgrades an include defect to a warning. A document with a broken include never renders
   successfully with `throwOnError = true`.
3. `sourcePath` on an include diagnostic is the file that contained the directive, not the
   target that could not be read. `target` is the target as written in the directive.
4. `line` is one-based and points at the `include::` line in `sourcePath`.
5. `RenderFailed::diagnostics()` returns the same collection the result would have carried. No
   defect is discarded on escalation.
6. The diagnostic set for a given source, options and renderer configuration is deterministic.
   Two runs produce the same codes, targets, paths and lines in the same order. Message wording
   may differ and is not contract.
7. A caller that catches `RenderFailed` can report every defect in one pass without re-running
   the renderer.
8. Removing or renaming a reason code, changing a severity, or turning an include defect into a
   warning is a breaking change.

## Non-guarantees

- Exception message text, diagnostic message text, and the exact phrasing of
  `correctiveAction`. Only the code, severity and the fields' meaning are contract.
- Column numbers. Diagnostics carry a line, not a column.
- The order of diagnostics that are not in the same file and at the same line.
- Diagnostics for defects outside the walk: a `SourceNotReadable` or `UnsupportedOption` is an
  exception and produces no diagnostics.
- The full set of `warning` and `info` codes. New recoverable codes may be added in a minor
  release.
- Whether a future backend reports the same codes. The codes are scoped to the html5 backend.

## Stability

This contract is pre-1.0. Adding a code is a minor change. Removing a code, renaming a code,
changing a code's severity, or changing the raise-versus-collect rule is a breaking change.

The always-fatal include policy is settled and permanent. It is not a bug to be reconciled with
Ruby in a later release, and a future contributor who finds Ruby's warn-and-continue behaviour
should not restore it. The rationale is in `docs/planning/contracts/safe-mode.md`.

The separate treatment of an escaping include target is settled and permanent for the same reason.
Ruby recovers such a path inside the jail and then reports it as missing
(`lib/asciidoctor/path_resolver.rb:384-388`, `lib/asciidoctor/reader.rb:1263`). phpiidoc reports
`include_target_escapes_base_dir` and never falls back to `include_target_not_found`. A future
contributor must not reintroduce the collapse.

Deprecation of a code follows the renderer's general rule: one minor release of overlap in which
the code is still emitted and documented as deprecated, then removal in the next minor release.

## Settled owner decisions affecting this contract

None recorded here. Two decisions that affect it are recorded in `docs/planning/contracts/safe-mode.md` and are
settled:

- D1b, include failure severity: every include diagnostic is an `error` in every safe mode.
- D4, escape detection timing: phpiidoc normalises the candidate, tests containment against
  `baseDir`, and emits `include_target_escapes_base_dir` before any read. The ordering in this
  contract and in `docs/planning/contracts/safe-mode.md` is identical, and the code has a producer.

## Evidence

All citations are to the local Ruby Asciidoctor clone, version `2.1.0.alpha.0`.

| Claim | Ruby evidence |
|---|---|
| A missing include target logs an error and replaces the directive with `Unresolved directive in <path> - include::<target>[<attrlist>]`. The page continues. | `lib/asciidoctor/reader.rb:1269-1272` |
| An `optional-option` include that is missing logs at info and is dropped. | `lib/asciidoctor/reader.rb:1265-1268` |
| An include chain deeper than the limit logs an error and returns. The page continues. | `lib/asciidoctor/reader.rb:1055-1059` |
| The include depth limit defaults to 64, and `0` disables include processing entirely. | `lib/asciidoctor/reader.rb:616-622` |
| At `SECURE` and above the directive is not processed and is rewritten to `link:target[role=include]`. | `lib/asciidoctor/reader.rb:1048-1054` |
| A URI include without `allow-uri-read` warns and emits a link. | `lib/asciidoctor/reader.rb:1251-1258` |
| An unresolved attribute in the target warns and produces an `Unresolved directive` replacement. | `lib/asciidoctor/reader.rb:1028-1042` |
| At `SAFE` and above the jail is `base_dir`; the resolver raises `SecurityError` for a path outside it only when `recover` is false, and defaults `recover` to true. | `lib/asciidoctor/abstract_node.rb:466-475`, `lib/asciidoctor/path_resolver.rb:384-388` |
| The resolver recovers an escaping path by re-rooting it inside the jail instead of raising, because `recover` defaults to true. | `lib/asciidoctor/path_resolver.rb:384-388` |
| The include reader passes only the target name, so includes always take the recover path. | `lib/asciidoctor/reader.rb:1263` |
| An unknown block type raises rather than continuing. | `lib/asciidoctor/parser.rb:905-909` |
| An unrecognized section raises. | `lib/asciidoctor/parser.rb:1809` |
| A duplicate section id logs a warning and the page continues. | `lib/asciidoctor/parser.rb:1668-1670` |
| A missing converter raises `NotImplementedError`. | `lib/asciidoctor/document.rb:1361` |
