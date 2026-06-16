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

namespace Cru\Fluidlint\Analysis;

use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\BooleanNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\NodeInterface;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\NumericNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\ObjectAccessorNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\RootNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\TextNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\ViewHelperNode;

final class ArgumentExtractor
{
    public function __construct(
        private readonly ExpressionEvaluator $expressionEvaluator = new ExpressionEvaluator(),
    ) {
    }

    public function scalarArgument(ViewHelperNode $node, string $name): ?string
    {
        $value = $this->extractLiteralValue($node, $name);
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string)$value;
    }

    public function extractLiteralValue(ViewHelperNode $node, string $name): mixed
    {
        $arguments = $node->getArguments();
        if (!isset($arguments[$name])) {
            return null;
        }

        return $this->resolveLiteral($arguments[$name]);
    }

    public function resolveLiteral(mixed $value): mixed
    {
        if (is_scalar($value) || $value === null) {
            return $value;
        }

        if ($value instanceof TextNode) {
            return $value->getText();
        }

        if ($value instanceof NumericNode) {
            return $value->getValue();
        }

        if ($value instanceof BooleanNode) {
            return $this->resolveBooleanNode($value);
        }

        if ($value instanceof RootNode) {
            $parts = [];
            foreach ($value->getChildNodes() as $child) {
                if ($child instanceof TextNode) {
                    $parts[] = $child->getText();
                } elseif ($child instanceof ObjectAccessorNode) {
                    return null;
                } elseif ($child instanceof NodeInterface) {
                    $resolved = $this->resolveLiteral($child);
                    if ($resolved === null) {
                        return null;
                    }
                    $parts[] = (string)$resolved;
                }
            }

            return $parts === [] ? null : implode('', $parts);
        }

        if ($value instanceof ObjectAccessorNode) {
            return null;
        }

        return null;
    }

    public function evaluateBooleanArgument(ViewHelperNode $node, string $name): ?bool
    {
        $arguments = $node->getArguments();
        if (!isset($arguments[$name])) {
            return null;
        }

        $argument = $arguments[$name];
        if ($argument instanceof BooleanNode) {
            return $this->resolveBooleanNode($argument);
        }

        return $this->expressionEvaluator->evaluateBoolean($this->resolveLiteral($argument));
    }

    private function resolveBooleanNode(BooleanNode $node): ?bool
    {
        $stack = $node->getStack();
        if ($stack === []) {
            return null;
        }

        if (count($stack) === 1) {
            $part = $stack[0];
            if ($part instanceof NumericNode) {
                return (bool)$part->getValue();
            }
            if ($part instanceof TextNode) {
                return $this->expressionEvaluator->evaluateBoolean($part->getText());
            }
            if ($part instanceof ObjectAccessorNode) {
                return null;
            }
            if (is_scalar($part)) {
                return $this->expressionEvaluator->evaluateBoolean($part);
            }
        }

        return null;
    }
}
