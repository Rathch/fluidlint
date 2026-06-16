<?php

declare(strict_types=1);

namespace Cru\Fluidlint\Tests;

use Cru\Fluidlint\Configuration\Configuration;
use Cru\Fluidlint\Report\Issue;
use Cru\Fluidlint\Report\Reporter;
use Cru\Fluidlint\Report\Severity;
use PHPUnit\Framework\TestCase;

final class ReporterTest extends TestCase
{
    private const PATH_BASE = '/project';

    public function testJsonOutputDoesNotEscapeSlashes(): void
    {
        $reporter = new Reporter();
        $json = $reporter->renderJson([
            new Issue(
                ruleId: 'dead-code/unused-variable',
                severity: Severity::Warning,
                message: 'Test',
                file: '/project/Resources/Private/Templates/List.html',
                context: ['variable' => 'items'],
            ),
        ], self::PATH_BASE, 1);

        self::assertStringContainsString('dead-code/unused-variable', $json);
        self::assertStringNotContainsString('dead-code\\/unused-variable', $json);
        self::assertStringContainsString('Resources/Private/Templates/List.html', $json);
        self::assertStringNotContainsString('/project/Resources/Private/Templates/List.html', $json);
    }

    public function testComplexityContextUsesBranchCounts(): void
    {
        $reporter = new Reporter();
        $json = $reporter->renderJson([
            new Issue(
                ruleId: 'complexity/threshold-exceeded',
                severity: Severity::Warning,
                message: 'Cyclomatic complexity 5 exceeds threshold.',
                file: '/project/template.html',
                context: [
                    'complexity' => 5,
                    'warn' => 4,
                    'error' => 10,
                    'branchCounts' => ['f:if' => 3, 'f:for' => 1],
                ],
            ),
        ], self::PATH_BASE);

        self::assertStringContainsString('"branchCounts"', $json);
        self::assertStringContainsString('"f:if": 3', $json);
        self::assertStringNotContainsString('contributions', $json);
        self::assertStringContainsString('"file": "template.html"', $json);
    }

    public function testTextOutputUsesSeverityColorsWhenDecorated(): void
    {
        $reporter = new Reporter();
        $output = $reporter->renderText([
            new Issue('fluid/parse-error', Severity::Error, 'Parse error', '/project/file.html'),
            new Issue('dead-code/unused-variable', Severity::Warning, 'Unused', '/project/file.html'),
            new Issue('dead-code/orphan-partial', Severity::Info, 'Orphan', '/project/file.html'),
        ], self::PATH_BASE, true);

        self::assertStringContainsString('<error>ERROR</error>', $output);
        self::assertStringContainsString('<comment>WARNING</comment>', $output);
        self::assertStringContainsString('<info>INFO</info>', $output);
        self::assertStringContainsString('(file.html)', $output);
    }

    public function testTextOutputOmitsColorsWhenNotDecorated(): void
    {
        $reporter = new Reporter();
        $output = $reporter->renderText([
            new Issue('fluid/parse-error', Severity::Error, 'Parse error', '/project/file.html'),
        ], self::PATH_BASE, false);

        self::assertStringContainsString('[ERROR]', $output);
        self::assertStringNotContainsString('<error>', $output);
        self::assertStringContainsString('(file.html)', $output);
    }

    public function testDetailedReportIncludesComplexityCalculations(): void
    {
        $reporter = new Reporter();
        $configuration = Configuration::loadDefaults();
        $json = $reporter->renderDetailedReport(
            [
                new Issue(
                    ruleId: 'complexity/threshold-exceeded',
                    severity: Severity::Warning,
                    message: 'Cyclomatic complexity 5 exceeds threshold.',
                    file: '/project/template.html',
                    context: ['complexity' => 5, 'warn' => 4, 'error' => 10, 'branchCounts' => ['f:if' => 3]],
                ),
            ],
            self::PATH_BASE,
            1,
            [
                '/project/template.html' => [
                    'complexity' => 5,
                    'branchCounts' => ['f:if' => 3],
                    'contributions' => [
                        ['viewHelper' => 'f:if', 'line' => 10, 'points' => 1],
                        ['viewHelper' => 'f:if', 'line' => 20, 'points' => 1],
                    ],
                ],
            ],
            $configuration,
        );

        self::assertStringContainsString('"generatedAt"', $json);
        self::assertStringContainsString('"thresholds"', $json);
        self::assertStringContainsString('"contributions"', $json);
        self::assertStringContainsString('"line": 10', $json);
        self::assertStringContainsString('"complexity/threshold-exceeded"', $json);
        self::assertStringContainsString('"template.html"', $json);
        self::assertStringNotContainsString('/project/template.html', $json);
    }

    public function testDetailedReportIncludesConfiguredThresholds(): void
    {
        $reporter = new Reporter();
        $configuration = Configuration::fromArray([
            'nestingDepth' => ['warn' => 5, 'error' => 9],
            'complexity' => ['warn' => 7, 'error' => 15],
            'failOn' => 'error',
        ]);

        $json = $reporter->renderDetailedReport([], self::PATH_BASE, 3, [], $configuration);
        $report = json_decode($json, true);

        self::assertIsArray($report);
        self::assertSame(3, $report['filesScanned']);
        self::assertSame(0, $report['issueCount']);
        self::assertSame(5, $report['thresholds']['nestingDepth']['warn']);
        self::assertSame(9, $report['thresholds']['nestingDepth']['error']);
        self::assertSame(7, $report['thresholds']['complexity']['warn']);
        self::assertSame(15, $report['thresholds']['complexity']['error']);
        self::assertSame('error', $report['thresholds']['failOn']);
    }

    public function testDetailedReportSortsIssuesBySeverity(): void
    {
        $reporter = new Reporter();
        $json = $reporter->renderDetailedReport(
            [
                new Issue('dead-code/orphan-partial', Severity::Info, 'Info issue', '/project/z.html'),
                new Issue('fluid/parse-error', Severity::Error, 'Error issue', '/project/a.html'),
                new Issue('dead-code/unused-variable', Severity::Warning, 'Warning issue', '/project/m.html'),
            ],
            self::PATH_BASE,
            3,
            [],
            Configuration::loadDefaults(),
        );

        $report = json_decode($json, true);
        self::assertIsArray($report);
        self::assertSame('fluid/parse-error', $report['issues'][0]['ruleId']);
        self::assertSame('dead-code/unused-variable', $report['issues'][1]['ruleId']);
        self::assertSame('dead-code/orphan-partial', $report['issues'][2]['ruleId']);
        self::assertSame('a.html', $report['issues'][0]['file']);
    }
}
