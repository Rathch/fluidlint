<?php

declare(strict_types=1);

namespace Cru\Fluidlint\Analysis;

use Cru\Fluidlint\Configuration\Configuration;
use Cru\Fluidlint\Report\Issue;
use Cru\Fluidlint\Report\Severity;
use TYPO3Fluid\Fluid\Core\Parser\ParsingState;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\ViewHelperNode;

final class ComplexityAnalyzer
{
    /**
     * @return list<Issue>
     */
    public function analyze(string $file, string $source, ParsingState $parsingState, Configuration $configuration): array
    {
        $walker = new AstWalker();
        $score = 1;
        $contributions = [];

        $walker->walk(
            $parsingState->getRootNode(),
            function (ViewHelperNode $node) use (&$score, &$contributions, $walker, $source): void {
                $identifier = $walker->viewHelperIdentifier($node);
                $increment = 0;

                if ($walker->isCoreViewHelper($node, 'if')) {
                    $increment = 1;
                } elseif ($walker->isCoreViewHelper($node, 'switch')) {
                    $increment = 1;
                } elseif ($walker->isCoreViewHelper($node, 'case')) {
                    $increment = 1;
                } elseif ($walker->isCoreViewHelper($node, 'for') || $walker->isCoreViewHelper($node, 'while')) {
                    $increment = 1;
                }

                if ($increment === 0) {
                    return;
                }

                $score += $increment;
                $line = self::lineForViewHelper($source, $node);
                $contributions[] = [
                    'viewHelper' => $identifier,
                    'line' => $line,
                    'points' => $increment,
                ];
            },
        );

        if ($score <= $configuration->complexityWarn) {
            return [];
        }

        $severity = $score >= $configuration->complexityError
            ? Severity::Error
            : Severity::Warning;

        return [
            new Issue(
                ruleId: 'complexity/threshold-exceeded',
                severity: $severity,
                message: sprintf('Cyclomatic complexity %d exceeds threshold.', $score),
                file: $file,
                context: [
                    'complexity' => $score,
                    'warn' => $configuration->complexityWarn,
                    'error' => $configuration->complexityError,
                    'contributions' => $contributions,
                ],
            ),
        ];
    }

    /**
     * @return array{complexity: int, contributions: list<array{viewHelper: string, line: int|null, points: int}>}
     */
    public function measure(string $source, ParsingState $parsingState): array
    {
        $walker = new AstWalker();
        $score = 1;
        $contributions = [];

        $walker->walk(
            $parsingState->getRootNode(),
            function (ViewHelperNode $node) use (&$score, &$contributions, $walker, $source): void {
                $identifier = $walker->viewHelperIdentifier($node);
                $increment = 0;

                if ($walker->isCoreViewHelper($node, 'if')
                    || $walker->isCoreViewHelper($node, 'switch')
                    || $walker->isCoreViewHelper($node, 'case')
                    || $walker->isCoreViewHelper($node, 'for')
                    || $walker->isCoreViewHelper($node, 'while')
                ) {
                    $increment = 1;
                }

                if ($increment === 0) {
                    return;
                }

                $score += $increment;
                $contributions[] = [
                    'viewHelper' => $identifier,
                    'line' => self::lineForViewHelper($source, $node),
                    'points' => $increment,
                ];
            },
        );

        return ['complexity' => $score, 'contributions' => $contributions];
    }

    private static function lineForViewHelper(string $source, ViewHelperNode $node): ?int
    {
        $needle = '<' . $node->getNamespace() . ':' . str_replace('.', '.', $node->getName());
        $position = strpos($source, $needle);
        if ($position === false) {
            $position = strpos($source, '{' . $node->getNamespace() . ':' . $node->getName());
        }
        if ($position === false) {
            return null;
        }

        return substr_count(substr($source, 0, $position), "\n") + 1;
    }
}
