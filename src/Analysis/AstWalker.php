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

use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\AbstractNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\NodeInterface;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\RootNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\TextNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\ViewHelperNode;

final class AstWalker
{
    /**
     * @param callable(ViewHelperNode, int, array<int, ViewHelperNode>): void $viewHelperVisitor
     * @param callable(NodeInterface, int): void|null $nodeVisitor
     */
    public function walk(
        NodeInterface $node,
        callable $viewHelperVisitor,
        ?callable $nodeVisitor = null,
        int $depth = 0,
        array $ancestors = [],
    ): void {
        if ($nodeVisitor !== null) {
            $nodeVisitor($node, $depth);
        }

        if ($node instanceof ViewHelperNode) {
            $viewHelperVisitor($node, $depth, $ancestors);
            $ancestors = [...$ancestors, $node];
        }

        if ($node instanceof AbstractNode || $node instanceof RootNode) {
            foreach ($node->getChildNodes() as $childNode) {
                if ($childNode instanceof NodeInterface) {
                    $this->walk($childNode, $viewHelperVisitor, $nodeVisitor, $depth + 1, $ancestors);
                }
            }
        }
    }

    /**
     * @return list<ViewHelperNode>
     */
    public function collectViewHelpers(NodeInterface $root): array
    {
        $nodes = [];
        $this->walk($root, static function (ViewHelperNode $node) use (&$nodes): void {
            $nodes[] = $node;
        });

        return $nodes;
    }

    public function isFluidNamespace(string $namespace): bool
    {
        return $namespace === 'f';
    }

    public function viewHelperIdentifier(ViewHelperNode $node): string
    {
        return $node->getNamespace() . ':' . $node->getName();
    }

    public function isCoreViewHelper(ViewHelperNode $node, string $name): bool
    {
        return $this->isFluidNamespace($node->getNamespace()) && $node->getName() === $name;
    }

    public function findChildViewHelper(ViewHelperNode $parent, string $name): ?ViewHelperNode
    {
        foreach ($parent->getChildNodes() as $child) {
            if ($child instanceof ViewHelperNode && $this->isCoreViewHelper($child, $name)) {
                return $child;
            }
        }

        return null;
    }

    /**
     * @return list<TextNode|string>
     */
    public function extractTextContent(NodeInterface $node): array
    {
        $parts = [];
        if ($node instanceof TextNode) {
            $parts[] = $node->getText();
        }

        if ($node instanceof AbstractNode || $node instanceof RootNode) {
            foreach ($node->getChildNodes() as $child) {
                if ($child instanceof NodeInterface) {
                    array_push($parts, ...$this->extractTextContent($child));
                }
            }
        }

        return $parts;
    }

    public function sectionIsEmpty(ViewHelperNode $sectionNode): bool
    {
        foreach ($sectionNode->getChildNodes() as $child) {
            if ($child instanceof TextNode && trim($child->getText()) === '') {
                continue;
            }
            return false;
        }

        return true;
    }
}
