<?php

declare(strict_types=1);

namespace Cru\Fluidlint\Parser;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperInterface;

/**
 * Generic stub for unknown ViewHelpers – accepts any argument and renders child nodes.
 */
final class PassthroughViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
    }

    public function validateAdditionalArguments(array $arguments): void
    {
    }

    public function render(): string
    {
        return $this->renderChildren();
    }

    public static function getClass(): string
    {
        return self::class;
    }
}
