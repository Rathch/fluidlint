<?php

declare(strict_types=1);

namespace Cru\Fluidlint;

use Cru\Fluidlint\Command\ComplexityCommand;
use Cru\Fluidlint\Command\DeadCodeCommand;
use Cru\Fluidlint\Command\ScanCommand;
use Symfony\Component\Console\Application as SymfonyApplication;

final class Application extends SymfonyApplication
{
    public function __construct()
    {
        parent::__construct('fluidlint', '1.0.0');
        $this->addCommands([
            new ScanCommand(),
            new ComplexityCommand(),
            new DeadCodeCommand(),
        ]);
    }
}
