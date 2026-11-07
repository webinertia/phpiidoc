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
 * The severity of a {@see Diagnostic}.
 *
 * Severity belongs to the reason code, not to the individual report, so a
 * diagnostic derives it from {@see ReasonCode::severity()} rather than
 * accepting it. A diagnostic therefore cannot contradict its own code.
 *
 * The three values are contract. Changing the severity assigned to a reason
 * code is a breaking change.
 *
 * @api
 */
enum Severity: string
{
    /** The document cannot be rendered correctly. */
    case Error = 'error';

    /** The document renders, but probably not as the author intended. */
    case Warning = 'warning';

    /** Informational only. No reason code in v1 produces this severity. */
    case Info = 'info';
}
