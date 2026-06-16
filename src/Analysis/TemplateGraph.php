<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: 2025 Christian Rath-Ulrich
//
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Cru\Fluidlint\Analysis;

use TYPO3Fluid\Fluid\Core\Parser\ParsingState;

final class TemplateGraph
{
    /**
     * @param array<string, list<string>> $filesByType
     * @param array<string, list<string>> $sectionsByFile
     * @param list<array{from: string, type: string, target: string, usesAll?: bool, arguments?: list<string>}> $references
     * @param array<string, list<string>> $partialNamesByFile
     * @param list<string> $typoScriptReferencedPartials
     */
    public function __construct(
        private readonly array $filesByType,
        private readonly array $sectionsByFile,
        private readonly array $references,
        private readonly array $partialNamesByFile,
        private readonly array $typoScriptReferencedPartials = [],
    ) {
    }

    /**
     * @param array<string, ParsingState> $parsingStates
     */
    public static function build(array $parsingStates, ?TypoScriptTemplateIndex $typoScriptIndex = null): self
    {
        $walker = new AstWalker();
        $extractor = new ArgumentExtractor();
        $variableExtractor = new VariableReferenceExtractor();
        $filesByType = ['template' => [], 'partial' => [], 'layout' => []];
        $sectionsByFile = [];
        $references = [];
        $partialNamesByFile = [];
        $partialRootPaths = $typoScriptIndex?->partialRootPaths() ?? [];

        foreach (array_keys($parsingStates) as $file) {
            $type = self::detectType($file);
            $filesByType[$type][] = $file;
            if ($type === 'partial') {
                $partialNamesByFile[$file] = self::partialNamesForFile($file, $partialRootPaths);
            }
        }

        foreach ($parsingStates as $file => $parsingState) {
            $source = file_get_contents($file) ?: '';
            $layoutName = $parsingState->getUnevaluatedLayoutName();
            if (is_string($layoutName) && $layoutName !== '') {
                $references[] = ['from' => $file, 'type' => 'layout', 'target' => $layoutName];
            } elseif ($layoutName instanceof \TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\TextNode) {
                $references[] = ['from' => $file, 'type' => 'layout', 'target' => $layoutName->getText()];
            }

            $sections = [];
            $walker->walk(
                $parsingState->getRootNode(),
                function (\TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\ViewHelperNode $node) use (&$sections, &$references, $walker, $extractor, $file): void {
                    if ($walker->isCoreViewHelper($node, 'section')) {
                        $name = $extractor->scalarArgument($node, 'name');
                        if ($name !== null) {
                            $sections[$name] = true;
                        }
                    }

                    if ($walker->isCoreViewHelper($node, 'render')) {
                        $partial = $extractor->scalarArgument($node, 'partial');
                        if ($partial !== null) {
                            $references[] = ['from' => $file, 'type' => 'partial', 'target' => $partial];
                        }

                        $section = $extractor->scalarArgument($node, 'section');
                        if ($section !== null) {
                            $references[] = ['from' => $file, 'type' => 'section', 'target' => $section];
                        }
                    }
                },
            );

            foreach ($variableExtractor->extractPartialRenderCalls($source) as $call) {
                $references[] = [
                    'from' => $file,
                    'type' => 'partial',
                    'target' => $call['partial'],
                    'usesAll' => $call['usesAll'],
                    'arguments' => $call['arguments'],
                ];
            }

            $sectionsByFile[$file] = array_keys($sections);
        }

        return new self(
            $filesByType,
            $sectionsByFile,
            self::deduplicateReferences($references),
            $partialNamesByFile,
            $typoScriptIndex?->referencedPartialNames() ?? [],
        );
    }

    /**
     * @param list<string> $excludeOrphanPatterns
     * @return list<string>
     */
    public function orphanPartials(array $excludeOrphanPatterns = []): array
    {
        $referenced = $this->transitivelyReferencedPartialNames();

        foreach ($this->typoScriptReferencedPartials as $name) {
            $referenced[$name] = true;
        }

        $orphans = [];
        foreach ($this->filesByType['partial'] as $file) {
            if (GlobMatcher::matchesAny($file, $excludeOrphanPatterns)) {
                continue;
            }

            $names = $this->partialNamesByFile[$file] ?? [self::basenameWithoutExtension($file)];
            $isReferenced = false;
            foreach ($names as $name) {
                if (isset($referenced[$name])) {
                    $isReferenced = true;
                    break;
                }
            }

            if (!$isReferenced) {
                $orphans[] = $file;
            }
        }

        return $orphans;
    }

    /**
     * @return list<string>
     */
    public function orphanLayouts(): array
    {
        $referenced = [];
        foreach ($this->references as $reference) {
            if ($reference['type'] === 'layout') {
                $referenced[$reference['target']] = true;
            }
        }

        $orphans = [];
        foreach ($this->filesByType['layout'] as $file) {
            $name = self::basenameWithoutExtension($file);
            if (!isset($referenced[$name])) {
                $orphans[] = $file;
            }
        }

        return $orphans;
    }

    /**
     * @param list<string> $entryPointGlobs
     * @return list<array{file: string, section: string}>
     */
    public function unusedSections(array $entryPointGlobs): array
    {
        $referencedSections = [];
        foreach ($this->references as $reference) {
            if ($reference['type'] === 'section') {
                $referencedSections[$reference['target']] = true;
            }
        }

        $unused = [];
        foreach ($this->sectionsByFile as $file => $sections) {
            if (!GlobMatcher::matchesAny($file, $entryPointGlobs)) {
                continue;
            }

            foreach ($sections as $section) {
                if (!isset($referencedSections[$section])) {
                    $unused[] = ['file' => $file, 'section' => $section];
                }
            }
        }

        return $unused;
    }

    /**
     * @return list<array{from: string, type: string, target: string, usesAll?: bool, arguments?: list<string>}>
     */
    public function references(): array
    {
        return $this->references;
    }

    /**
     * @return array<string, list<string>>
     */
    public function partialNamesByFile(): array
    {
        return $this->partialNamesByFile;
    }

    /**
     * @return array<string, true>
     */
    private function transitivelyReferencedPartialNames(): array
    {
        $referenced = [];
        $queue = [];

        foreach ($this->references as $reference) {
            if ($reference['type'] !== 'partial') {
                continue;
            }
            $target = $reference['target'];
            if (!isset($referenced[$target])) {
                $referenced[$target] = true;
                $queue[] = $target;
            }
        }

        while ($queue !== []) {
            $current = array_shift($queue);
            foreach ($this->filesByType['partial'] as $file) {
                $names = $this->partialNamesByFile[$file] ?? [];
                if (!in_array($current, $names, true)) {
                    continue;
                }

                foreach ($this->references as $reference) {
                    if ($reference['from'] !== $file || $reference['type'] !== 'partial') {
                        continue;
                    }
                    $target = $reference['target'];
                    if (!isset($referenced[$target])) {
                        $referenced[$target] = true;
                        $queue[] = $target;
                    }
                }
            }
        }

        return $referenced;
    }

    /**
     * @param list<string> $partialRootPaths
     * @return list<string>
     */
    public static function partialNamesForFile(string $file, array $partialRootPaths): array
    {
        $names = [];
        $normalizedFile = str_replace('\\', '/', $file);

        if (preg_match('#/(?:Partials|partials)/(.+)\.(?:fluid\.)?html$#i', $normalizedFile, $match) === 1) {
            $names[] = $match[1];
        }

        foreach ($partialRootPaths as $root) {
            $normalizedRoot = rtrim(str_replace('\\', '/', $root), '/');
            if (!str_starts_with($normalizedFile, $normalizedRoot . '/')) {
                continue;
            }

            $relative = substr($normalizedFile, strlen($normalizedRoot) + 1);
            $names[] = preg_replace('/\.(fluid\.)?html$/', '', $relative) ?? $relative;
        }

        $names[] = self::basenameWithoutExtension($file);

        return array_values(array_unique($names));
    }

    /**
     * @param list<array{from: string, type: string, target: string, usesAll?: bool, arguments?: list<string>}> $references
     * @return list<array{from: string, type: string, target: string, usesAll?: bool, arguments?: list<string>}>
     */
    private static function deduplicateReferences(array $references): array
    {
        $unique = [];
        foreach ($references as $reference) {
            $key = $reference['from'] . '|' . $reference['type'] . '|' . $reference['target'];
            $unique[$key] = $reference;
        }

        return array_values($unique);
    }

    private static function detectType(string $file): string
    {
        if (str_contains($file, '/Layouts/') || str_contains($file, '/layouts/')) {
            return 'layout';
        }
        if (str_contains($file, '/Partials/') || str_contains($file, '/partials/')) {
            return 'partial';
        }

        return 'template';
    }

    private static function basenameWithoutExtension(string $file): string
    {
        $base = basename($file);
        return preg_replace('/\.(fluid\.)?html$/', '', $base) ?? $base;
    }
}
