<?php

declare(strict_types=1);

namespace Cru\Fluidlint\Report;

final class Reporter
{
    /**
     * @param list<Issue> $issues
     */
    public function renderText(array $issues): string
    {
        if ($issues === []) {
            return "No issues found.\n";
        }

        usort($issues, $this->sortIssues(...));

        $lines = [];
        foreach ($issues as $issue) {
            $location = $issue->file;
            if ($issue->line !== null) {
                $location .= ':' . $issue->line;
            }
            $lines[] = sprintf(
                '[%s] %s – %s (%s)',
                strtoupper($issue->severity->value),
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
    public function renderJson(array $issues, int $filesScanned = 0): string
    {
        usort($issues, $this->sortIssues(...));

        return json_encode([
            'filesScanned' => $filesScanned,
            'issueCount' => count($issues),
            'issues' => array_map(static fn (Issue $issue): array => $issue->toArray(), $issues),
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
    }

    /**
     * @param list<Issue> $issues
     */
    public function renderSarif(array $issues, int $filesScanned = 0): string
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
                        'artifactLocation' => ['uri' => $issue->file],
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

        return json_encode($sarif, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
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
