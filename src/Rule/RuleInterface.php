<?php

declare(strict_types=1);

namespace Cru\Fluidlint\Rule;

use Cru\Fluidlint\Configuration\Configuration;
use Cru\Fluidlint\Report\Issue;
use TYPO3Fluid\Fluid\Core\Parser\ParsingState;

interface RuleInterface
{
    public function getId(): string;

    /**
     * @return list<Issue>
     */
    public function analyze(string $file, string $source, ParsingState $parsingState, Configuration $configuration): array;
}
