<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: 2025 Christian Rath-Ulrich
//
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Cru\Fluidlint\Analysis;

use Symfony\Component\Finder\Finder;

/**
 * Collects Fluid template and partial root paths referenced in TypoScript.
 */
final class TypoScriptTemplateIndex
{
    /**
     * @param list<string> $templateNames
     * @param list<string> $partialRootPaths Absolute paths to partial root directories
     * @param list<string> $templateRootPaths Absolute paths to template root directories
     * @param list<string> $referencedPartialNames Partial names referenced directly in TypoScript
     */
    public function __construct(
        private readonly array $templateNames,
        private readonly array $partialRootPaths,
        private readonly array $templateRootPaths,
        private readonly array $referencedPartialNames = [],
    ) {
    }

    /**
     * @param list<string> $typoScriptGlobs Glob patterns relative to $projectRoot
     */
    public static function build(string $projectRoot, array $typoScriptGlobs): self
    {
        $templateNames = [];
        $partialRootPaths = [];
        $templateRootPaths = [];
        $referencedPartialNames = [];

        $projectRoot = rtrim(str_replace('\\', '/', realpath($projectRoot) ?: $projectRoot), '/');
        $typoScriptFiles = self::findTypoScriptFiles($projectRoot, $typoScriptGlobs);
        $fileReferences = [];

        foreach ($typoScriptFiles as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            if (preg_match_all('/\btemplateName\s*=\s*([A-Za-z0-9_]+)/', $content, $matches)) {
                foreach ($matches[1] as $name) {
                    $templateNames[$name] = true;
                }
            }

            foreach (self::extractRootPathAssignments($content) as $assignment) {
                $resolved = self::resolveTypoScriptPath($assignment['path'], $projectRoot, $file);
                if ($resolved === null || !is_dir($resolved)) {
                    continue;
                }

                if ($assignment['type'] === 'partial') {
                    $partialRootPaths[$resolved] = true;
                } else {
                    $templateRootPaths[$resolved] = true;
                }
            }

            foreach (self::extractReferencedFluidFiles($content) as $fluidFile) {
                $fileReferences[] = ['path' => $fluidFile, 'source' => $file];
            }
        }

        foreach ($fileReferences as $reference) {
            $resolvedFile = self::resolveTypoScriptPath($reference['path'], $projectRoot, $reference['source']);
            if ($resolvedFile === null || !is_file($resolvedFile) || !str_ends_with(strtolower($resolvedFile), '.html')) {
                continue;
            }

            if (!str_contains(strtolower($resolvedFile), '/partials/')) {
                continue;
            }

            foreach (TemplateGraph::partialNamesForFile($resolvedFile, array_keys($partialRootPaths)) as $name) {
                $referencedPartialNames[$name] = true;
            }
        }

        return new self(
            array_keys($templateNames),
            array_keys($partialRootPaths),
            array_keys($templateRootPaths),
            array_keys($referencedPartialNames),
        );
    }

    /**
     * @return list<string>
     */
    public function templateNames(): array
    {
        return $this->templateNames;
    }

    /**
     * @return list<string>
     */
    public function partialRootPaths(): array
    {
        return $this->partialRootPaths;
    }

    /**
     * @return list<string>
     */
    public function templateRootPaths(): array
    {
        return $this->templateRootPaths;
    }

    /**
     * @return list<string>
     */
    public function referencedPartialNames(): array
    {
        return $this->referencedPartialNames;
    }

    public function isReferencedTemplateFile(string $file): bool
    {
        $basename = preg_replace('/\.(fluid\.)?html$/', '', basename($file)) ?? basename($file);
        if (!in_array($basename, $this->templateNames, true)) {
            return false;
        }

        $normalizedFile = str_replace('\\', '/', $file);
        foreach ($this->templateRootPaths as $root) {
            $normalizedRoot = rtrim(str_replace('\\', '/', $root), '/');
            if (str_starts_with($normalizedFile, $normalizedRoot . '/')) {
                return true;
            }
        }

        return $this->templateRootPaths === [];
    }

    /**
     * @param list<string> $globs
     * @return list<string>
     */
    private static function findTypoScriptFiles(string $projectRoot, array $globs): array
    {
        if ($globs === []) {
            $globs = ['Configuration/**/*.typoscript', 'Configuration/**/*.ts'];
        }

        $files = [];
        $finder = Finder::create()
            ->files()
            ->in($projectRoot)
            ->name('*.typoscript')
            ->name('*.ts');

        foreach ($finder as $file) {
            $path = $file->getRealPath() ?: $file->getPathname();
            $relative = ltrim(substr(str_replace('\\', '/', $path), strlen($projectRoot)), '/');
            if (GlobMatcher::matchesAny($relative, $globs)) {
                $files[] = $path;
            }
        }

        return array_values(array_unique($files));
    }

    /**
     * @return list<array{type: 'partial'|'template', path: string}>
     */
    private static function extractRootPathAssignments(string $content): array
    {
        $assignments = [];

        if (preg_match_all('/\b(partialRootPaths?|templateRootPaths?)\s*\{([^}]+)\}/s', $content, $blocks, PREG_SET_ORDER)) {
            foreach ($blocks as $block) {
                $type = str_contains($block[1], 'partial') ? 'partial' : 'template';
                if (preg_match_all('/\b\d+\s*=\s*([^\n]+)/', $block[2], $paths)) {
                    foreach ($paths[1] as $path) {
                        $assignments[] = ['type' => $type, 'path' => trim($path)];
                    }
                }
            }
        }

        if (preg_match_all('/\b(partialRootPaths?|templateRootPaths?)\.[^\s=]+\s*=\s*([^\n]+)/', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $type = str_contains($match[1], 'partial') ? 'partial' : 'template';
                $assignments[] = ['type' => $type, 'path' => trim($match[2])];
            }
        }

        if (preg_match_all('/\b(partialRootPath|templateRootPath)\s*=\s*([^\n]+)/', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $type = str_contains($match[1], 'partial') ? 'partial' : 'template';
                $assignments[] = ['type' => $type, 'path' => trim($match[2])];
            }
        }

        return array_values(array_filter(
            $assignments,
            static fn (array $assignment): bool => $assignment['path'] !== '' && !str_starts_with($assignment['path'], '{$'),
        ));
    }

    /**
     * @return list<string>
     */
    private static function extractReferencedFluidFiles(string $content): array
    {
        $files = [];

        if (preg_match_all('/\bfile\s*=\s*([^\n]+)/', $content, $matches)) {
            foreach ($matches[1] as $path) {
                $path = trim($path);
                if ($path !== '' && !str_starts_with($path, '{$')) {
                    $files[] = $path;
                }
            }
        }

        return $files;
    }

    private static function resolveTypoScriptPath(string $path, string $projectRoot, string $sourceFile): ?string
    {
        $path = trim($path, " \t\"'");

        if (preg_match('/^EXT:([^\/]+)\/(.+)$/', $path, $matches) === 1) {
            $relative = $matches[2];
            $candidates = [
                $projectRoot . '/' . $relative,
                dirname($sourceFile, 5) . '/' . $relative,
                dirname($sourceFile, 4) . '/' . $relative,
                dirname($sourceFile, 3) . '/' . $relative,
            ];

            foreach ($candidates as $candidate) {
                $resolved = realpath($candidate);
                if ($resolved !== false) {
                    return rtrim(str_replace('\\', '/', $resolved), '/');
                }
            }

            return null;
        }

        if (str_starts_with($path, '/')) {
            $resolved = realpath($path);
            return $resolved !== false ? rtrim(str_replace('\\', '/', $resolved), '/') : null;
        }

        $resolved = realpath($projectRoot . '/' . $path);
        return $resolved !== false ? rtrim(str_replace('\\', '/', $resolved), '/') : null;
    }
}
