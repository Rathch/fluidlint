<?php

declare(strict_types=1);

namespace Cru\Fluidlint\Analysis;

use Cru\Fluidlint\Configuration\Configuration;
use Cru\Fluidlint\Report\Issue;
use Cru\Fluidlint\Report\Severity;
use TYPO3Fluid\Fluid\Core\Parser\ParsingState;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\NodeInterface;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\TextNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\ViewHelperNode;

final class DeadCodeAnalyzer
{
    public function __construct(
        private readonly ExpressionEvaluator $expressionEvaluator = new ExpressionEvaluator(),
        private readonly AstWalker $walker = new AstWalker(),
        private readonly ArgumentExtractor $argumentExtractor = new ArgumentExtractor(),
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
            $source = file_get_contents($file) ?: '';
            array_push($issues, ...$this->analyzeUnreachableBranches($file, $source, $parsingState));
            array_push($issues, ...$this->analyzeUnusedVariables($file, $source, $parsingState));
        }

        $severity = $configuration->orphanSeverityEnum();
        foreach ($graph->orphanPartials() as $orphan) {
            $issues[] = new Issue(
                ruleId: 'dead-code/orphan-partial',
                severity: $severity,
                message: sprintf('Partial "%s" is not referenced by any template.', basename($orphan)),
                file: $orphan,
            );
        }

        foreach ($graph->orphanLayouts() as $orphan) {
            $issues[] = new Issue(
                ruleId: 'dead-code/orphan-layout',
                severity: $severity,
                message: sprintf('Layout "%s" is not referenced by any template.', basename($orphan)),
                file: $orphan,
            );
        }

        foreach ($graph->unusedSections($configuration->entryPoints) as $unused) {
            $issues[] = new Issue(
                ruleId: 'dead-code/unused-section',
                severity: $severity,
                message: sprintf('Section "%s" is defined but never rendered.', $unused['section']),
                file: $unused['file'],
                context: ['section' => $unused['section']],
            );
        }

        return $issues;
    }

    /**
     * @return list<Issue>
     */
    public function analyzeUnreachableBranches(string $file, string $source, ParsingState $parsingState): array
    {
        $issues = [];

        $this->walker->walk(
            $parsingState->getRootNode(),
            function (ViewHelperNode $node) use (&$issues, $file, $source): void {
                if ($this->walker->isCoreViewHelper($node, 'if')) {
                    array_push($issues, ...$this->analyzeIfNode($file, $source, $node));
                }

                if ($this->walker->isCoreViewHelper($node, 'switch')) {
                    array_push($issues, ...$this->analyzeSwitchNode($file, $source, $node));
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

        $this->collectVariableDefinitionsFromSource($source, $defined);

        $this->collectVariableReads($parsingState->getRootNode(), $read);
        $this->collectVariableReadsFromSource($source, $read);

        $issues = [];
        foreach (array_keys($defined) as $variable) {
            if (!isset($read[$variable])) {
                $issues[] = new Issue(
                    ruleId: 'dead-code/unused-variable',
                    severity: Severity::Warning,
                    message: sprintf('Variable "%s" is defined but never read.', $variable),
                    file: $file,
                    context: ['variable' => $variable],
                );
            }
        }

        return $issues;
    }

    /**
     * @return list<Issue>
     */
    private function analyzeIfNode(string $file, string $source, ViewHelperNode $node): array
    {
        $result = $this->argumentExtractor->evaluateBooleanArgument($node, 'condition');
        if ($result === null) {
            return [];
        }

        $line = $this->lineForViewHelper($source, $node);
        if ($result === false && $this->walker->findChildViewHelper($node, 'then') !== null) {
            return [new Issue(
                ruleId: 'dead-code/unreachable-then',
                severity: Severity::Warning,
                message: 'Then-branch is unreachable because condition is constantly false.',
                file: $file,
                line: $line,
            )];
        }

        if ($result === true && $this->walker->findChildViewHelper($node, 'else') !== null) {
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
    private function analyzeSwitchNode(string $file, string $source, ViewHelperNode $node): array
    {
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
                        line: $this->lineForViewHelper($source, $child),
                    );
                }
            }
        }

        return $issues;
    }

    /**
     * @param array<string, true> $read
     */
    private function collectVariableReads(NodeInterface $node, array &$read): void
    {
        if ($node instanceof TextNode) {
            $this->extractVariablesFromText($node->getText(), $read);
        }

        if ($node instanceof ViewHelperNode) {
            foreach ($node->getArguments() as $argument) {
                if (is_string($argument)) {
                    $this->extractVariablesFromText($argument, $read);
                }
            }
        }

        if ($node instanceof ViewHelperNode || method_exists($node, 'getChildNodes')) {
            foreach ($node->getChildNodes() as $child) {
                if ($child instanceof NodeInterface) {
                    $this->collectVariableReads($child, $read);
                }
            }
        }
    }

    /**
     * @param array<string, true> $defined
     */
    private function collectVariableDefinitionsFromSource(string $source, array &$defined): void
    {
        if (preg_match_all('/<f:variable\b[^>]*\bname\s*=\s*["\']([^"\']+)["\']/i', $source, $matches)) {
            foreach ($matches[1] as $name) {
                $defined[$name] = true;
            }
        }

        if (preg_match_all('/\{f:variable\([^)]*\bname\s*:\s*["\']([^"\']+)["\']/i', $source, $matches)) {
            foreach ($matches[1] as $name) {
                $defined[$name] = true;
            }
        }

        if (preg_match_all('/<f:for\b[^>]*\bas\s*=\s*["\']([^"\']+)["\']/i', $source, $matches)) {
            foreach ($matches[1] as $name) {
                $defined[$name] = true;
            }
        }
    }

    /**
     * @param array<string, true> $read
     */
    private function collectVariableReadsFromSource(string $source, array &$read): void
    {
        if (preg_match_all('/\{([a-zA-Z][a-zA-Z0-9_.]*)\}/', $source, $matches)) {
            foreach ($matches[1] as $match) {
                $root = explode('.', $match)[0];
                $read[$root] = true;
            }
        }
    }

    /**
     * @param array<string, true> $read
     */
    private function extractVariablesFromText(string $text, array &$read): void
    {
        if (preg_match_all('/\{([a-zA-Z][a-zA-Z0-9_.]*)\}/', $text, $matches)) {
            foreach ($matches[1] as $match) {
                $root = explode('.', $match)[0];
                $read[$root] = true;
            }
        }
    }

    private function lineForViewHelper(string $source, ViewHelperNode $node): ?int
    {
        $needle = '<f:' . $node->getName();
        $position = strpos($source, $needle);
        if ($position === false) {
            $position = strpos($source, '{f:' . $node->getName());
        }
        if ($position === false) {
            return null;
        }

        return substr_count(substr($source, 0, $position), "\n") + 1;
    }
}
