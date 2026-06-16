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
use TYPO3Fluid\Fluid\Core\Parser\ParsingState;
use TYPO3Fluid\Fluid\Core\Parser\TemplateParser;

final class TemplateParserFactory
{
    private ?TemplateParser $parser = null;

    public function createParser(): TemplateParser
    {
        if ($this->parser === null) {
            $context = new LintRenderingContext();
            $parser = new TemplateParser();
            $parser->setRenderingContext($context);
            $this->parser = $parser;
        }

        return $this->parser;
    }

    /**
     * @throws ParserException
     */
    public function parse(string $source, string $identifier): ParsingState
    {
        return $this->createParser()->parse($source, $identifier, $identifier);
    }
}
