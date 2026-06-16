<?php

declare(strict_types=1);

namespace Cru\Fluidlint\Tests\Fixtures\ViewHelpers;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Simulates TYPO3 CMS ViewHelpers that require dependency injection at construction time.
 */
final class UnconstructableViewHelper extends AbstractViewHelper
{
    public function __construct(private readonly object $dependency)
    {
    }

    public function render(): string
    {
        return '';
    }
}
