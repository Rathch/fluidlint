<?php

declare(strict_types=1);

namespace Cru\Fluidlint\Rule;

use Cru\Fluidlint\Configuration\Configuration;
use Cru\Fluidlint\Report\Issue;
use Cru\Fluidlint\Report\Severity;
use TYPO3Fluid\Fluid\Core\Parser\ParsingState;

final class RuleEngine
{
    /**
     * @param list<RuleInterface> $rules
     */
    public function __construct(
        private readonly array $rules,
    ) {
    }

    /**
     * @return list<Issue>
     */
    public function analyze(string $file, string $source, ParsingState $parsingState, Configuration $configuration): array
    {
        $issues = [];
        foreach ($this->rules as $rule) {
            if (!$configuration->isRuleEnabled($rule->getId())) {
                continue;
            }
            array_push($issues, ...$rule->analyze($file, $source, $parsingState, $configuration));
        }

        return $issues;
    }

    public static function createDefault(): self
    {
        return new self([
            new Rules\NestingDepthRule(),
            new Rules\EmptySectionRule(),
            new Rules\DuplicateSectionRule(),
        ]);
    }
}
