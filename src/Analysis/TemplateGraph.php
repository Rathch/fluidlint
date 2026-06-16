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

use TYPO3Fluid\Fluid\Core\Parser\ParsingState;

final class TemplateGraph
{
    /**
     * @param array<string, list<string>> $filesByType
     * @param array<string, list<string>> $sectionsByFile
     * @param list<array{from: string, type: string, target: string}> $references
     */
    public function __construct(
        private readonly array $filesByType,
        private readonly array $sectionsByFile,
        private readonly array $references,
    ) {
    }

    /**
     * @param array<string, ParsingState> $parsingStates
     */
    public static function build(array $parsingStates): self
    {
        $walker = new AstWalker();
        $extractor = new ArgumentExtractor();
        $filesByType = ['template' => [], 'partial' => [], 'layout' => []];
        $sectionsByFile = [];
        $references = [];

        foreach (array_keys($parsingStates) as $file) {
            $type = self::detectType($file);
            $filesByType[$type][] = $file;
        }

        foreach ($parsingStates as $file => $parsingState) {
            $layoutName = $parsingState->getUnevaluatedLayoutName();
            if (is_string($layoutName) && $layoutName !== '') {
                $references[] = ['from' => $file, 'type' => 'layout', 'target' => $layoutName];
            } elseif ($layoutName instanceof \TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\TextNode) {
                $references[] = ['from' => $file, 'type' => 'layout', 'target' => $layoutName->getText()];
            }

            $sections = [];
            $walker->walk(
                $parsingState->getRootNode(),
                function (\TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\ViewHelperNode $node) use (&$sections, &$references, $walker, $extractor, $file): void {
                    if ($walker->isCoreViewHelper($node, 'section')) {
                        $name = $extractor->scalarArgument($node, 'name');
                        if ($name !== null) {
                            $sections[$name] = true;
                        }
                    }

                    if ($walker->isCoreViewHelper($node, 'render')) {
                        $partial = $extractor->scalarArgument($node, 'partial');
                        if ($partial !== null) {
                            $references[] = ['from' => $file, 'type' => 'partial', 'target' => $partial];
                        }

                        $section = $extractor->scalarArgument($node, 'section');
                        if ($section !== null) {
                            $references[] = ['from' => $file, 'type' => 'section', 'target' => $section];
                        }
                    }
                },
            );
            $sectionsByFile[$file] = array_keys($sections);
        }

        return new self($filesByType, $sectionsByFile, $references);
    }

    /**
     * @return list<string>
     */
    public function orphanPartials(): array
    {
        $referenced = [];
        foreach ($this->references as $reference) {
            if ($reference['type'] === 'partial') {
                $referenced[$reference['target']] = true;
            }
        }

        $orphans = [];
        foreach ($this->filesByType['partial'] as $file) {
            $name = self::basenameWithoutExtension($file);
            if (!isset($referenced[$name])) {
                $orphans[] = $file;
            }
        }

        return $orphans;
    }

    /**
     * @return list<string>
     */
    public function orphanLayouts(): array
    {
        $referenced = [];
        foreach ($this->references as $reference) {
            if ($reference['type'] === 'layout') {
                $referenced[$reference['target']] = true;
            }
        }

        $orphans = [];
        foreach ($this->filesByType['layout'] as $file) {
            $name = self::basenameWithoutExtension($file);
            if (!isset($referenced[$name])) {
                $orphans[] = $file;
            }
        }

        return $orphans;
    }

    /**
     * @param list<string> $entryPointGlobs
     * @return list<array{file: string, section: string}>
     */
    public function unusedSections(array $entryPointGlobs): array
    {
        $referencedSections = [];
        foreach ($this->references as $reference) {
            if ($reference['type'] === 'section') {
                $referencedSections[$reference['target']] = true;
            }
        }

        $unused = [];
        foreach ($this->sectionsByFile as $file => $sections) {
            if (!self::matchesAnyGlob($file, $entryPointGlobs)) {
                continue;
            }

            foreach ($sections as $section) {
                if (!isset($referencedSections[$section])) {
                    $unused[] = ['file' => $file, 'section' => $section];
                }
            }
        }

        return $unused;
    }

    private static function detectType(string $file): string
    {
        if (str_contains($file, '/Layouts/') || str_contains($file, '/layouts/')) {
            return 'layout';
        }
        if (str_contains($file, '/Partials/') || str_contains($file, '/partials/')) {
            return 'partial';
        }

        return 'template';
    }

    private static function basenameWithoutExtension(string $file): string
    {
        $base = basename($file);
        return preg_replace('/\.(fluid\.)?html$/', '', $base) ?? $base;
    }

    /**
     * @param list<string> $globs
     */
    private static function matchesAnyGlob(string $file, array $globs): bool
    {
        foreach ($globs as $glob) {
            $regex = '/^' . str_replace(
                ['**', '*', '/'],
                ['.*', '[^/]*', '\\/'],
                $glob,
            ) . '$/i';

            if (preg_match($regex, $file) === 1 || preg_match($regex, basename($file)) === 1) {
                return true;
            }
        }

        return false;
    }
}
