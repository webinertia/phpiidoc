# Contract: Attributes

**Version** 0.1 (pre-1.0)

**Status** Draft for owner review

**Consumer** `webinertia/asciidoc-php-multi-repo`, `src/Render/AttributePolicy.php`

## Scope

This contract governs document attributes: how they are supplied, the set phpiidoc recognizes,
the built-in and intrinsic defaults, the attributes phpiidoc derives from the backend, the
precedence rules when the same key arrives from more than one place, and how the resolved set is
exposed to a consumer.

It does not govern attribute references inside content (substitution semantics), counters,
conditional directives (`ifdef`, `ifndef`, `ifeval`), or the CLI flags that map onto attributes;
those are recorded in `docs/planning/phpiidoc-cli-requirements.md`.

## Surface

### Supplying attributes

Attributes arrive in `RenderOptions::$attributes` as
`array<string, string|false|null>`.

| Supplied value | Meaning |
|---|---|
| `'value'` | Set the attribute to `'value'`. |
| `''` | Set the attribute to the empty string, which is the AsciiDoc convention for a set-but-valueless attribute. |
| `false` | Unset the attribute. It is absent from the resolved set. |
| `null` | Unset the attribute. It is absent from the resolved set. |

Keys are normalized to lowercase. A key may carry the AsciiDoc `@` soft-set suffix; the suffix
is stripped before storage and the attribute is marked as soft-set.

### Reading attributes

```php
namespace Phpiidoc\Document;

interface Attributes
{
    public function has(string $name): bool;

    public function get(string $name): ?string;

    public function getOr(string $name, string $default): string;

    /** @return array<string, string> */
    public function all(): array;
}
```

`has()` is true for an attribute set to the empty string, which is how AsciiDoc distinguishes
"set" from "unset". `get()` returns `null` only when the attribute is absent. `all()` returns only
set attributes, keyed by lowercase name, in no guaranteed order.

### Built-in attributes

These are present unless the caller unsets them (`lib/asciidoctor.rb:363-387`):

| Attribute | Default |
|---|---|
| `appendix-caption` | `Appendix` |
| `appendix-refsig` | `Appendix` |
| `caution-caption` | `Caution` |
| `chapter-refsig` | `Chapter` |
| `example-caption` | `Example` |
| `figure-caption` | `Figure` |
| `important-caption` | `Important` |
| `last-update-label` | `Last updated` |
| `note-caption` | `Note` |
| `part-refsig` | `Part` |
| `prewrap` | empty |
| `sectids` | empty |
| `section-refsig` | `Section` |
| `table-caption` | `Table` |
| `tip-caption` | `Tip` |
| `toc-placement` | `auto` |
| `toc-title` | `Table of Contents` |
| `untitled-label` | `Untitled` |
| `version-label` | `Version` |
| `warning-caption` | `Warning` |

`sectnums` is a flexible attribute: it is recognized with or without a value
(`lib/asciidoctor.rb:390`).

### Intrinsic attributes

These are always set and cannot be unset by a caller (`lib/asciidoctor.rb:392-430`). They exist
so that a document can write literal characters that would otherwise be consumed as markup.

| Attribute | Value |
|---|---|
| `startsb` | `[` |
| `endsb` | `]` |
| `vbar` | `\|` |
| `caret` | `^` |
| `asterisk` | `*` |
| `tilde` | `~` |
| `plus` | `&#43;` |
| `backslash` | `\` |
| `backtick` | `` ` `` |
| `blank` | empty |
| `empty` | empty |
| `sp` | a single space |
| `two-colons` | `::` |
| `two-semicolons` | `;;` |
| `nbsp` | `&#160;` |
| `deg` | `&#176;` |
| `zwsp` | `&#8203;` |
| `quot` | `"` |
| `apos` | `'` |
| `lsquo` | `&#8216;` |
| `rsquo` | `&#8217;` |
| `ldquo` | `&#8220;` |
| `rdquo` | `&#8221;` |
| `wj` | `&#8288;` |
| `brvbar` | `&#166;` |
| `pp` | `&#43;&#43;` |
| `cpp` | `&#43;&#43;` |
| `cxx` | `C&#43;&#43;` |
| `amp` | `&` |
| `lt` | `<` |
| `gt` | `>` |

### Derived attributes

phpiidoc sets these itself.

| Attribute | Value | Source |
|---|---|---|
| `asciidoctor` | empty | `lib/asciidoctor/document.rb:373-384` |
| `asciidoctor-version` | the phpiidoc compatibility version | `lib/asciidoctor/document.rb:373-384` |
| `safe-mode-name` | `unsafe`, `safe`, `server` or `secure` | `lib/asciidoctor/document.rb:373-384` |
| `safe-mode-<name>` | empty, for the active mode | `lib/asciidoctor/document.rb:373-384` |
| `safe-mode-level` | `0`, `1`, `10` or `20` | `lib/asciidoctor/document.rb:373-384` |
| `max-include-depth` | `64` when unset | `lib/asciidoctor/document.rb:373-384` |
| `htmlsyntax` | `html` | `lib/asciidoctor/document.rb:1311-1388` |
| `backend` | `html5` | `lib/asciidoctor/document.rb:1311-1388` |
| `backend-html5` | empty | `lib/asciidoctor/document.rb:1311-1388` |
| `backend-html5-doctype-article` | empty | `lib/asciidoctor/document.rb:1311-1388` |
| `doctype` | `article` | `lib/asciidoctor/document.rb:1311-1388` |
| `doctype-article` | empty | `lib/asciidoctor/document.rb:1311-1388` |
| `filetype` | `html` | `lib/asciidoctor/document.rb:1311-1388` |
| `filetype-html` | empty | `lib/asciidoctor/document.rb:1311-1388` |
| `basebackend` | `html` | `lib/asciidoctor/document.rb:1311-1388` |
| `basebackend-html` | empty | `lib/asciidoctor/document.rb:1311-1388` |
| `basebackend-html-doctype-article` | empty | `lib/asciidoctor/document.rb:1311-1388` |
| `outfilesuffix` | `.html` | `lib/asciidoctor.rb:241-244` |
| `docdir` | the directory of `baseDir` or of the source file | `lib/asciidoctor/document.rb:393-400` |
| `docfile` | the source path | `lib/asciidoctor/load.rb:61-63` |
| `docname` | the source basename without its suffix | `lib/asciidoctor/load.rb:61-63` |
| `docfilesuffix` | the source suffix | `lib/asciidoctor/load.rb:61-63` |
| `embedded` | empty, when `headerFooter` is `false` | `lib/asciidoctor/document.rb:361-371` |
| `notitle` | empty, when `headerFooter` is `false` | `lib/asciidoctor/document.rb:361-371` |
| `localdate`, `localyear`, `localtime`, `localdatetime`, `docdate`, `docyear`, `doctime`, `docdatetime` | the current clock, unless pinned | `lib/asciidoctor/document.rb:1277-1303` |

`pagewidth` is derived only for a backend that has an entry in `DEFAULT_PAGE_WIDTHS`
(`lib/asciidoctor.rb:238`). There is no entry for `html5`, so `pagewidth` is absent.

The clock attributes are the only nondeterministic members of the set. A caller that needs
reproducible output pins them, exactly as the consumer does today.

### Attributes phpiidoc deliberately does not honour

| Attribute | Why |
|---|---|
| `copycss` | phpiidoc never writes a stylesheet. Styling belongs to the consumer. |
| `linkcss`, `stylesheet`, `stylesdir` | The fragment has no stylesheet references. |
| `source-highlighter` | No syntax highlighter is implemented in v1. Accepted and ignored with `unsupported_attribute_ignored`. |
| `icons`, `iconfont-remote`, `iconfont-name` | No icon fonts are loaded. |
| `webfonts` | No web fonts are loaded. |
| `docinfo`, `docinfodir`, `docinfosubs` | No docinfo files are read. |
| `toc`, `toclevels`, `toc-placement` | Accepted and ignored. The table of contents is returned as a value object, never rendered into the fragment. |
| `outdir`, `outfilesuffix` overrides | phpiidoc writes no files. |
| `base_dir` as an attribute | The base directory is an option, not an attribute. Setting the attribute does not change resolution. |

Every ignored attribute produces `unsupported_attribute_ignored` at `warning` severity, except
the `toc` family, which is accepted silently because the result carries a `Toc` instead.

### Precedence

Resolution order, lowest to highest:

1. Built-in attributes.
2. Attributes derived from the backend and from the source path.
3. Intrinsic attributes.
4. Option attributes from `RenderOptions::$attributes`.
5. Document header declarations.

Steps 1 to 3 cannot be unset by a caller. Steps 4 and 5 are where precedence is undecided; see
open decision D3.

Option attributes are stored as overrides. In Ruby they are treated as constants: the document
records them as `@attribute_overrides` and refuses a later `set_attribute` for a locked name
(`lib/asciidoctor/document.rb:296-315`, `:876-877`, `:924-926`). Ruby's effective order is
therefore intrinsic, then option, then header, with option winning over header.

## Guarantees to consumers

1. Every built-in attribute in the table above is present unless the caller unsets it, so a
   document that references `note-caption` resolves.
2. Every intrinsic attribute in the table above is present and cannot be unset by a document
   header, so a document cannot break its own escaping by unsetting `vbar`.
3. The backend-derived attributes are present and describe the actual backend: `backend` is
   `html5`, `basebackend` is `html`, `filetype` is `html`.
4. `safe-mode-level` and `safe-mode-name` reflect the mode actually in effect, not the mode the
   caller asked for.
5. `RenderResult::$attributes` is the final resolved set, identical to
   `$result->document->getAttributes()`.
6. The clock attributes are present unless pinned. A consumer that pins them gets deterministic
   output; a consumer that does not gets the current clock.
7. Unsetting an attribute is expressed as `false` or `null`, and the result is the attribute
   being absent, not the attribute being empty.
8. Adding a built-in attribute is a minor change. Removing one, changing one, or changing a
   precedence rule is a breaking change.

## Non-guarantees

- The order of keys in `all()`.
- Attribute reference substitution inside content beyond the intrinsics listed.
- Counters and their values.
- Conditional directives.
- The exact wording of `unsupported_attribute_ignored` messages.
- Attributes set by extensions. There is no extension API in v1.
- Compatibility with any Asciidoctor version beyond `2.1.0.alpha.0` for the built-in set.

## Stability

This contract is pre-1.0. Adding an attribute or an intrinsic is a minor change. Removing or
renaming one, changing a default value, or changing the precedence order is a breaking change.

The deliberately-unhonoured list may shrink in a minor release, because honouring an attribute
that was previously ignored only changes output for documents that set it. It may not grow
silently for an attribute that already worked.

## Open owner decisions

### D3. Attribute precedence between options and the document header

Steps 4 and 5 of the resolution order are undecided.

Options:

1. **Option attributes win**, matching Ruby (`lib/asciidoctor/document.rb:876-877`,
   `:924-926`). Consequence: a caller can pin behaviour that a repository cannot override. The
   consumer pins `source-highlighter` off and gets that guarantee for every repository. A
   repository that sets `:idprefix:` in its header is ignored.
2. **Document header wins.** Consequence: a repository controls its own rendering, but a caller
   cannot pin anything, and every consumer-side policy attribute becomes advisory.

Two concrete cases decide this, and both are visible in the current consumer:

- `source-highlighter`. The consumer sets it to `false` so the site highlights client-side
  (`AttributePolicy.php:62`). If the header wins, a repository that enables a highlighter gets
  highlighted Mermaid source, and the highlighting spans break the diagram loader that reads the
  raw text.
- `idprefix`, `idseparator`, `sectids`. The consumer sets none of them and relies on the
  defaults. If the header wins, a repository that sets `:idprefix:` changes every id in that
  repository, and every TOC link and cross-page anchor in it. The consumer would need a
  validation pass that rejects a header setting any of the three.

Recommendation: option attributes win, matching Ruby. It makes caller policy authoritative and
keeps repository headers from silently changing navigation. A repository that genuinely needs a
different `idprefix` can be accommodated later by an explicit per-repository option, which is a
visible change, rather than by an invisible header.

## Evidence

All citations are to the local Ruby Asciidoctor clone, version `2.1.0.alpha.0`.

| Claim | Ruby evidence |
|---|---|
| The built-in attribute set and its defaults. | `lib/asciidoctor.rb:363-387` |
| `sectnums` is a flexible attribute. | `lib/asciidoctor.rb:390` |
| The intrinsic attribute set and its values. | `lib/asciidoctor.rb:392-430` |
| Option attributes are normalized: lowercase keys, `@` soft-set suffix, `!` unset prefix and suffix. | `lib/asciidoctor/document.rb:296-315` |
| Option attributes are stored as overrides and are locked against later writes, so they beat header declarations. | `lib/asciidoctor/document.rb:876-877`, `:924-926` |
| The `asciidoctor`, `asciidoctor-version`, `safe-mode-*`, `max-include-depth` and `allow-uri-read` intrinsics. | `lib/asciidoctor/document.rb:373-384` |
| `base_dir` becomes `docdir`. | `lib/asciidoctor/document.rb:393-400` |
| `backend` and `doctype` are coerced from options. | `lib/asciidoctor/document.rb:403-409` |
| Backend-derived attributes: `htmlsyntax`, `backend-*`, `doctype-*`, `filetype-*`, `basebackend-*`, `pagewidth`. | `lib/asciidoctor/document.rb:1311-1388` |
| There is no `pagewidth` default for html, so the attribute is absent. | `lib/asciidoctor.rb:238`, `lib/asciidoctor/document.rb:1311-1388` |
| The default output extension for html is `.html`. | `lib/asciidoctor.rb:241-244` |
| `docfile`, `docdir`, `docname` and `docfilesuffix` come from the input path. | `lib/asciidoctor/load.rb:61-63` |
| Embedded mode sets `embedded` and `notitle`. | `lib/asciidoctor/document.rb:361-371` |
| The clock attributes and the `SOURCE_DATE_EPOCH` override. | `lib/asciidoctor/document.rb:1277-1303` |
| An attribute set to the empty string counts as set. | `lib/asciidoctor/document.rb:876-926` |
| The `toc` family is read only when deciding to emit a rendered TOC. phpiidoc never emits one. | `lib/asciidoctor/converter/html5.rb:322-327` |
| A source block language comes from the block attribute, falling back to `source-language`. | `lib/asciidoctor/parser.rb:823-832` |
| Consumer evidence: the consumer pins `source-highlighter` off and six clock attributes. | `asciidoc-php-multi-repo/src/Render/AttributePolicy.php:56-67` |
