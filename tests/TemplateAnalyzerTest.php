<?php

declare(strict_types=1);

namespace Cru\Fluidlint\Tests;

use Cru\Fluidlint\Configuration\Configuration;
use Cru\Fluidlint\Service\TemplateAnalyzer;
use PHPUnit\Framework\TestCase;

final class TemplateAnalyzerTest extends TestCase
{
    private string $fixturesPath;

    protected function setUp(): void
    {
        $this->fixturesPath = __DIR__ . '/Fixtures';
    }

    public function testAnalyzeFilesReturnsComplexityMeasurements(): void
    {
        $analyzer = new TemplateAnalyzer();
        $file = $this->fixturesPath . '/Resources/Private/Templates/List.html';

        $result = $analyzer->analyzeFiles([$file], Configuration::loadDefaults(), true, false);

        self::assertArrayHasKey('complexity', $result);
        self::assertArrayHasKey($file, $result['complexity']);
        self::assertArrayHasKey('complexity', $result['complexity'][$file]);
        self::assertArrayHasKey('branchCounts', $result['complexity'][$file]);
        self::assertArrayHasKey('contributions', $result['complexity'][$file]);
        self::assertGreaterThan(1, $result['complexity'][$file]['complexity']);
        self::assertNotEmpty($result['complexity'][$file]['contributions']);

        foreach ($result['complexity'][$file]['contributions'] as $contribution) {
            self::assertArrayHasKey('viewHelper', $contribution);
            self::assertArrayHasKey('line', $contribution);
            self::assertArrayHasKey('points', $contribution);
        }
    }

    public function testAnalyzeFilesOmitsComplexityWhenDisabled(): void
    {
        $analyzer = new TemplateAnalyzer();
        $file = $this->fixturesPath . '/Resources/Private/Templates/List.html';

        $result = $analyzer->analyzeFiles([$file], Configuration::loadDefaults(), false, false);

        self::assertSame([], $result['complexity']);
    }

    public function testBranchCountsMatchContributions(): void
    {
        $analyzer = new TemplateAnalyzer();
        $file = $this->fixturesPath . '/Resources/Private/Templates/List.html';

        $result = $analyzer->analyzeFiles([$file], Configuration::loadDefaults(), true, false);
        $measurement = $result['complexity'][$file];

        $expectedPoints = 0;
        foreach ($measurement['contributions'] as $contribution) {
            $expectedPoints += $contribution['points'];
        }

        self::assertSame(1 + $expectedPoints, $measurement['complexity']);

        $expectedCounts = [];
        foreach ($measurement['contributions'] as $contribution) {
            $name = $contribution['viewHelper'];
            $expectedCounts[$name] = ($expectedCounts[$name] ?? 0) + $contribution['points'];
        }

        self::assertSame($expectedCounts, $measurement['branchCounts']);
    }
}
