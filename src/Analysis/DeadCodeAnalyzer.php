<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: 2025 Christian Rath-Ulrich
//
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Cru\Fluidlint\Analysis;

use Cru\Fluidlint\Configuration\Configuration;
use Cru\Fluidlint\Report\Issue;
use Cru\Fluidlint\Report\Severity;
use TYPO3Fluid\Fluid\Core\Parser\ParsingState;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\ViewHelperNode;

final class DeadCodeAnalyzer
{
    public function __construct(
        private readonly ExpressionEvaluator $expressionEvaluator = new ExpressionEvaluator(),
        private readonly AstWalker $walker = new AstWalker(),
        private readonly ArgumentExtractor $argumentExtractor = new ArgumentExtractor(),
        private readonly VariableReferenceExtractor $variableExtractor = new VariableReferenceExtractor(),
    ) {
    }

    /**
     * @param array<string, ParsingState> $parsingStates
     * @return list<Issue>
     */
    public function analyzeProject(array $parsingStates, TemplateGraph $graph, Configuration $configuration): array
    {
        $issues = [];

        foreach ($parsingStates as $file => $parsingState) {
            if ($this->isExcludedFromAnalysis($file, $configuration)) {
                continue;
            }

            $source = file_get_contents($file) ?: '';

            if ($configuration->isRuleEnabled('dead-code/unreachable-then')
                || $configuration->isRuleEnabled('dead-code/unreachable-else')
                || $configuration->isRuleEnabled('dead-code/unreachable-case')
            ) {
                array_push($issues, ...$this->analyzeUnreachableBranches($file, $source, $parsingState, $configuration));
            }

            if ($configuration->isRuleEnabled('dead-code/unused-variable')) {
                array_push($issues, ...$this->analyzeUnusedVariables($file, $source, $parsingState));
            }

            if ($configuration->isRuleEnabled('dead-code/partial-all-arguments')) {
                array_push($issues, ...$this->analyzePartialRenderCalls($file, $source));
            }
        }

        if ($configuration->isRuleEnabled('dead-code/unused-partial-argument')) {
            array_push($issues, ...$this->analyzePartialArguments($parsingStates, $graph, $configuration));
        }

        if ($configuration->isRuleEnabled('dead-code/orphan-partial')) {
            $severity = $configuration->orphanSeverityEnum();
            foreach ($graph->orphanPartials($configuration->excludeOrphanPatterns) as $orphan) {
                $issues[] = new Issue(
                    ruleId: 'dead-code/orphan-partial',
                    severity: $severity,
                    message: sprintf('Partial "%s" is not referenced by any template.', self::displayPartialName($orphan, $graph)),
                    file: $orphan,
                );
            }
        }

        if ($configuration->isRuleEnabled('dead-code/orphan-layout')) {
            $severity = $configuration->orphanSeverityEnum();
            foreach ($graph->orphanLayouts() as $orphan) {
                $issues[] = new Issue(
                    ruleId: 'dead-code/orphan-layout',
                    severity: $severity,
                    message: sprintf('Layout "%s" is not referenced by any template.', basename($orphan)),
                    file: $orphan,
                );
            }
        }

        if ($configuration->isRuleEnabled('dead-code/unused-section')) {
            $severity = $configuration->orphanSeverityEnum();
            foreach ($graph->unusedSections($configuration->entryPoints) as $unused) {
                if ($this->templateUsesLayout($unused['file'], $parsingStates)) {
                    continue;
                }

                $issues[] = new Issue(
                    ruleId: 'dead-code/unused-section',
                    severity: $severity,
                    message: sprintf('Section "%s" is defined but never rendered.', $unused['section']),
                    file: $unused['file'],
                    context: ['section' => $unused['section']],
                );
            }
        }

        return $issues;
    }

    /**
     * @return list<Issue>
     */
    public function analyzeUnreachableBranches(string $file, string $source, ParsingState $parsingState, ?Configuration $configuration = null): array
    {
        $issues = [];
        $sourceLocator = new ViewHelperSourceLocator($source);

        $this->walker->walk(
            $parsingState->getRootNode(),
            function (ViewHelperNode $node) use (&$issues, $file, $sourceLocator, $configuration): void {
                if ($this->walker->isCoreViewHelper($node, 'if')) {
                    array_push($issues, ...$this->analyzeIfNode($file, $node, $sourceLocator, $configuration));
                }

                if ($this->walker->isCoreViewHelper($node, 'switch')) {
                    array_push($issues, ...$this->analyzeSwitchNode($file, $node, $sourceLocator, $configuration));
                }
            },
        );

        return $issues;
    }

    /**
     * @return list<Issue>
     */
    public function analyzeUnusedVariables(string $file, string $source, ParsingState $parsingState): array
    {
        $defined = [];
        $read = [];

        $this->walker->walk(
            $parsingState->getRootNode(),
            function (ViewHelperNode $node) use (&$defined): void {
                if ($this->walker->isCoreViewHelper($node, 'variable')) {
                    $name = $this->argumentExtractor->scalarArgument($node, 'name');
                    if ($name !== null) {
                        $defined[$name] = true;
                    }
                }

                if ($this->walker->isCoreViewHelper($node, 'for')) {
                    $as = $this->argumentExtractor->scalarArgument($node, 'as');
                    if ($as !== null) {
                        $defined[$as] = true;
                    }
                }
            },
        );

        $this->variableExtractor->collectDefinitionsFromSource($source, $defined);
        $this->variableExtractor->collectReadsFromNode($parsingState->getRootNode(), $read);
        $this->variableExtractor->collectReadsFromSource($source, $read);

        $implicitlyPassed = array_fill_keys($this->variableExtractor->extractLoopVariablesPassedViaAll($source), true);

        $issues = [];
        foreach (array_keys($defined) as $variable) {
            if (isset($read[$variable]) || isset($implicitlyPassed[$variable])) {
                continue;
            }

            $issues[] = new Issue(
                ruleId: 'dead-code/unused-variable',
                severity: Severity::Warning,
                message: sprintf('Variable "%s" is defined but never read.', $variable),
                file: $file,
                context: ['variable' => $variable],
            );
        }

        return $issues;
    }

    /**
     * @return list<Issue>
     */
    public function analyzePartialRenderCalls(string $file, string $source): array
    {
        $issues = [];

        foreach ($this->variableExtractor->extractPartialRenderCalls($source) as $call) {
            if (!$call['usesAll']) {
                continue;
            }

            $issues[] = new Issue(
                ruleId: 'dead-code/partial-all-arguments',
                severity: Severity::Warning,
                message: sprintf(
                    'Partial "%s" is rendered with _all; explicit unused-argument checks are skipped for this call.',
                    $call['partial'],
                ),
                file: $file,
                line: $call['line'],
                context: ['partial' => $call['partial']],
            );
        }

        return $issues;
    }

    /**
     * @param array<string, ParsingState> $parsingStates
     * @return list<Issue>
     */
    public function analyzePartialArguments(array $parsingStates, TemplateGraph $graph, ?Configuration $configuration = null): array
    {
        $issues = [];
        $callsByPartial = [];

        foreach ($graph->references() as $reference) {
            if ($reference['type'] !== 'partial' || ($reference['usesAll'] ?? false)) {
                continue;
            }

            if ($configuration !== null && $this->isExcludedFromAnalysis($reference['from'], $configuration)) {
                continue;
            }

            $callsByPartial[$reference['target']][] = $reference;
        }

        foreach ($callsByPartial as $partialName => $calls) {
            $partialFile = $this->resolvePartialFile($partialName, $graph);
            if ($partialFile === null || !isset($parsingStates[$partialFile])) {
                continue;
            }

            if ($configuration !== null && $this->isExcludedFromAnalysis($partialFile, $configuration)) {
                continue;
            }

            $partialSource = file_get_contents($partialFile) ?: '';
            $read = [];
            $this->variableExtractor->collectReadsFromSource($partialSource, $read);
            $this->variableExtractor->collectReadsFromNode($parsingStates[$partialFile]->getRootNode(), $read);

            $passedArguments = [];
            foreach ($calls as $call) {
                foreach ($call['arguments'] ?? [] as $argument) {
                    $passedArguments[$argument] = $call['from'];
                }
            }

            foreach (array_keys($passedArguments) as $argument) {
                if (isset($read[$argument])) {
                    continue;
                }

                $issues[] = new Issue(
                    ruleId: 'dead-code/unused-partial-argument',
                    severity: Severity::Warning,
                    message: sprintf(
                        'Argument "%s" passed to partial "%s" is never read inside the partial.',
                        $argument,
                        $partialName,
                    ),
                    file: $partialFile,
                    context: ['partial' => $partialName, 'argument' => $argument],
                );
            }
        }

        return $issues;
    }

    /**
     * @return list<Issue>
     */
    private function analyzeIfNode(string $file, ViewHelperNode $node, ViewHelperSourceLocator $sourceLocator, ?Configuration $configuration = null): array
    {
        $result = $this->argumentExtractor->evaluateBooleanArgument($node, 'condition');
        if ($result === null) {
            return [];
        }

        $line = $sourceLocator->lineFor($node);
        if ($result === false && $this->walker->findChildViewHelper($node, 'then') !== null) {
            if ($configuration !== null && !$configuration->isRuleEnabled('dead-code/unreachable-then')) {
                return [];
            }

            return [new Issue(
                ruleId: 'dead-code/unreachable-then',
                severity: Severity::Warning,
                message: 'Then-branch is unreachable because condition is constantly false.',
                file: $file,
                line: $line,
            )];
        }

        if ($result === true && $this->walker->findChildViewHelper($node, 'else') !== null) {
            if ($configuration !== null && !$configuration->isRuleEnabled('dead-code/unreachable-else')) {
                return [];
            }

            return [new Issue(
                ruleId: 'dead-code/unreachable-else',
                severity: Severity::Warning,
                message: 'Else-branch is unreachable because condition is constantly true.',
                file: $file,
                line: $line,
            )];
        }

        return [];
    }

    /**
     * @return list<Issue>
     */
    private function analyzeSwitchNode(string $file, ViewHelperNode $node, ViewHelperSourceLocator $sourceLocator, ?Configuration $configuration = null): array
    {
        if ($configuration !== null && !$configuration->isRuleEnabled('dead-code/unreachable-case')) {
            return [];
        }

        $expression = $this->argumentExtractor->extractLiteralValue($node, 'expression');
        if ($expression === null) {
            return [];
        }

        $switchValue = $this->expressionEvaluator->evaluateMixed($expression);
        if ($switchValue === null || is_object($switchValue)) {
            return [];
        }

        $issues = [];
        foreach ($node->getChildNodes() as $child) {
            if (!$child instanceof ViewHelperNode) {
                continue;
            }

            if ($this->walker->isCoreViewHelper($child, 'case')) {
                $caseValue = $this->argumentExtractor->extractLiteralValue($child, 'value');
                if ($caseValue === null) {
                    continue;
                }
                $evaluatedCase = $this->expressionEvaluator->evaluateMixed($caseValue);
                $equal = $this->expressionEvaluator->valuesEqual($switchValue, $evaluatedCase);
                if ($equal === false) {
                    $issues[] = new Issue(
                        ruleId: 'dead-code/unreachable-case',
                        severity: Severity::Info,
                        message: sprintf('Case "%s" is unreachable for constant switch expression.', (string)$caseValue),
                        file: $file,
                        line: $sourceLocator->lineFor($child),
                    );
                }
            }
        }

        return $issues;
    }

    private function resolvePartialFile(string $partialName, TemplateGraph $graph): ?string
    {
        foreach ($graph->partialNamesByFile() as $file => $names) {
            if (in_array($partialName, $names, true)) {
                return $file;
            }
        }

        return null;
    }

    private static function displayPartialName(string $file, TemplateGraph $graph): string
    {
        $names = $graph->partialNamesByFile()[$file] ?? [];
        if ($names !== []) {
            return $names[0];
        }

        return basename($file);
    }

    /**
     * @param array<string, ParsingState> $parsingStates
     */
    private function templateUsesLayout(string $file, array $parsingStates): bool
    {
        if (!isset($parsingStates[$file])) {
            return false;
        }

        $layoutName = $parsingStates[$file]->getUnevaluatedLayoutName();
        if ($layoutName instanceof \TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\TextNode) {
            return trim($layoutName->getText()) !== '';
        }

        return is_string($layoutName) && $layoutName !== '';
    }

    private function isExcludedFromAnalysis(string $file, Configuration $configuration): bool
    {
        return GlobMatcher::matchesAny($file, $configuration->excludeAnalysisPatterns);
    }
}
