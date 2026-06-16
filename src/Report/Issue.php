<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: 2025 Christian Rath-Ulrich
//
// SPDX-License-Identifier: GPL-3.0-or-later

/*
 * This file is part of the package cru/fluidlint
 *
 * Copyright (C) 2026 Christian Rath-Ulrich
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Cru\Fluidlint\Report;

final class Issue
{
    public function __construct(
        public readonly string $ruleId,
        public readonly Severity $severity,
        public readonly string $message,
        public readonly string $file,
        public readonly ?int $line = null,
        public readonly ?int $column = null,
        public readonly array $context = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'ruleId' => $this->ruleId,
            'severity' => $this->severity->value,
            'message' => $this->message,
            'file' => $this->file,
            'line' => $this->line,
            'column' => $this->column,
            'context' => $this->context,
        ];
    }
}
