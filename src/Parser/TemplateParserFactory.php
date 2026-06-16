<?php

declare(strict_types=1);

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
