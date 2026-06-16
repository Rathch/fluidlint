<?php

declare(strict_types=1);

namespace Cru\Fluidlint\Tests;

use Cru\Fluidlint\Configuration\Configuration;
use PHPUnit\Framework\TestCase;

final class ConfigurationTest extends TestCase
{
    public function testReportPathIsLoadedFromArray(): void
    {
        $configuration = Configuration::fromArray([
            'report' => ['path' => 'var/reports/fluidlint.json'],
        ]);

        self::assertSame('var/reports/fluidlint.json', $configuration->reportPath);
    }

    public function testReportPathTrimsWhitespace(): void
    {
        $configuration = Configuration::fromArray([
            'report' => ['path' => '  var/report.json  '],
        ]);

        self::assertSame('var/report.json', $configuration->reportPath);
    }

    public function testEmptyReportPathBecomesNull(): void
    {
        $configuration = Configuration::fromArray([
            'report' => ['path' => '   '],
        ]);

        self::assertNull($configuration->reportPath);
    }

    public function testReportPathCanBeOverridden(): void
    {
        $defaults = Configuration::fromArray([
            'report' => ['path' => 'var/default.json'],
        ]);

        $configuration = Configuration::fromArray([
            'report' => ['path' => 'var/custom.json'],
        ], $defaults);

        self::assertSame('var/custom.json', $configuration->reportPath);
    }

    public function testLoadFromProjectReadsReportPath(): void
    {
        $directory = sys_get_temp_dir() . '/fluidlint-config-' . uniqid('', true);
        mkdir($directory);

        $reportPath = 'build/fluidlint-report.json';
        file_put_contents(
            $directory . '/.fluidlint.yaml',
            "report:\n  path: {$reportPath}\n",
        );

        $configuration = Configuration::loadFromProject($directory);

        self::assertSame($reportPath, $configuration->reportPath);

        unlink($directory . '/.fluidlint.yaml');
        rmdir($directory);
    }
}
