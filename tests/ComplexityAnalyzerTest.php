<?php

declare(strict_types=1);

namespace Cru\Fluidlint\Tests;

use Cru\Fluidlint\Analysis\ComplexityAnalyzer;
use Cru\Fluidlint\Parser\TemplateParserFactory;
use PHPUnit\Framework\TestCase;

final class ComplexityAnalyzerTest extends TestCase
{
    public function testContributionLineNumbersFollowDocumentOrder(): void
    {
        $source = <<<HTML
<f:if condition="1">
    content
</f:if>
<f:if condition="2">
    more
</f:if>
HTML;

        $parser = new TemplateParserFactory();
        $state = $parser->parse($source, 'template.html');
        $measurement = (new ComplexityAnalyzer())->measure($source, $state);

        $ifLines = array_values(array_map(
            static fn (array $contribution): int => (int)$contribution['line'],
            array_filter(
                $measurement['contributions'],
                static fn (array $contribution): bool => $contribution['viewHelper'] === 'f:if',
            ),
        ));

        self::assertSame([1, 4], $ifLines);
    }

    public function testContributionLineNumbersForNestedLayoutTemplate(): void
    {
        $file = __DIR__ . '/../gsb_core/Resources/Extensions/fluid_styled_content/Private/Layouts/Default.html';
        if (!is_file($file)) {
            self::markTestSkipped('gsb_core fixture layout is not available.');
        }

        $source = file_get_contents($file);
        self::assertIsString($source);

        $parser = new TemplateParserFactory();
        $state = $parser->parse($source, $file);
        $measurement = (new ComplexityAnalyzer())->measure($source, $state);

        $ifLines = array_values(array_map(
            static fn (array $contribution): int => (int)$contribution['line'],
            array_filter(
                $measurement['contributions'],
                static fn (array $contribution): bool => $contribution['viewHelper'] === 'f:if',
            ),
        ));

        self::assertNotEmpty($ifLines);
        self::assertNotSame(array_unique([$ifLines[0]]), $ifLines);
        self::assertContains(12, $ifLines);
        self::assertContains(20, $ifLines);
        self::assertContains(24, $ifLines);
        self::assertContains(26, $ifLines);
    }
}
