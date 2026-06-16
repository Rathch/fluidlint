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

namespace Cru\Fluidlint;

use Cru\Fluidlint\Command\ComplexityCommand;
use Cru\Fluidlint\Command\DeadCodeCommand;
use Cru\Fluidlint\Command\ScanCommand;
use Symfony\Component\Console\Application as SymfonyApplication;

final class Application extends SymfonyApplication
{
    public function __construct()
    {
        parent::__construct('fluidlint', '1.0.0');
        $this->addCommands([
            new ScanCommand(),
            new ComplexityCommand(),
            new DeadCodeCommand(),
        ]);
    }
}
