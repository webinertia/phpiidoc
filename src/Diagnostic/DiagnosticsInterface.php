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

use Countable;
use IteratorAggregate;

/**
 * The diagnostics collected during one parse or render, in source order.
 *
 * The collection is the unit a caller reports on. Every method that returns a
 * list preserves the order in which the diagnostics were collected, so a
 * report reads top to bottom through the source.
 *
 * Implementations are not required to be immutable, but a collection reached
 * from a result or a document must not change after the call that produced it
 * returns. Two callers reading the same result see the same diagnostics.
 *
 * @extends IteratorAggregate<int, Diagnostic>
 *
 * @api
 */
interface DiagnosticsInterface extends Countable, IteratorAggregate
{
    /**
     * Every diagnostic, in source order.
     *
     * @return list<Diagnostic>
     */
    public function all(): array;

    /**
     * The diagnostics whose severity is `Error`, in source order.
     *
     * @return list<Diagnostic>
     */
    public function errors(): array;

    /**
     * The diagnostics whose severity is `Warning`, in source order.
     *
     * @return list<Diagnostic>
     */
    public function warnings(): array;

    /**
     * Whether at least one diagnostic is an `Error`.
     */
    public function hasErrors(): bool;

    /**
     * Whether at least one diagnostic carries the given code.
     */
    public function hasCode(ReasonCode $code): bool;

    /**
     * The first diagnostic in source order, or `null` when there are none.
     */
    public function first(): ?Diagnostic;
}
