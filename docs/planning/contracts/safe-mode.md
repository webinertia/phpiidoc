# Contract: Safe Mode

**Version** 0.1 (pre-1.0)

**Status** Draft for owner review

**Consumer** Any caller that chooses a safe mode or supplies a base directory. The retired
`webinertia/asciidoc-php-multi-repo` is one example.

## Scope

This contract governs the safe-mode model: the four modes and their integer levels, what each
mode permits and forbids, which default applies at each entry point, and what `baseDir` means in
relation to the mode.

It does not govern the include reason codes, which are in `docs/planning/contracts/diagnostics.md`, or the
attribute set, which is in `docs/planning/contracts/attributes.md`. It records two settled divergences from
Ruby: include failure severity and escape detection timing.

## Surface

```php
namespace Phpiidoc\Render;

enum SafeMode: int
{
    case Unsafe = 0;
    case Safe = 1;
    case Server = 10;
    case Secure = 20;
}
```

| Mode | Level | Permits | Forbids |
|---|---|---|---|
| `Unsafe` | 0 | Everything. Includes resolve anywhere on the filesystem. Attributes may override built-ins. | Nothing. |
| `Safe` | 1 | Includes resolve inside `baseDir`. Reads are jailed to `baseDir`. | Any resolved path outside `baseDir`. |
| `Server` | 10 | Reads inside `baseDir`. | Include processing. Caller-only `backend`, `copycss`, `source-highlighter`. `docdir` is cleared and `docfile` becomes relative. |
| `Secure` | 20 | Reads inside `baseDir`. | Everything `Server` forbids, plus: include processing, `linkcss` forced on, `icons` disabled, attribute values capped at 4096 bytes. |

`PARANOID` exists in Ruby but is commented out (`lib/asciidoctor.rb:89`). It is not modelled.

### Defaults per entry point

| Entry point | Default | Ruby's default |
|---|---|---|
| API: `RenderOptions::$safeMode` | `SafeMode::Safe` | `SafeMode::Secure` (`lib/asciidoctor/document.rb:319-327`) |
| CLI | `SafeMode::Unsafe` when the CLI is built | `SafeMode::Unsafe` (`lib/asciidoctor/cli/options.rb:14`) |

The API default is settled by the owner at `Safe`, a deliberate divergence from Ruby. The CLI
default is recorded here for completeness; phpiidoc has no CLI yet, and when one is built it
follows Ruby at `Unsafe` unless the CLI contract says otherwise.

### `baseDir` is the jail root

At `Safe` and above, `baseDir` is not a path hint. It is the root of a jail. Every resolved
system path is confined to it.

Ruby's resolver joins `baseDir` to a relative start and leaves `jail` unset when the mode is
below `Safe`; at `Safe` and above it sets `start ||= doc.base_dir` and `jail ||= doc.base_dir`
(`lib/asciidoctor/abstract_node.rb:466-475`). phpiidoc adopts that rule as contract.

Consequences a consumer must account for:

1. A page that includes `../shared.adoc` from a page directory leaves the jail when `baseDir` is
   that page's directory.
2. A consumer that wants includes to reach a repository's shared files must set `baseDir` to the
   repository's source root, not to the page's own directory.
3. A consumer must not rewrite `baseDir` into a process-relative path. A relative `baseDir`
   anchors the jail to the process working directory, which is not the source root.

### Detection before resolution

Ruby's resolver defaults `recover` to true, so an escaping path is re-rooted inside the jail and
then fails as a missing file (`lib/asciidoctor/path_resolver.rb:384-388`); the include reader
passes only the target name, so includes always take that path (`lib/asciidoctor/reader.rb:1263`).
The result is that a policy violation and a typo produce the same message.

phpiidoc does not recover. At `Safe` and above, every include target is normalised, tested for
containment against `baseDir`, and reported as `include_target_escapes_base_dir` before any read
is attempted. This is settled by D4.

The order is fixed and is contract:

1. Take the target as written and resolve attribute references in it.
2. If the target is absolute, the candidate is the target. Otherwise the candidate is the
   directory of the including file joined with the target (`lib/asciidoctor/reader.rb:1262-1263`).
   The including file's directory, not `baseDir`, is the start for a relative target.
3. Normalise the candidate as specified below.
4. If the normalised candidate is not contained in the normalised `baseDir`, emit
   `include_target_escapes_base_dir` and stop. No read is attempted.
5. Only then test whether the target exists, and report `include_target_not_found` if it does not.

Because step 4 precedes step 5, a target that both escapes the jail and does not exist reports the
escape. The two conditions never collapse into one another.

#### Normalisation is contract

The containment test must normalise exactly as the resolver does, or the two disagree on some
input: the check fires on a legal include, or misses an illegal one. The following rules are
therefore part of this contract, not an implementation detail.

1. **Separators.** Backslash is replaced by forward slash before anything else
   (`lib/asciidoctor/path_resolver.rb:245-251`).
2. **Partitioning.** Repeated slashes collapse, the path is split on `/`, and empty segments and
   `.` segments are dropped (`lib/asciidoctor/path_resolver.rb:289-335`).
3. **Root detection.** A path is absolute if it starts with `/`, or, on Windows, with a drive
   letter or UNC prefix (`lib/asciidoctor/path_resolver.rb:114`, `:146-148`).
4. **Parent references.** `..` removes the preceding segment. It never removes a segment that
   belongs to the root (`lib/asciidoctor/path_resolver.rb:261-272`, `:431-445`).
5. **Containment.** The candidate is inside `baseDir` when it equals `baseDir`, or when it starts
   with `baseDir` followed by `/` (`lib/asciidoctor/path_resolver.rb:204-212`). A prefix match
   without the separator is not containment, so `/docs-private` is not inside `/docs`.
6. **Windows roots.** When the candidate's root differs from the jail's root, the path is outside
   the jail regardless of segment arithmetic (`lib/asciidoctor/path_resolver.rb:418-426`). A
   different drive letter is an escape, never a relative path.

Both sides of the comparison are normalised by the same rules. Comparing a normalised candidate
against a raw `baseDir`, or the reverse, is a defect.

Containment is against `baseDir`, never against the including file's directory. A target that
leaves the page directory and stays inside `baseDir` is the ordinary case that `baseDir` exists to
permit: `include::../shared/attributes.adoc[]` from `docs/guide/page.adoc` is legal when `baseDir`
is the repository root. "Escapes the page directory" and "escapes the jail" are different
conditions, and only the second is a diagnostic.

## Guarantees to consumers

1. At `Safe` and above, no read happens outside `baseDir`. A path that would escape is refused
   and reported, not silently re-rooted.
2. At `Safe` and above, includes are processed. `Safe` is the lowest mode that both confines
   reads and inlines includes.
3. `RenderOptions::$safeMode` defaults to `SafeMode::Safe`, so a caller that does not set a mode
   gets working includes inside a jail.
4. The mode actually in effect is visible on the result as `safe-mode-name` and
   `safe-mode-level`.
5. A mode that disables includes produces a hard `include_disabled_by_safe_mode` diagnostic for
   every include directive, never a link and never a silent omission.
6. A consumer that passes a mode explicitly gets exactly that mode. No mode is silently upgraded
   or downgraded.
7. At `Safe` and above, an escaping include target produces `include_target_escapes_base_dir`,
   never `include_target_not_found`, even when the target also does not exist.
8. The containment test uses the same normalisation as path resolution, so a legal include is
   never refused and an illegal one is never admitted.
9. Changing a mode's permissions, changing a default, or changing the meaning of `baseDir` is a
   breaking change.

## Non-guarantees

- Enforcement against a hostile document. Safe mode is a guardrail against mistakes, not a
  sandbox against an adversary who controls the process.
- Symlink resolution semantics beyond "the resolved path must be inside `baseDir`".
- Network access. phpiidoc performs no network reads in any mode.
- Ruby's `PARANOID` mode.
- The CLI surface, which is contracted separately.
- The exact wording of refusal messages.

## Stability

This contract is pre-1.0. Adding a mode is a minor change. Changing a mode's permissions,
changing a default, or changing the jail root definition is a breaking change.

The include divergence is permanent. Ruby treats three include failures as non-fatal
(`lib/asciidoctor/reader.rb:1041`, `:1048-1054`, `:1055-1058`); phpiidoc treats all three as hard
errors in every mode. A future contributor who finds Ruby's warn-and-continue behaviour must not
restore it. The rationale is that a rendered page with a silent hole is worse than a failed
build, and the consumer currently compensates with a regex pre-scan it wants to delete.

The escape divergence is permanent as well, and is a consequence of D4. Ruby recovers an escaping
path by re-rooting it inside the jail, because `recover` defaults to true
(`lib/asciidoctor/path_resolver.rb:384-388`), and the include reader never turns that off
(`lib/asciidoctor/reader.rb:1263`). phpiidoc refuses the path and reports
`include_target_escapes_base_dir` instead. Restoring Ruby's recovery is not a bug fix to be made
later; it would make a policy violation indistinguishable from a typo and leave the reason code
with no producer.

## Settled owner decisions

### D1. API safe-mode default is `Safe`

Decided. `RenderOptions::$safeMode` defaults to `SafeMode::Safe`. Ruby's API default is
`SafeMode::Secure` (`lib/asciidoctor/document.rb:319-327`); phpiidoc does not match it.

Reasoning:

- `Secure` cannot render a documentation site at all. Include directives are never processed and
  are rewritten to `link:target[role=include]` (`lib/asciidoctor/reader.rb:1048-1054`), so every
  include becomes a hyperlink instead of inlined content. `linkcss` is forced on, `icons` is
  disabled, and attribute values are capped at 4096 bytes
  (`lib/asciidoctor/document.rb:422-429`).
- `Unsafe` has no jail, so a page can read any file the process can read.
- `Safe` is the only mode that both inlines includes and confines reads to `baseDir`
  (`lib/asciidoctor/abstract_node.rb:466-475`).
- Ruby's own docblock recommends `:safe` or `:unsafe` to enable all of Asciidoctor's features
  (`lib/asciidoctor/document.rb:12-16`).
- It matches what every consumer path already uses: `site.json` sets `"safeMode": "safe"`,
  `contracts/site.schema.json:91-94` enumerates the four strings with default `safe`, and
  `PageRenderer::safeMode()` falls back to `SafeMode::SAFE` (`PageRenderer.php:444-450`). No
  consumer change is needed for the default.

### D1a. Hazard: a missing mode under Ruby's default

Recorded because it can bite a consumer phpiidoc has not been written against yet. Ruby's
`Secure` default is the one safe-mode choice that fails silently: a caller who omits the mode
gets includes rewritten to links with no diagnostic, and the page renders with holes. That is
the failure this contract's `Safe` default prevents.

### D1b. Include failure severity

Settled and recorded in `docs/planning/contracts/diagnostics.md`. All three of Ruby's non-fatal include paths
become hard diagnostics in every mode. Safe mode governs which code applies, never the severity.

### D4. Escape detection timing

Decided: phpiidoc detects the escape before resolution and gives it its own reason code. It does
not match Ruby's recovery.

Ruby's resolver defaults `recover` to true, so an escaping path is re-rooted inside the jail and
then fails as a missing file (`lib/asciidoctor/path_resolver.rb:384-388`). The include reader
passes only the target name, so includes always take that path
(`lib/asciidoctor/reader.rb:1263`). Ruby's own output therefore cannot distinguish a policy
violation from a typo.

phpiidoc normalises the candidate, tests containment against `baseDir`, emits
`include_target_escapes_base_dir`, and never attempts the read. The reason code has a producer, a
consumer's report schema advertises a condition that can actually occur, and a containment
regression fails a test instead of silently rendering.

The cost is one containment check per include and a diagnostic that differs from Ruby's for the
same input. That is accepted. A reason code with no producer is worse than no reason code.

This decision obliges three further things, all specified above in "Detection before
resolution":

- The escape test normalises the candidate exactly as the resolver does. The normalisation rules
  are listed there as contract.
- Containment is measured against `baseDir`, not against the page directory. Leaving the page
  directory while staying inside `baseDir` is legal and is the reason `baseDir` exists.
- A target that both escapes and does not exist reports `include_target_escapes_base_dir`, never
  `include_target_not_found`. The two never collapse.

## Evidence

All citations are to the local Ruby Asciidoctor clone, version `2.1.0.alpha.0`.

| Claim | Ruby evidence |
|---|---|
| `UNSAFE = 0`, `SAFE = 1`, `SERVER = 10`, `SECURE = 20`. | `lib/asciidoctor.rb:56`, `:61`, `:69`, `:81` |
| `PARANOID` is commented out. | `lib/asciidoctor.rb:89` |
| The API safe-mode default is `SECURE`. | `lib/asciidoctor/document.rb:319-327` |
| The CLI safe-mode default is `UNSAFE`. | `lib/asciidoctor/cli/options.rb:14` |
| Below `SAFE`, a relative start is joined to `base_dir` and no jail is set; at `SAFE` and above, `start` defaults to `base_dir` and `jail` is `base_dir`. | `lib/asciidoctor/abstract_node.rb:446-476`, especially `:466-475` |
| The resolver recovers an escaping path by re-rooting it, because `recover` defaults to true. | `lib/asciidoctor/path_resolver.rb:384-388` |
| The include reader passes only the target name, so includes take the recover path. | `lib/asciidoctor/reader.rb:1263` |
| A relative include target starts at the directory of the including file, not at `base_dir`. | `lib/asciidoctor/reader.rb:1262-1263` |
| Normalisation replaces backslashes with forward slashes. | `lib/asciidoctor/path_resolver.rb:245-251` |
| Normalisation collapses repeated slashes, drops empty segments and drops `.` segments. | `lib/asciidoctor/path_resolver.rb:289-335` |
| Normalisation resolves `..` by removing the preceding segment. | `lib/asciidoctor/path_resolver.rb:261-272`, `:431-445` |
| Absolute path detection accepts a leading slash, a drive letter or a UNC prefix on Windows. | `lib/asciidoctor/path_resolver.rb:114`, `:146-148` |
| Containment is equality or a prefix followed by a slash. | `lib/asciidoctor/path_resolver.rb:204-212` |
| A candidate whose root differs from the jail root is outside the jail regardless of segments. | `lib/asciidoctor/path_resolver.rb:418-426` |
| At `SERVER` and above, `copycss`, `source-highlighter` and `backend` become caller-only, `docdir` is cleared and `docfile` is made relative. | `lib/asciidoctor/document.rb:411-421` |
| At `SECURE`, `max-attribute-value-size` defaults to 4096, `linkcss` is forced on and `icons` is disabled. | `lib/asciidoctor/document.rb:422-429` |
| Below `SERVER`, `user-home` is set. | `lib/asciidoctor/document.rb:431` |
| At `SECURE` and above, include directives are rewritten to links rather than processed. | `lib/asciidoctor/reader.rb:1048-1054` |
| A missing include target becomes literal `Unresolved directive` text and the page continues. | `lib/asciidoctor/reader.rb:1041`, `:1269-1272` |
| An exceeded include depth logs an error and the page continues. | `lib/asciidoctor/reader.rb:1055-1059` |
| The include depth limit defaults to 64 and `0` disables includes. | `lib/asciidoctor/reader.rb:616-622` |
| Ruby recommends `:safe` or `:unsafe` to enable all features. | `lib/asciidoctor/document.rb:12-16` |
| Consumer evidence: `site.json` sets the mode to `safe`. | `asciidoc-php-multi-repo/site.json` |
| Consumer evidence: the site schema enumerates the four modes and defaults to `safe`. | `asciidoc-php-multi-repo/contracts/site.schema.json:91-94` |
| Consumer evidence: the renderer falls back to `SafeMode::SAFE`. | `asciidoc-php-multi-repo/src/Render/PageRenderer.php:444-450` |
