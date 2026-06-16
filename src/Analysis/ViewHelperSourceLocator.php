<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: 2025 Christian Rath-Ulrich
//
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Cru\Fluidlint\Analysis;

use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\ViewHelperNode;

/**
 * Maps ViewHelper AST nodes to source line numbers in document order.
 */
final class ViewHelperSourceLocator
{
    private int $searchOffset = 0;

    /** @var array<int, true> */
    private array $usedPositions = [];

    public function __construct(
        private readonly string $source,
    ) {
    }

    public function lineFor(ViewHelperNode $node): ?int
    {
        $needles = [
            '<' . $node->getNamespace() . ':' . $node->getName(),
            '{' . $node->getNamespace() . ':' . $node->getName(),
        ];

        $position = $this->findPosition($needles, $this->searchOffset, false);
        if ($position === null) {
            $position = $this->findPosition($needles, 0, true);
        }

        if ($position === null) {
            return null;
        }

        $this->usedPositions[$position] = true;
        $this->searchOffset = $position + 1;

        return substr_count(substr($this->source, 0, $position), "\n") + 1;
    }

    /**
     * @param list<string> $needles
     */
    private function findPosition(array $needles, int $offset, bool $onlyUnused): ?int
    {
        $best = null;

        foreach ($needles as $needle) {
            $position = $offset;
            while (($position = strpos($this->source, $needle, $position)) !== false) {
                if (!$onlyUnused || !isset($this->usedPositions[$position])) {
                    $best = $best === null ? $position : min($best, $position);
                    break;
                }

                ++$position;
            }
        }

        return $best;
    }
}
