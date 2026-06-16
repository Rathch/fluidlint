<?php

declare(strict_types=1);

namespace Cru\Fluidlint\Tests;

use Cru\Fluidlint\Application;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

final class ScanCommandTest extends TestCase
{
    private string $fixturesPath;

    protected function setUp(): void
    {
        $this->fixturesPath = __DIR__ . '/Fixtures';
    }

    public function testScanWritesDetailedReportWhenReportFileOptionIsSet(): void
    {
        $reportPath = $this->temporaryReportPath();

        $tester = $this->createScanTester();
        $exitCode = $tester->execute([
            'paths' => [$this->fixturesPath],
            '--report-file' => $reportPath,
            '--format' => 'text',
        ]);

        self::assertSame(1, $exitCode);
        self::assertFileExists($reportPath);

        $report = $this->decodeReport($reportPath);
        self::assertGreaterThan(0, $report['filesScanned']);
        self::assertGreaterThan(0, $report['issueCount']);
        self::assertArrayHasKey('thresholds', $report);
        self::assertArrayHasKey('complexity', $report);
        self::assertArrayHasKey('issues', $report);
        self::assertNotEmpty($report['complexity']);

        $this->removeFile($reportPath);
    }

    public function testScanWritesReportIndependentlyOfStdoutFormat(): void
    {
        $reportPath = $this->temporaryReportPath();

        $tester = $this->createScanTester();
        $exitCode = $tester->execute([
            'paths' => [$this->fixturesPath],
            '--report-file' => $reportPath,
            '--format' => 'json',
        ]);

        self::assertSame(1, $exitCode);
        self::assertFileExists($reportPath);
        self::assertStringStartsWith('{', trim($tester->getDisplay()));
        self::assertStringContainsString('"issueCount"', $tester->getDisplay());
        self::assertStringContainsString('"generatedAt"', file_get_contents($reportPath) ?: '');

        $this->removeFile($reportPath);
    }

    public function testScanPrintsVerboseConfirmationWhenReportIsWritten(): void
    {
        $reportPath = $this->temporaryReportPath();

        $tester = $this->createScanTester();
        $tester->execute([
            'paths' => [$this->fixturesPath],
            '--report-file' => $reportPath,
            '--format' => 'text',
        ], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        self::assertStringContainsString(
            sprintf('Detailed report written to %s', $reportPath),
            $tester->getDisplay(),
        );

        $this->removeFile($reportPath);
    }

    public function testScanDoesNotWriteReportWithoutConfiguredPath(): void
    {
        $reportPath = $this->temporaryReportPath();

        $tester = $this->createScanTester();
        $tester->execute([
            'paths' => [$this->fixturesPath],
            '--format' => 'text',
        ]);

        self::assertFileDoesNotExist($reportPath);
        $this->removeFile($reportPath);
    }

    public function testScanLoadsReportPathFromConfigFile(): void
    {
        $reportPath = $this->temporaryReportPath();
        $configPath = $this->temporaryConfigPath($reportPath);

        $tester = $this->createScanTester();
        $tester->execute([
            'paths' => [$this->fixturesPath],
            '--config' => $configPath,
            '--format' => 'text',
        ]);

        self::assertFileExists($reportPath);

        $this->removeFile($reportPath);
        unlink($configPath);
    }

    public function testReportFileOptionOverridesConfigPath(): void
    {
        $configReportPath = $this->temporaryReportPath();
        $cliReportPath = $this->temporaryReportPath();
        $configPath = $this->temporaryConfigPath($configReportPath);

        $tester = $this->createScanTester();
        $tester->execute([
            'paths' => [$this->fixturesPath],
            '--config' => $configPath,
            '--report-file' => $cliReportPath,
            '--format' => 'text',
        ]);

        self::assertFileExists($cliReportPath);
        self::assertFileDoesNotExist($configReportPath);

        $this->removeFile($cliReportPath);
        $this->removeFile($configReportPath);
        unlink($configPath);
    }

    public function testScanUsesRelativePathsInCliAndReport(): void
    {
        $reportPath = $this->temporaryReportPath();

        $tester = $this->createScanTester();
        $tester->execute([
            'paths' => [$this->fixturesPath],
            '--report-file' => $reportPath,
            '--format' => 'text',
        ]);

        $absoluteFixtures = realpath($this->fixturesPath) ?: $this->fixturesPath;
        self::assertStringNotContainsString($absoluteFixtures, $tester->getDisplay());
        self::assertStringContainsString('Resources/Private/', $tester->getDisplay());

        $report = $this->decodeReport($reportPath);
        $firstComplexityKey = array_key_first($report['complexity']);
        self::assertIsString($firstComplexityKey);
        self::assertStringStartsNotWith('/', $firstComplexityKey);
        self::assertStringNotContainsString($absoluteFixtures, $firstComplexityKey);

        if ($report['issues'] !== []) {
            self::assertStringStartsNotWith('/', $report['issues'][0]['file']);
            self::assertStringNotContainsString($absoluteFixtures, $report['issues'][0]['file']);
        }

        $this->removeFile($reportPath);
    }

    private function createScanTester(): CommandTester
    {
        $application = new Application();
        $application->setAutoExit(false);

        return new CommandTester($application->find('scan'));
    }

    private function temporaryReportPath(): string
    {
        return sys_get_temp_dir() . '/fluidlint-scan-report-' . uniqid('', true) . '.json';
    }

    private function temporaryConfigPath(string $reportPath): string
    {
        $configPath = sys_get_temp_dir() . '/fluidlint-config-' . uniqid('', true) . '.yaml';
        file_put_contents($configPath, "report:\n  path: {$reportPath}\n");

        return $configPath;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeReport(string $path): array
    {
        $content = file_get_contents($path);
        self::assertIsString($content);

        $decoded = json_decode($content, true);
        self::assertIsArray($decoded);

        return $decoded;
    }

    private function removeFile(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
        }
    }
}
