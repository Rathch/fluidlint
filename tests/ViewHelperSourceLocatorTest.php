<?php

declare(strict_types=1);

namespace Cru\Fluidlint\Tests;

use Cru\Fluidlint\Analysis\ViewHelperSourceLocator;
use Cru\Fluidlint\Parser\TemplateParserFactory;
use PHPUnit\Framework\TestCase;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\ViewHelperNode;

final class ViewHelperSourceLocatorTest extends TestCase
{
    public function testFindsSequentialViewHelperPositions(): void
    {
        $source = "<f:if condition=\"1\"></f:if>\n<f:switch expression=\"x\"><f:case value=\"a\"></f:case></f:switch>";
        $parser = new TemplateParserFactory();
        $state = $parser->parse($source, 'template.html');
        $locator = new ViewHelperSourceLocator($source);

        $lines = [];
        $walker = new \Cru\Fluidlint\Analysis\AstWalker();
        $walker->walk(
            $state->getRootNode(),
            static function (ViewHelperNode $node) use ($locator, &$lines): void {
                $lines[] = $locator->lineFor($node);
            },
        );

        self::assertSame([1, 2, 2], array_values(array_filter($lines)));
    }
}
