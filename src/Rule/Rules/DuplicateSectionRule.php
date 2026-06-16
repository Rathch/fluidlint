<?php

declare(strict_types=1);

namespace Cru\Fluidlint\Rule\Rules;

use Cru\Fluidlint\Analysis\ArgumentExtractor;
use Cru\Fluidlint\Analysis\AstWalker;
use Cru\Fluidlint\Configuration\Configuration;
use Cru\Fluidlint\Report\Issue;
use Cru\Fluidlint\Report\Severity;
use Cru\Fluidlint\Rule\RuleInterface;
use TYPO3Fluid\Fluid\Core\Parser\ParsingState;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\ViewHelperNode;

final class DuplicateSectionRule implements RuleInterface
{
    private readonly ArgumentExtractor $argumentExtractor;

    public function __construct(?ArgumentExtractor $argumentExtractor = null)
    {
        $this->argumentExtractor = $argumentExtractor ?? new ArgumentExtractor();
    }

    public function getId(): string
    {
        return 'fluid/duplicate-section';
    }

    public function analyze(string $file, string $source, ParsingState $parsingState, Configuration $configuration): array
    {
        $walker = new AstWalker();
        $sections = [];
        $issues = [];

        $walker->walk(
            $parsingState->getRootNode(),
            function (ViewHelperNode $node) use (&$sections, &$issues, $walker, $file, $source): void {
                if (!$walker->isCoreViewHelper($node, 'section')) {
                    return;
                }

                $name = $this->argumentExtractor->scalarArgument($node, 'name');
                if ($name === null || $name === '') {
                    return;
                }

                if (isset($sections[$name])) {
                    $issues[] = new Issue(
                        ruleId: $this->getId(),
                        severity: Severity::Error,
                        message: sprintf('Duplicate section name "%s".', $name),
                        file: $file,
                        line: self::lineForNode($source, 'section'),
                        context: ['section' => $name],
                    );
                    return;
                }

                $sections[$name] = true;
            },
        );

        return $issues;
    }

    private static function lineForNode(string $source, string $tag): ?int
    {
        $position = strpos($source, '<f:' . $tag);
        if ($position === false) {
            return null;
        }

        return substr_count(substr($source, 0, $position), "\n") + 1;
    }
}
