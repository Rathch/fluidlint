<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: 2025 Christian Rath-Ulrich
//
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Cru\Fluidlint\Report;

use Cru\Fluidlint\Configuration\Configuration;

final class ReportWriter
{
    public function __construct(
        private readonly Reporter $reporter = new Reporter(),
    ) {
    }

    /**
     * @param list<Issue> $issues
     * @param array<string, array{complexity: int, branchCounts: array<string, int>, contributions: list<array{viewHelper: string, line: int|null, points: int}>}> $complexityByFile
     */
    public function writeDetailedReport(
        string $path,
        array $issues,
        string $pathBase,
        int $filesScanned,
        array $complexityByFile,
        Configuration $configuration,
    ): void {
        $directory = dirname($path);
        if ($directory !== '' && $directory !== '.' && !is_dir($directory)) {
            if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new \RuntimeException(sprintf('Unable to create report directory "%s".', $directory));
            }
        }

        $content = $this->reporter->renderDetailedReport($issues, $pathBase, $filesScanned, $complexityByFile, $configuration);
        if (is_dir($path)) {
            throw new \RuntimeException(sprintf('Unable to write report file "%s".', $path));
        }

        if (file_put_contents($path, $content) === false) {
            throw new \RuntimeException(sprintf('Unable to write report file "%s".', $path));
        }
    }
}
