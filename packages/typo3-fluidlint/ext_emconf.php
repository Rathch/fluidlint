<?php

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

$_EXTKEY = 'fluidlint';

$EM_CONF[$_EXTKEY] = [
    'title' => 'Fluidlint',
    'description' => 'Linting, cyclomatic complexity and dead-code analysis for Fluid templates',
    'category' => 'misc',
    'author' => 'CRU',
    'state' => 'stable',
    'version' => '1.1.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-14.99.99',
        ],
    ],
];
