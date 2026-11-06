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
     * Reads are jailed to the base directory. Include processing is forbidden,
     * and the document may not set the attributes that affect conversion.
     */
    case Server = 10;

    /**
     * As {@see self::Server}, with `linkcss` forced on, `icons` disabled and
     * attribute values capped at 4096 bytes.
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
     * {@see self::Server} and above forbid include processing, so every include
     * directive at those levels produces `include_disabled_by_safe_mode` rather
     * than being silently dropped.
     */
    public function processesIncludes(): bool
    {
        return ! $this->isAtLeast(self::Server);
    }
}
