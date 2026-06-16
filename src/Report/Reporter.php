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

use Cru\Fluidlint\Configuration\Configuration;
use Cru\Fluidlint\Util\PathRelativizer;

final class Reporter
{
    /**
     * @param list<Issue> $issues
     */
    public function renderText(array $issues, string $pathBase, bool $decorated = true): string
    {
        if ($issues === []) {
            return $decorated ? "<info>No issues found.</info>\n" : "No issues found.\n";
        }

        usort($issues, $this->sortIssues(...));

        $lines = [];
        foreach ($issues as $issue) {
            $location = $issue->displayFile($pathBase);
            if ($issue->line !== null) {
                $location .= ':' . $issue->line;
            }

            $severityLabel = strtoupper($issue->severity->value);
            if ($decorated) {
                $tag = $issue->severity->consoleTag();
                $severityLabel = sprintf('<%s>%s</%s>', $tag, $severityLabel, $tag);
            }

            $lines[] = sprintf(
                '[%s] %s – %s (%s)',
                $severityLabel,
                $issue->ruleId,
                $issue->message,
                $location,
            );
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param list<Issue> $issues
     */
    public function renderJson(array $issues, string $pathBase, int $filesScanned = 0): string
    {
        usort($issues, $this->sortIssues(...));

        return json_encode([
            'filesScanned' => $filesScanned,
            'issueCount' => count($issues),
            'issues' => array_map(static fn (Issue $issue): array => $issue->toArray($pathBase), $issues),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    }

    /**
     * @param list<Issue> $issues
     */
    public function renderSarif(array $issues, string $pathBase, int $filesScanned = 0): string
    {
        usort($issues, $this->sortIssues(...));

        $rules = [];
        $results = [];

        foreach ($issues as $issue) {
            $rules[$issue->ruleId] = [
                'id' => $issue->ruleId,
                'name' => $issue->ruleId,
                'shortDescription' => ['text' => $issue->ruleId],
            ];

            $result = [
                'ruleId' => $issue->ruleId,
                'level' => match ($issue->severity) {
                    Severity::Error => 'error',
                    Severity::Warning => 'warning',
                    Severity::Info => 'note',
                },
                'message' => ['text' => $issue->message],
                'locations' => [[
                    'physicalLocation' => [
                        'artifactLocation' => ['uri' => $issue->displayFile($pathBase)],
                        'region' => array_filter([
                            'startLine' => $issue->line,
                            'startColumn' => $issue->column,
                        ]),
                    ],
                ]],
            ];
            $results[] = $result;
        }

        $sarif = [
            'version' => '2.1.0',
            '$schema' => 'https://raw.githubusercontent.com/oasis-tcs/sarif-spec/master/Schemata/sarif-schema-2.1.0.json',
            'runs' => [[
                'tool' => [
                    'driver' => [
                        'name' => 'fluidlint',
                        'informationUri' => 'https://github.com/cru/fluidlint',
                        'rules' => array_values($rules),
                    ],
                ],
                'results' => $results,
                'properties' => ['filesScanned' => $filesScanned],
            ]],
        ];

        return json_encode($sarif, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    }

    /**
     * @param list<Issue> $issues
     * @param array<string, array{complexity: int, branchCounts: array<string, int>, contributions: list<array{viewHelper: string, line: int|null, points: int}>}> $complexityByFile
     */
    public function renderDetailedReport(
        array $issues,
        string $pathBase,
        int $filesScanned,
        array $complexityByFile,
        Configuration $configuration,
    ): string {
        usort($issues, $this->sortIssues(...));

        return json_encode([
            'generatedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'filesScanned' => $filesScanned,
            'issueCount' => count($issues),
            'thresholds' => [
                'nestingDepth' => [
                    'warn' => $configuration->nestingDepthWarn,
                    'error' => $configuration->nestingDepthError,
                ],
                'complexity' => [
                    'warn' => $configuration->complexityWarn,
                    'error' => $configuration->complexityError,
                ],
                'failOn' => $configuration->failOn,
            ],
            'complexity' => PathRelativizer::relativizeComplexityKeys($complexityByFile, $pathBase),
            'issues' => array_map(static fn (Issue $issue): array => $issue->toArray($pathBase), $issues),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    }

    /**
     * @param list<Issue> $issues
     */
    public function exceedsFailThreshold(array $issues, Severity $failOn): bool
    {
        foreach ($issues as $issue) {
            if ($issue->severity->isAtLeast($failOn)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<Issue> $issues
     */
    private function sortIssues(Issue $a, Issue $b): int
    {
        $severity = $b->severity->rank() <=> $a->severity->rank();
        if ($severity !== 0) {
            return $severity;
        }

        $file = $a->file <=> $b->file;
        if ($file !== 0) {
            return $file;
        }

        return ($a->line ?? 0) <=> ($b->line ?? 0);
    }
}
