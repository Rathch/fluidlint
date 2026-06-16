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

namespace Cru\Fluidlint\Rule;

use Cru\Fluidlint\Configuration\Configuration;
use Cru\Fluidlint\Report\Issue;
use Cru\Fluidlint\Report\Severity;
use TYPO3Fluid\Fluid\Core\Parser\ParsingState;

final class RuleEngine
{
    /**
     * @param list<RuleInterface> $rules
     */
    public function __construct(
        private readonly array $rules,
    ) {
    }

    /**
     * @return list<Issue>
     */
    public function analyze(string $file, string $source, ParsingState $parsingState, Configuration $configuration): array
    {
        $issues = [];
        foreach ($this->rules as $rule) {
            if (!$configuration->isRuleEnabled($rule->getId())) {
                continue;
            }
            array_push($issues, ...$rule->analyze($file, $source, $parsingState, $configuration));
        }

        return $issues;
    }

    public static function createDefault(): self
    {
        return new self([
            new Rules\NestingDepthRule(),
            new Rules\EmptySectionRule(),
            new Rules\DuplicateSectionRule(),
        ]);
    }
}
