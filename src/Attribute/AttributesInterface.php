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
 * The resolved attribute set of a document, as a reader sees it.
 *
 * One name resolves to one value. Which value is decided by
 * {@see AttributeSource} precedence, and this interface reports both the
 * outcome and how it was reached, so a caller that pins an attribute can tell
 * whether the pin held.
 *
 * An attribute set to the empty string is set. That is how AsciiDoc expresses a
 * boolean attribute in its default on state, and Ruby relies on it throughout
 * (`lib/asciidoctor/document.rb:873`, `:878`).
 *
 * @api
 */
interface AttributesInterface
{
    /**
     * The value of the named attribute, or `$default` when it is not set.
     */
    public function get(string $name, mixed $default = null): mixed;

    /**
     * Whether the named attribute is set.
     *
     * True for an attribute whose value is the empty string, and false for one
     * that was unset.
     */
    public function has(string $name): bool;

    /**
     * Every resolved attribute, keyed by name.
     *
     * @return array<string, mixed>
     */
    public function all(): array;

    /**
     * Whether the named attribute was supplied by the render call and is
     * therefore closed to the document header.
     *
     * Ruby's equivalent is `attribute_locked?`, which asks whether the name is a
     * key in the overrides map (`lib/asciidoctor/document.rb:919-926`). A
     * header assignment to a locked name is not applied and is not an error
     * (`lib/asciidoctor/document.rb:876-877`).
     */
    public function isLocked(string $name): bool;

    /**
     * Where the named attribute's value came from, or `null` when it is not set.
     */
    public function sourceOf(string $name): ?AttributeSource;
}
