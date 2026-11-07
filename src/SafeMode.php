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

namespace Phpiidoc;

/**
 * The safe-mode levels, at the integer values Ruby assigns them.
 *
 * The integer values are contract, not an implementation detail. They order the
 * modes, and a caller compares levels with {@see self::isAtLeast()} instead of
 * matching case by case.
 *
 * Ruby defines `UNSAFE = 0` and `SAFE = 1` (`lib/asciidoctor.rb:56`, `:61`),
 * `SERVER = 10` (`lib/asciidoctor.rb:69`) and `SECURE = 20`
 * (`lib/asciidoctor.rb:81`). Ruby's `PARANOID` level is commented out
 * (`lib/asciidoctor.rb:89`) and is deliberately not modelled here.
 *
 * The API default is {@see self::Safe}, a settled divergence from Ruby's
 * `Secure` default. Ruby's default is read from the `safe:` option at
 * `lib/asciidoctor/document.rb:319-327`.
 *
 * @api
 */
enum SafeMode: int
{
    /**
     * Nothing is forbidden. Includes resolve anywhere the process can read, and
     * a document header may override built-in attributes.
     */
    case Unsafe = 0;

    /**
     * Reads are jailed to the base directory and include directives are
     * processed. This is the only level that does both.
     *
     * Ruby sets `jail ||= doc.base_dir` at this level and above, and leaves the
     * jail unset below it (`lib/asciidoctor/abstract_node.rb:466-475`).
     */
    case Safe = 1;

    /**
     * Reads are jailed to the base directory and include processing is still
     * performed, with every include confined to the jail.
     *
     * Ruby stops processing includes only at {@see self::Secure}
     * (`lib/asciidoctor/reader.rb:1050`), so at this level an include is read
     * normally and only the jail limits where from. phpiidoc's earlier reading of
     * this level, that includes were forbidden here, was wrong.
     */
    case Server = 10;

    /**
     * As {@see self::Server}, and include directives are no longer processed.
     *
     * Ruby does not drop them: it rewrites the directive into a link to the
     * target so the reader can still follow it by hand
     * (`lib/asciidoctor/reader.rb:1050-1054`). phpiidoc reports
     * `include_disabled_by_safe_mode` instead, because a rewritten line is a
     * silent hole in the rendered page that no build fails on. `linkcss` is
     * forced on, `icons` is disabled and attribute values are capped at 4096
     * bytes.
     */
    case Secure = 20;

    /**
     * The mode name Ruby publishes as the `safe-mode-name` attribute.
     *
     * Ruby lowercases the constant name (`lib/asciidoctor.rb:91`) and publishes
     * it beside `safe-mode-level` (`lib/asciidoctor/document.rb:376-378`).
     */
    public function label(): string
    {
        return match ($this) {
            self::Unsafe => 'unsafe',
            self::Safe   => 'safe',
            self::Server => 'server',
            self::Secure => 'secure',
        };
    }

    /**
     * Whether this level is at least as restrictive as `$other`.
     */
    public function isAtLeast(self $other): bool
    {
        return $this->value >= $other->value;
    }

    /**
     * Whether resolved system paths are confined to the base directory.
     *
     * This is the level test Ruby performs before deciding whether to pass a
     * jail to the path resolver (`lib/asciidoctor/abstract_node.rb:466-475`).
     */
    public function confinesReads(): bool
    {
        return $this->isAtLeast(self::Safe);
    }

    /**
     * Whether `include::[]` directives are processed.
     *
     * Only {@see self::Secure} and above stop it, which is Ruby's test
     * (`lib/asciidoctor/reader.rb:1050`). At {@see self::Server} an include is
     * still read, and the jail is what limits it, so an earlier reading of this
     * level as "includes forbidden" was wrong.
     *
     * Where Ruby rewrites the directive into a link, phpiidoc reports
     * `include_disabled_by_safe_mode`; see {@see self::Secure}.
     */
    public function processesIncludes(): bool
    {
        return ! $this->isAtLeast(self::Secure);
    }
}
