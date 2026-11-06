# Contract: Safe Mode

**Version** 0.1 (pre-1.0)

**Status** Draft for owner review

**Consumer** `webinertia/asciidoc-php-multi-repo`, `src/Render/PageRenderer.php`,
`contracts/site.schema.json`, `site.json`

## Scope

This contract governs the safe-mode model: the four modes and their integer levels, what each
mode permits and forbids, which default applies at each entry point, and what `baseDir` means in
relation to the mode.

It does not govern the include reason codes, which are in `contracts/diagnostics.md`, or the
attribute set, which is in `contracts/attributes.md`. It records one settled divergence from Ruby
and one open decision.

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

phpiidoc resolves the path, tests containment against `baseDir`, and emits
`include_target_escapes_base_dir` before any read is attempted. The two conditions are never
conflated. See open decision D4.

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
7. Changing a mode's permissions, changing a default, or changing the meaning of `baseDir` is a
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

Settled and recorded in `contracts/diagnostics.md`. All three of Ruby's non-fatal include paths
become hard diagnostics in every mode. Safe mode governs which code applies, never the severity.

## Open owner decisions

### D4. Escape detection timing

phpiidoc currently specifies detection before resolution: compute the resolved path, test it
against `baseDir`, emit `include_target_escapes_base_dir`, and never attempt the read. Ruby does
the opposite and recovers, which is why an escape and a typo are indistinguishable in Ruby
output.

Options:

1. **Detect before resolution**, recommended. Consequence: `include_target_escapes_base_dir`
   becomes reachable, the consumer's report code gets a producer, and a containment bug fails a
   test. Cost: one extra containment check per include, and a slightly different diagnostic than
   Ruby's for the same input.
2. **Match Ruby and recover.** Consequence: an escape is reported as a missing file. The
   distinct code stays unreachable, and a containment regression is invisible.

Recommendation: option 1. A reason code with no producer is worse than no reason code, because a
consumer's report schema advertises a condition that no run can ever produce, and no test can
fail when containment breaks.

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
