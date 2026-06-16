<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: 2025 Christian Rath-Ulrich
//
// SPDX-License-Identifier: GPL-3.0-or-later

/*
 * This file is part of the package cru/fluidlint
 *
 * Copyright (C) 2026 Christian Rath-Ulrich
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Cru\Fluidlint\Parser;

use TYPO3Fluid\Fluid\Core\Parser\Exception as ParserException;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperResolver;

/**
 * Resolves core Fluid ViewHelpers normally; falls back to PassthroughViewHelper for unknown VHs.
 *
 * TYPO3 CMS ViewHelpers often depend on bootstrapped services (e.g. ImageService). When fluidlint
 * runs inside a TYPO3 project, templates may resolve to TYPO3\CMS\Fluid classes – we remap those
 * to TYPO3Fluid equivalents when possible and stub the rest.
 */
final class StubViewHelperResolver extends ViewHelperResolver
{
    private const CMS_VIEWHELPER_NAMESPACE = 'TYPO3\\CMS\\Fluid\\ViewHelpers\\';
    private const FLUID_VIEWHELPER_NAMESPACE = 'TYPO3Fluid\\Fluid\\ViewHelpers\\';

    public function isNamespaceValid(string $namespaceIdentifier): bool
    {
        return true;
    }

    public function resolveViewHelperClassName(string $namespaceIdentifier, string $methodIdentifier): string
    {
        try {
            return $this->preferFluidCoreViewHelper(
                parent::resolveViewHelperClassName($namespaceIdentifier, $methodIdentifier),
            );
        } catch (ParserException) {
            return PassthroughViewHelper::class;
        }
    }

    public function createViewHelperInstanceFromClassName(string $viewHelperClassName): ViewHelperInterface
    {
        if ($viewHelperClassName === PassthroughViewHelper::class) {
            return new PassthroughViewHelper();
        }

        $viewHelperClassName = $this->preferFluidCoreViewHelper($viewHelperClassName);

        if (str_starts_with($viewHelperClassName, self::FLUID_VIEWHELPER_NAMESPACE)) {
            return parent::createViewHelperInstanceFromClassName($viewHelperClassName);
        }

        try {
            return parent::createViewHelperInstanceFromClassName($viewHelperClassName);
        } catch (\Throwable) {
            return new PassthroughViewHelper();
        }
    }

    /**
     * Maps TYPO3\CMS Fluid ViewHelper classes to their TYPO3Fluid core equivalents when available.
     */
    private function preferFluidCoreViewHelper(string $className): string
    {
        if (!str_starts_with($className, self::CMS_VIEWHELPER_NAMESPACE)) {
            return $className;
        }

        $fluidClassName = self::FLUID_VIEWHELPER_NAMESPACE . substr($className, strlen(self::CMS_VIEWHELPER_NAMESPACE));

        return class_exists($fluidClassName) ? $fluidClassName : $className;
    }
}
