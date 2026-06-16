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

namespace Cru\Fluidlint\Service;

use Cru\Fluidlint\Analysis\ComplexityAnalyzer;
use Cru\Fluidlint\Analysis\DeadCodeAnalyzer;
use Cru\Fluidlint\Analysis\TemplateGraph;
use Cru\Fluidlint\Analysis\TypoScriptTemplateIndex;
use Cru\Fluidlint\Configuration\Configuration;
use Cru\Fluidlint\Parser\TemplateParserFactory;
use Cru\Fluidlint\Report\Issue;
use Cru\Fluidlint\Report\Severity;
use Cru\Fluidlint\Rule\RuleEngine;
use TYPO3Fluid\Fluid\Core\Parser\Exception as ParserException;
use TYPO3Fluid\Fluid\Core\Parser\ParsingState;

final class TemplateAnalyzer
{
    private readonly TemplateParserFactory $parserFactory;
    private readonly RuleEngine $ruleEngine;
    private readonly ComplexityAnalyzer $complexityAnalyzer;
    private readonly DeadCodeAnalyzer $deadCodeAnalyzer;

    public function __construct(
        ?TemplateParserFactory $parserFactory = null,
        ?RuleEngine $ruleEngine = null,
        ?ComplexityAnalyzer $complexityAnalyzer = null,
        ?DeadCodeAnalyzer $deadCodeAnalyzer = null,
    ) {
        $this->parserFactory = $parserFactory ?? new TemplateParserFactory();
        $this->ruleEngine = $ruleEngine ?? RuleEngine::createDefault();
        $this->complexityAnalyzer = $complexityAnalyzer ?? new ComplexityAnalyzer();
        $this->deadCodeAnalyzer = $deadCodeAnalyzer ?? new DeadCodeAnalyzer();
    }

    /**
     * @return array{issues: list<Issue>, parsingStates: array<string, ParsingState>, complexity: array<string, array{complexity: int, branchCounts: array<string, int>, contributions: list<array{viewHelper: string, line: int|null, points: int}>}>}
     */
    public function analyzeFiles(array $files, Configuration $configuration, bool $includeComplexity = true, bool $includeDeadCode = true): array
    {
        $issues = [];
        $parsingStates = [];
        $complexity = [];

        foreach ($files as $file) {
            $source = file_get_contents($file);
            if ($source === false) {
                $issues[] = new Issue(
                    ruleId: 'fluid/parse-error',
                    severity: Severity::Error,
                    message: 'Unable to read template file.',
                    file: $file,
                );
                continue;
            }

            try {
                $parsingState = $this->parserFactory->parse($source, $file);
                $parsingStates[$file] = $parsingState;
            } catch (ParserException $exception) {
                if ($configuration->isRuleEnabled('fluid/parse-error')) {
                    $issues[] = $this->createParseErrorIssue($file, $exception);
                }
                continue;
            }

            array_push($issues, ...$this->ruleEngine->analyze($file, $source, $parsingState, $configuration));

            if ($includeComplexity) {
                $measurement = $this->complexityAnalyzer->measure($source, $parsingState);
                $complexity[$file] = [
                    'complexity' => $measurement['complexity'],
                    'branchCounts' => ComplexityAnalyzer::summarizeContributions($measurement['contributions']),
                    'contributions' => $measurement['contributions'],
                ];
                array_push($issues, ...$this->complexityAnalyzer->analyze($file, $source, $parsingState, $configuration));
            }
        }

        if ($includeDeadCode && $parsingStates !== []) {
            $projectRoot = $this->resolveProjectRoot(array_keys($parsingStates));
            $typoScriptIndex = TypoScriptTemplateIndex::build($projectRoot, $configuration->typoScriptPaths);
            $graph = TemplateGraph::build($parsingStates, $typoScriptIndex);
            array_push($issues, ...$this->deadCodeAnalyzer->analyzeProject($parsingStates, $graph, $configuration));
        }

        return ['issues' => $issues, 'parsingStates' => $parsingStates, 'complexity' => $complexity];
    }

    private function createParseErrorIssue(string $file, ParserException $exception): Issue
    {
        $line = null;
        $column = null;

        if (preg_match('/line (\d+) at character (\d+)/', $exception->getMessage(), $matches) === 1) {
            $line = (int)$matches[1];
            $column = (int)$matches[2];
        }

        return new Issue(
            ruleId: 'fluid/parse-error',
            severity: Severity::Error,
            message: $exception->getMessage(),
            file: $file,
            line: $line,
            column: $column,
        );
    }

    /**
     * @param list<string> $files
     */
    private function resolveProjectRoot(array $files): string
    {
        if ($files === []) {
            return getcwd() ?: '.';
        }

        $common = str_replace('\\', '/', dirname($files[0]));
        foreach ($files as $file) {
            $directory = str_replace('\\', '/', dirname($file));
            while (!str_starts_with($directory, $common) && $common !== '/') {
                $common = dirname($common);
            }
        }

        $cwd = str_replace('\\', '/', getcwd() ?: '.');
        if (is_dir($cwd . '/Configuration')) {
            return $cwd;
        }

        return $common;
    }
}
