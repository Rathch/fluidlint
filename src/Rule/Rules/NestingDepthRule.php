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

namespace Cru\Fluidlint\Rule\Rules;

use Cru\Fluidlint\Analysis\AstWalker;
use Cru\Fluidlint\Configuration\Configuration;
use Cru\Fluidlint\Report\Issue;
use Cru\Fluidlint\Report\Severity;
use Cru\Fluidlint\Rule\RuleInterface;
use TYPO3Fluid\Fluid\Core\Parser\ParsingState;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\ViewHelperNode;

final class NestingDepthRule implements RuleInterface
{
    public function getId(): string
    {
        return 'fluid/nesting-depth';
    }

    public function analyze(string $file, string $source, ParsingState $parsingState, Configuration $configuration): array
    {
        $walker = new AstWalker();
        $maxDepth = 0;
        $deepestLine = null;

        $walker->walk(
            $parsingState->getRootNode(),
            static function (ViewHelperNode $node, int $depth) use (&$maxDepth, &$deepestLine, $source, $file): void {
                if ($depth > $maxDepth) {
                    $maxDepth = $depth;
                    $deepestLine = self::estimateLine($source, $node->getName());
                }
            },
        );

        if ($maxDepth <= $configuration->nestingDepthWarn) {
            return [];
        }

        $severity = $maxDepth >= $configuration->nestingDepthError
            ? Severity::Error
            : Severity::Warning;

        return [
            new Issue(
                ruleId: $this->getId(),
                severity: $severity,
                message: sprintf('ViewHelper nesting depth %d exceeds threshold.', $maxDepth),
                file: $file,
                line: $deepestLine,
                context: ['depth' => $maxDepth, 'warn' => $configuration->nestingDepthWarn, 'error' => $configuration->nestingDepthError],
            ),
        ];
    }

    private static function estimateLine(string $source, string $needle): ?int
    {
        $position = strpos($source, '<f:' . $needle);
        if ($position === false) {
            return null;
        }

        return substr_count(substr($source, 0, $position), "\n") + 1;
    }
}
