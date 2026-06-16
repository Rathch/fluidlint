<?php

declare(strict_types=1);

namespace Cru\Fluidlint\Parser;

use TYPO3Fluid\Fluid\Core\Rendering\RenderingContext;
use TYPO3Fluid\Fluid\Core\Variables\StandardVariableProvider;

final class LintRenderingContext extends RenderingContext
{
    public function __construct()
    {
        parent::__construct();
        $this->setViewHelperResolver(new StubViewHelperResolver());
        $this->setVariableProvider(new StandardVariableProvider());
    }
}
