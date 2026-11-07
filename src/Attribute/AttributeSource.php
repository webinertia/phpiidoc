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

namespace Phpiidoc\Attribute;

/**
 * Where an attribute's resolved value came from.
 *
 * The three sources are ordered by precedence, highest first: `Option`, then
 * `Header`, then `Default`. An attribute supplied by the render call wins over
 * one the document header declares, and both win over a built-in default.
 *
 * Ruby reaches the same ordering by keeping the render call's attributes in a
 * separate overrides map and refusing to let a header write over a name in it
 * (`lib/asciidoctor/document.rb:296-315`, `:876-877`, `:919-926`).
 *
 * @api
 */
enum AttributeSource: string
{
    /** A built-in default that no caller and no document set. */
    case Default = 'default';

    /** Set by the document header, in the body or in the header block. */
    case Header = 'header';

    /**
     * Supplied by the render call.
     *
     * Ruby calls these attribute overrides and notes that a direct assignment
     * "effectively makes the attribute a constant"
     * (`lib/asciidoctor/document.rb:296-298`).
     */
    case Option = 'option';

    /**
     * Whether this source outranks `$other`.
     */
    public function outranks(self $other): bool
    {
        return $this->precedence() > $other->precedence();
    }

    /**
     * The precedence rank, highest first.
     */
    public function precedence(): int
    {
        return match ($this) {
            self::Option  => 3,
            self::Header  => 2,
            self::Default => 1,
        };
    }
}
