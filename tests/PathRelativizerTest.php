<?php

declare(strict_types=1);

namespace Cru\Fluidlint\Tests;

use Cru\Fluidlint\Util\PathRelativizer;
use PHPUnit\Framework\TestCase;

final class PathRelativizerTest extends TestCase
{
    public function testResolveBaseUsesSingleDirectoryArgument(): void
    {
        $fixtures = realpath(__DIR__ . '/Fixtures');
        self::assertIsString($fixtures);

        self::assertSame($fixtures, PathRelativizer::resolveBase([$fixtures]));
    }

    public function testResolveBaseUsesParentDirectoryForSingleFile(): void
    {
        $file = realpath(__DIR__ . '/Fixtures/Resources/Private/Templates/List.html');
        self::assertIsString($file);

        self::assertSame(dirname($file), PathRelativizer::resolveBase([$file]));
    }

    public function testRelativizeStripsBaseDirectory(): void
    {
        $base = '/project';
        $path = '/project/Resources/Private/Templates/List.html';

        self::assertSame('Resources/Private/Templates/List.html', PathRelativizer::relativize($path, $base));
    }

    public function testRelativizeReturnsDotForBasePath(): void
    {
        self::assertSame('.', PathRelativizer::relativize('/project', '/project'));
    }

    public function testRelativizeComplexityKeys(): void
    {
        $base = '/project';
        $complexity = [
            '/project/Templates/List.html' => ['complexity' => 2],
            '/project/Partials/Item.html' => ['complexity' => 1],
        ];

        self::assertSame([
            'Templates/List.html' => ['complexity' => 2],
            'Partials/Item.html' => ['complexity' => 1],
        ], PathRelativizer::relativizeComplexityKeys($complexity, $base));
    }

    public function testRelativizeUsesRealPathsFromFixtures(): void
    {
        $fixtures = realpath(__DIR__ . '/Fixtures');
        $file = realpath(__DIR__ . '/Fixtures/Resources/Private/Templates/List.html');
        self::assertIsString($fixtures);
        self::assertIsString($file);

        self::assertSame(
            'Resources/Private/Templates/List.html',
            PathRelativizer::relativize($file, $fixtures),
        );
    }
}
