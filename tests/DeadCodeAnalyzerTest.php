<?php

declare(strict_types=1);

namespace Cru\Fluidlint\Tests;

use Cru\Fluidlint\Analysis\DeadCodeAnalyzer;
use Cru\Fluidlint\Analysis\TemplateGraph;
use Cru\Fluidlint\Analysis\TypoScriptTemplateIndex;
use Cru\Fluidlint\Analysis\VariableReferenceExtractor;
use Cru\Fluidlint\Configuration\Configuration;
use Cru\Fluidlint\Parser\TemplateParserFactory;
use PHPUnit\Framework\TestCase;

final class DeadCodeAnalyzerTest extends TestCase
{
    private string $fixturesPath;

    protected function setUp(): void
    {
        $this->fixturesPath = __DIR__ . '/Fixtures';
    }

    public function testDetectsVariableUsedWithPipeSyntax(): void
    {
        $parser = new TemplateParserFactory();
        $deadCode = new DeadCodeAnalyzer();
        $file = $this->fixturesPath . '/Resources/Private/Templates/PartialArguments.html';
        $source = file_get_contents($file) ?: '';
        $state = $parser->parse($source, $file);

        $issues = $deadCode->analyzeUnusedVariables($file, $source, $state);
        $ruleIds = array_map(static fn ($issue) => $issue->ruleId, $issues);

        self::assertNotContains('dead-code/unused-variable', $ruleIds);
    }

    public function testWarnsWhenPartialIsRenderedWithAll(): void
    {
        $parser = new TemplateParserFactory();
        $deadCode = new DeadCodeAnalyzer();
        $file = $this->fixturesPath . '/Resources/Private/Templates/PartialAll.html';
        $source = file_get_contents($file) ?: '';
        $state = $parser->parse($source, $file);

        $issues = $deadCode->analyzePartialRenderCalls($file, $source);
        $ruleIds = array_map(static fn ($issue) => $issue->ruleId, $issues);

        self::assertContains('dead-code/partial-all-arguments', $ruleIds);
    }

    public function testDoesNotFlagLoopVariablePassedViaAll(): void
    {
        $parser = new TemplateParserFactory();
        $deadCode = new DeadCodeAnalyzer();
        $file = $this->fixturesPath . '/Resources/Private/Templates/PartialAll.html';
        $source = file_get_contents($file) ?: '';
        $state = $parser->parse($source, $file);

        $issues = $deadCode->analyzeUnusedVariables($file, $source, $state);
        $variables = array_map(
            static fn ($issue) => $issue->context['variable'] ?? null,
            $issues,
        );

        self::assertNotContains('item', $variables);
    }

    public function testDetectsUnusedPartialArgument(): void
    {
        $parser = new TemplateParserFactory();
        $deadCode = new DeadCodeAnalyzer();
        $template = $this->fixturesPath . '/Resources/Private/Templates/PartialArguments.html';
        $partial = $this->fixturesPath . '/Resources/Private/Partials/Box.html';
        $templateSource = file_get_contents($template) ?: '';
        $partialSource = file_get_contents($partial) ?: '';
        $states = [
            $template => $parser->parse($templateSource, $template),
            $partial => $parser->parse($partialSource, $partial),
        ];
        $graph = TemplateGraph::build($states);

        $issues = $deadCode->analyzePartialArguments($states, $graph);
        $ruleIds = array_map(static fn ($issue) => $issue->ruleId, $issues);

        self::assertContains('dead-code/unused-partial-argument', $ruleIds);
    }

    public function testResolvesPartialNamesRelativeToTypoScriptRoot(): void
    {
        $partial = $this->fixturesPath . '/Resources/Private/Partials/Page/Widget.html';
        $names = TemplateGraph::partialNamesForFile($partial, [
            $this->fixturesPath . '/Resources/Private/Partials/Page',
        ]);

        self::assertContains('Widget', $names);
        self::assertContains('Page/Widget', $names);
    }

    public function testTypoScriptIndexFindsTemplateNames(): void
    {
        $index = TypoScriptTemplateIndex::build($this->fixturesPath, ['Configuration/**/*.typoscript']);

        self::assertContains('Example', $index->templateNames());
    }

    public function testExcludesExtensionOverridesFromOrphanPartials(): void
    {
        $parser = new TemplateParserFactory();
        $deadCode = new DeadCodeAnalyzer();
        $extensionPartial = $this->fixturesPath . '/Resources/Extensions/demo/Private/Partials/Unreferenced.html';
        $states = [
            $extensionPartial => $parser->parse('<span>orphan</span>', $extensionPartial),
        ];
        $graph = TemplateGraph::build($states);
        $configuration = Configuration::fromArray([
            'deadCode' => ['excludeOrphanPatterns' => ['**/Resources/Extensions/**']],
        ]);

        $issues = $deadCode->analyzeProject($states, $graph, $configuration);
        $ruleIds = array_map(static fn ($issue) => $issue->ruleId, $issues);

        self::assertNotContains('dead-code/orphan-partial', $ruleIds);
    }

    public function testExtractArgumentNamesIgnoresInlineViewHelpersInQuotedStrings(): void
    {
        $extractor = new VariableReferenceExtractor();
        $block = "header: data.header,
            positionClass: '{f:if(condition: data.header_position, then: \\'ce-headline\\')}'";

        $names = $extractor->extractArgumentNames($block);

        self::assertContains('header', $names);
        self::assertContains('positionClass', $names);
        self::assertNotContains('f', $names);
        self::assertNotContains('condition', $names);
        self::assertNotContains('then', $names);
    }

    public function testReadsVariableFromInlineObjectAttribute(): void
    {
        $extractor = new VariableReferenceExtractor();
        $read = [];
        $source = '<f:cObject typoscriptObjectPath="lib.foo" data="{uid: popup}" />';

        $extractor->collectReadsFromSource($source, $read);

        self::assertArrayHasKey('popup', $read);
    }

    public function testPartialAllArgumentsDisabledByDefault(): void
    {
        $configuration = Configuration::loadDefaults();
        self::assertFalse($configuration->isRuleEnabled('dead-code/partial-all-arguments'));
    }

    public function testVariableReferenceExtractorReadsRenderArguments(): void
    {
        $extractor = new VariableReferenceExtractor();
        $read = [];
        $source = '<f:render partial="Box" arguments="{title: headline, unused: ignoredVar}" />';

        $extractor->collectReadsFromSource($source, $read);

        self::assertArrayHasKey('headline', $read);
        self::assertArrayHasKey('ignoredVar', $read);
    }
}
