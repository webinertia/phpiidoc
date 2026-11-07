<?php

declare(strict_types=1);

/**
 * This file is part of the Phpiidoc package.
 *
 * Copyright (c) 2026 Joey Smith <jsmith@webinertia.net>
 * and contributors.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Phpiidoc\Diagnostic;

use function str_starts_with;

/**
 * The stable reason codes a diagnostic carries.
 *
 * The string value of each case is the code a caller matches on. Codes are
 * contract: they are never renamed and never reused, because a caller keys
 * behaviour and reporting on them. Adding a case is a minor change; removing,
 * renaming or re-pointing one is a breaking change.
 *
 * Severity is carried by the code rather than by the report, so
 * {@see self::severity()} is the single place a code's severity is decided.
 *
 * ## The include codes diverge from Ruby permanently
 *
 * Ruby treats three include failures as non-fatal. An unresolvable target is
 * replaced with an "Unresolved directive" line and processing continues
 * (`lib/asciidoctor/reader.rb:1041`). At `SECURE` and above the directive is
 * rewritten to a link (`lib/asciidoctor/reader.rb:1048-1054`). Exceeding the
 * depth limit logs and abandons that include (`lib/asciidoctor/reader.rb:1055-1058`).
 * phpiidoc reports all three as `Error` in every mode. A rendered page with a
 * silent hole is worse than a failed build, and restoring Ruby's
 * warn-and-continue behaviour is not a bug fix to be made later.
 *
 * ## Escape detection is a permanent divergence too
 *
 * Ruby's resolver defaults `recover` to true, so an escaping path is re-rooted
 * inside the jail and then fails as a missing file
 * (`lib/asciidoctor/path_resolver.rb:384-388`); the include reader never turns
 * that off (`lib/asciidoctor/reader.rb:1263`). phpiidoc normalises the candidate
 * exactly as the resolver does, tests containment against the base directory,
 * and reports {@see self::IncludeTargetEscapesBaseDir} before any read is
 * attempted. Because the containment test precedes the existence test, a target
 * that both escapes and does not exist reports the escape. The two codes never
 * collapse into one another.
 *
 * @api
 */
enum ReasonCode: string
{
    /**
     * An include target does not exist.
     *
     * Ruby replaces the directive with an "Unresolved directive" line and
     * continues (`lib/asciidoctor/reader.rb:1041`).
     */
    case IncludeTargetNotFound = 'include_target_not_found';

    /**
     * A safe mode forbids include processing.
     *
     * Ruby rewrites the directive to a link at `SECURE` and above
     * (`lib/asciidoctor/reader.rb:1048-1054`).
     */
    case IncludeDisabledBySafeMode = 'include_disabled_by_safe_mode';

    /**
     * An include nests deeper than the configured maximum.
     *
     * Ruby logs an error and abandons that include
     * (`lib/asciidoctor/reader.rb:1055-1058`). The default maximum is 64
     * (`lib/asciidoctor/reader.rb:616`).
     */
    case IncludeDepthExceeded = 'include_depth_exceeded';

    /**
     * Include processing is disabled because the maximum depth is zero.
     *
     * Ruby treats a non-positive `max-include-depth` as "do not process
     * includes" (`lib/asciidoctor/reader.rb:616`).
     */
    case IncludeDisabledByDepthZero = 'include_disabled_by_depth_zero';

    /**
     * An include target is a URI and URI reads are not permitted.
     *
     * Ruby requires the `allow-uri-read` attribute, which only the API can set
     * (`lib/asciidoctor/document.rb:383-384`), and warns when it is absent
     * (`lib/asciidoctor/reader.rb:1252-1253`).
     */
    case IncludeUriNotAllowed = 'include_uri_not_allowed';

    /**
     * An include target resolves outside the base directory.
     *
     * Detected before resolution, never after it. See the enum docblock for why
     * this is a permanent divergence from Ruby.
     */
    case IncludeTargetEscapesBaseDir = 'include_target_escapes_base_dir';

    /**
     * A section that belongs in the table of contents has no id.
     *
     * Ids are generated from titles by default (`sectids` is on,
     * `lib/asciidoctor.rb:378`), so this arises only from `sectids!` or a
     * malformed explicit anchor (`lib/asciidoctor/section.rb:190`).
     */
    case SectionMissingId = 'section_missing_id';

    /** A block context is not recognised. */
    case UnknownBlockContext = 'unknown_block_context';

    /** A construct is recognised but not implemented in this version. */
    case UnsupportedConstruct = 'unsupported_construct';

    /** An explicit section id is already in use. */
    case DuplicateSectionId = 'duplicate_section_id';

    /** An attribute reference names an attribute that is not set. */
    case UnresolvedAttributeReference = 'unresolved_attribute_reference';

    /** An attribute is recognised but deliberately not honoured. */
    case UnsupportedAttributeIgnored = 'unsupported_attribute_ignored';

    /**
     * The severity assigned to this code.
     *
     * Every include code is an `Error` in every mode. That is the settled
     * divergence recorded in the enum docblock, expressed here so no caller can
     * downgrade it.
     */
    public function severity(): Severity
    {
        return match ($this) {
            self::DuplicateSectionId,
            self::UnresolvedAttributeReference,
            self::UnsupportedAttributeIgnored => Severity::Warning,
            default => Severity::Error,
        };
    }

    /**
     * Whether this code reports an include directive.
     */
    public function isIncludeCode(): bool
    {
        return str_starts_with($this->value, 'include_');
    }
}
