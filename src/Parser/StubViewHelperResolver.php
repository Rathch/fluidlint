<?php

declare(strict_types=1);

namespace Cru\Fluidlint\Parser;

use TYPO3Fluid\Fluid\Core\Parser\Exception as ParserException;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperResolver;

/**
 * Resolves core Fluid ViewHelpers normally; falls back to PassthroughViewHelper for unknown VHs.
 */
final class StubViewHelperResolver extends ViewHelperResolver
{
    public function isNamespaceValid(string $namespaceIdentifier): bool
    {
        return true;
    }

    public function resolveViewHelperClassName(string $namespaceIdentifier, string $methodIdentifier): string
    {
        try {
            return parent::resolveViewHelperClassName($namespaceIdentifier, $methodIdentifier);
        } catch (ParserException) {
            return PassthroughViewHelper::class;
        }
    }

    public function createViewHelperInstanceFromClassName(string $viewHelperClassName): ViewHelperInterface
    {
        if ($viewHelperClassName === PassthroughViewHelper::class) {
            return new PassthroughViewHelper();
        }

        return parent::createViewHelperInstanceFromClassName($viewHelperClassName);
    }
}
