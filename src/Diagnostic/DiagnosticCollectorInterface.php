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

/**
 * The sink the parse phase reports into.
 *
 * {@see DiagnosticsInterface} is a result: a caller reads it, and what it
 * returns does not change after the call that produced it returns. A phase that
 * is still running cannot report into a result, and the parse phase reports as
 * it goes: an include that escapes the jail is known before the next line is
 * read, and an unterminated delimited block is known only when the reader runs
 * out of lines. This interface is the writable half that was missing from the
 * diagnostic set, which had contracted the view and not the sink.
 *
 * ## One collector per parse, and it is not shared between parses
 *
 * A collector carries the state of exactly one parse. Reusing one across two
 * documents mixes their diagnostics and their ordering, so the parse entry point
 * creates a collector per call and hands the same instance to the reader and to
 * every parser stage. Nothing outside that call holds a reference to it, which
 * is what makes {@see self::collect()} safe to hand out.
 *
 * ## Source order is the collector's responsibility
 *
 * `DiagnosticsInterface::all()` promises source order, and the collector is
 * where that promise is kept. Reports arrive in the order the phases raise them,
 * which is source order for everything except a diagnostic about an include
 * whose own lines are still being read, and those are raised before the include
 * is opened. A collector therefore appends and never sorts.
 *
 * @api
 */
interface DiagnosticCollectorInterface
{
    /**
     * Record one diagnostic.
     *
     * The caller builds the diagnostic, including its message, because the
     * message names the construct and the collector does not know what a
     * construct is. Severity is not passed: it is derived from the code
     * ({@see ReasonCode::severity()}), so a report cannot disagree with the
     * vocabulary.
     */
    public function report(Diagnostic $diagnostic): void;

    /**
     * A view of the diagnostics recorded so far.
     *
     * The view is a snapshot and does not change when the collector is reported
     * to again, which is what lets it be returned on a finished document while
     * the collector is still in use.
     */
    public function collect(): DiagnosticsInterface;
}
