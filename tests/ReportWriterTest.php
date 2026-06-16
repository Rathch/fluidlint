<?php

declare(strict_types=1);

namespace Cru\Fluidlint\Tests;

use Cru\Fluidlint\Configuration\Configuration;
use Cru\Fluidlint\Report\Issue;
use Cru\Fluidlint\Report\Reporter;
use Cru\Fluidlint\Report\ReportWriter;
use Cru\Fluidlint\Report\Severity;
use PHPUnit\Framework\TestCase;

final class ReportWriterTest extends TestCase
{
    public function testWritesDetailedReportToConfiguredPath(): void
    {
        $directory = sys_get_temp_dir() . '/fluidlint-report-test-' . uniqid('', true);
        $path = $directory . '/nested/report.json';

        $writer = new ReportWriter(new Reporter());
        $writer->writeDetailedReport(
            $path,
            [new Issue('fluid/parse-error', Severity::Error, 'Parse error', '/project/file.html')],
            '/project',
            1,
            [
                '/project/file.html' => [
                    'complexity' => 2,
                    'branchCounts' => ['f:if' => 1],
                    'contributions' => [
                        ['viewHelper' => 'f:if', 'line' => 5, 'points' => 1],
                    ],
                ],
            ],
            Configuration::loadDefaults(),
        );

        self::assertFileExists($path);
        $content = file_get_contents($path);
        self::assertIsString($content);
        self::assertStringContainsString('"issueCount": 1', $content);
        self::assertStringContainsString('"contributions"', $content);
        self::assertStringContainsString('"file": "file.html"', $content);
        self::assertStringNotContainsString('/project/file.html', $content);

        unlink($path);
        rmdir($directory . '/nested');
        rmdir($directory);
    }

    public function testThrowsWhenTargetPathIsADirectory(): void
    {
        $directory = sys_get_temp_dir() . '/fluidlint-report-dir-' . uniqid('', true);
        mkdir($directory);

        $writer = new ReportWriter(new Reporter());

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage(sprintf('Unable to write report file "%s".', $directory));

            $writer->writeDetailedReport(
                $directory,
                [],
                '/project',
                0,
                [],
                Configuration::loadDefaults(),
            );
        } finally {
            rmdir($directory);
        }
    }
}
