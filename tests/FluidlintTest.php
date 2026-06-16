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

namespace Cru\Fluidlint\Tests;

use Cru\Fluidlint\Analysis\ComplexityAnalyzer;
use Cru\Fluidlint\Analysis\DeadCodeAnalyzer;
use Cru\Fluidlint\Configuration\Configuration;
use Cru\Fluidlint\Discovery\TemplateDiscovery;
use Cru\Fluidlint\Parser\TemplateParserFactory;
use Cru\Fluidlint\Service\TemplateAnalyzer;
use PHPUnit\Framework\TestCase;

final class FluidlintTest extends TestCase
{
    private string $fixturesPath;

    protected function setUp(): void
    {
        $this->fixturesPath = __DIR__ . '/Fixtures';
    }

    public function testDiscoversFixtureTemplates(): void
    {
        $discovery = new TemplateDiscovery(Configuration::loadDefaults());
        $files = $discovery->discover([$this->fixturesPath]);

        self::assertNotEmpty($files);
        self::assertTrue(
            (bool)array_filter($files, static fn (string $file): bool => str_ends_with($file, 'List.html')),
        );
    }

    public function testDetectsDuplicateSection(): void
    {
        $analyzer = new TemplateAnalyzer();
        $file = $this->fixturesPath . '/Resources/Private/Templates/DuplicateSection.html';
        $result = $analyzer->analyzeFiles([$file], Configuration::loadDefaults(), false, false);

        $ruleIds = array_map(static fn ($issue) => $issue->ruleId, $result['issues']);
        self::assertContains('fluid/duplicate-section', $ruleIds);
    }

    public function testDetectsUnreachableThenBranch(): void
    {
        $parser = new TemplateParserFactory();
        $deadCode = new DeadCodeAnalyzer();
        $file = $this->fixturesPath . '/Resources/Private/Templates/DeadCode.html';
        $source = file_get_contents($file) ?: '';
        $state = $parser->parse($source, $file);

        $issues = $deadCode->analyzeUnreachableBranches($file, $source, $state);
        $ruleIds = array_map(static fn ($issue) => $issue->ruleId, $issues);

        self::assertContains('dead-code/unreachable-then', $ruleIds);
    }

    public function testDetectsUnusedVariableFromSource(): void
    {
        $parser = new TemplateParserFactory();
        $deadCode = new DeadCodeAnalyzer();
        $file = $this->fixturesPath . '/Resources/Private/Templates/DeadCode.html';
        $source = file_get_contents($file) ?: '';
        $state = $parser->parse($source, $file);

        $issues = $deadCode->analyzeUnusedVariables($file, $source, $state);
        $ruleIds = array_map(static fn ($issue) => $issue->ruleId, $issues);

        self::assertContains('dead-code/unused-variable', $ruleIds);
    }

    public function testMeasuresComplexity(): void
    {
        $parser = new TemplateParserFactory();
        $complexity = new ComplexityAnalyzer();
        $file = $this->fixturesPath . '/Resources/Private/Templates/List.html';
        $source = file_get_contents($file) ?: '';
        $state = $parser->parse($source, $file);

        $measurement = $complexity->measure($source, $state);

        self::assertGreaterThan(1, $measurement['complexity']);
        self::assertNotEmpty($measurement['contributions']);
    }

    public function testParsesCustomViewHelperWithStubResolver(): void
    {
        $parser = new TemplateParserFactory();
        $source = '<myext:customBox title="Test"><p>Content</p></myext:customBox>';
        $state = $parser->parse($source, 'custom.html');

        self::assertNotNull($state->getRootNode());
    }

    public function testParsesViewHelperWithConstructorDependencies(): void
    {
        $parser = new TemplateParserFactory();
        $source = '<html xmlns:test="http://typo3.org/ns/Cru/Fluidlint/Tests/Fixtures/ViewHelpers">'
            . '<test:unconstructable title="Test" />'
            . '</html>';
        $state = $parser->parse($source, 'unconstructable.html');

        self::assertNotNull($state->getRootNode());
    }
}
