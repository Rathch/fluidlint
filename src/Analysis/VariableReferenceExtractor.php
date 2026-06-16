<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: 2025 Christian Rath-Ulrich
//
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Cru\Fluidlint\Analysis;

use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\NodeInterface;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\TextNode;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\ViewHelperNode;

final class VariableReferenceExtractor
{
    /**
     * @param array<string, true> $read
     */
    public function collectReadsFromSource(string $source, array &$read): void
    {
        if (preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)(?:\.[a-zA-Z0-9_.]*)?(?:\s*->|\})/', $source, $matches)) {
            foreach ($matches[1] as $name) {
                $read[$name] = true;
            }
        }

        $this->collectReadsFromInlineObjects($source, $read);
        $this->collectReadsFromViewHelperTagAttributes($source, $read);

        foreach ($this->extractRenderArgumentBlocks($source) as $block) {
            if (str_contains($block, '_all')) {
                continue;
            }

            if (preg_match_all('/\b[a-zA-Z_][a-zA-Z0-9_]*\s*:\s*([a-zA-Z_][a-zA-Z0-9_.]*)/', $this->stripQuotedStrings($block), $refs)) {
                foreach ($refs[1] as $variable) {
                    $read[explode('.', $variable)[0]] = true;
                }
            }
        }
    }

    /**
     * @param array<string, true> $read
     */
    public function collectReadsFromNode(NodeInterface $node, array &$read): void
    {
        if ($node instanceof TextNode) {
            $this->collectReadsFromText($node->getText(), $read);
        }

        if ($node instanceof ViewHelperNode) {
            foreach ($node->getArguments() as $argument) {
                if (is_string($argument)) {
                    $this->collectReadsFromText($argument, $read);
                }
            }
        }

        if (method_exists($node, 'getChildNodes')) {
            foreach ($node->getChildNodes() as $child) {
                if ($child instanceof NodeInterface) {
                    $this->collectReadsFromNode($child, $read);
                }
            }
        }
    }

    /**
     * @return list<array{partial: string, line: ?int, usesAll: bool, arguments: list<string>}>
     */
    public function extractPartialRenderCalls(string $source): array
    {
        $calls = [];

        if (!preg_match_all('/<f:render\b([^>]*)(?:\/>|>)/is', $source, $matches, PREG_OFFSET_CAPTURE)) {
            return $calls;
        }

        foreach ($matches[1] as $index => [$attributes]) {
            $partial = $this->extractAttribute($attributes, 'partial');
            if ($partial === null) {
                continue;
            }

            $argumentsBlock = $this->extractAttribute($attributes, 'arguments') ?? '';
            $usesAll = str_contains($argumentsBlock, '_all');
            $argumentNames = [];

            if (!$usesAll) {
                $argumentNames = $this->extractArgumentNames($argumentsBlock);
            }

            $offset = $matches[0][$index][1];
            $calls[] = [
                'partial' => $partial,
                'line' => substr_count(substr($source, 0, $offset), "\n") + 1,
                'usesAll' => $usesAll,
                'arguments' => $argumentNames,
            ];
        }

        return $calls;
    }

    /**
     * @param array<string, true> $defined
     */
    public function collectDefinitionsFromSource(string $source, array &$defined): void
    {
        if (preg_match_all('/<f:variable\b[^>]*\bname\s*=\s*["\']([^"\']+)["\']/i', $source, $matches)) {
            foreach ($matches[1] as $name) {
                $defined[$name] = true;
            }
        }

        if (preg_match_all('/\{f:variable\([^)]*\bname\s*:\s*["\']([^"\']+)["\']/i', $source, $matches)) {
            foreach ($matches[1] as $name) {
                $defined[$name] = true;
            }
        }

        if (preg_match_all('/<f:for\b[^>]*\bas\s*=\s*["\']([^"\']+)["\']/i', $source, $matches)) {
            foreach ($matches[1] as $name) {
                $defined[$name] = true;
            }
        }
    }

    /**
     * @return list<string>
     */
    public function extractLoopVariablesPassedViaAll(string $source): array
    {
        $variables = [];

        if (!preg_match_all('/<f:for\b[^>]*\bas\s*=\s*["\']([^"\']+)["\'][^>]*>(.*?)<\/f:for>/is', $source, $matches)) {
            return $variables;
        }

        foreach ($matches[2] as $index => $body) {
            if (str_contains($body, '_all')) {
                $variables[] = $matches[1][$index];
            }
        }

        return $variables;
    }

    /**
     * @return list<string>
     */
    public function extractArgumentNames(string $argumentsBlock): array
    {
        $stripped = $this->stripQuotedStrings($argumentsBlock);
        if (!preg_match_all('/\b([a-zA-Z_][a-zA-Z0-9_]*)\s*:/', $stripped, $matches)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            $matches[1],
            static fn (string $name): bool => !in_array(strtolower($name), ['true', 'false', 'null'], true),
        )));
    }

    /**
     * @param array<string, true> $read
     */
    private function collectReadsFromInlineObjects(string $source, array &$read): void
    {
        if (!preg_match_all('/\{([^{}]+)\}/', $source, $matches)) {
            return;
        }

        foreach ($matches[1] as $content) {
            if (!str_contains($content, ':')) {
                continue;
            }

            if (preg_match_all('/\b([a-zA-Z_][a-zA-Z0-9_]*)\s*:\s*([a-zA-Z_][a-zA-Z0-9_.]*)/', $content, $pairs)) {
                foreach ($pairs[2] as $value) {
                    $read[explode('.', $value)[0]] = true;
                }
            }
        }
    }

    private const DEFINITION_ATTRIBUTES = ['name', 'as', 'partial', 'section', 'layout', 'each', 'key', 'iteration'];

    /**
     * @param array<string, true> $read
     */
    private function collectReadsFromViewHelperTagAttributes(string $source, array &$read): void
    {
        if (preg_match_all('/<[a-zA-Z0-9_.:-]+[^>]*>/s', $source, $tags)) {
            foreach ($tags[0] as $tag) {
                if (preg_match_all('/\b([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*"\{?([a-zA-Z_][a-zA-Z0-9_.]*)\}?"/', $tag, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        if (in_array($match[1], self::DEFINITION_ATTRIBUTES, true)) {
                            continue;
                        }
                        $read[explode('.', $match[2])[0]] = true;
                    }
                }
            }
        }
    }

    private function stripQuotedStrings(string $input): string
    {
        $result = preg_replace("/'(?:\\\\'|[^'])*'/s", "''", $input) ?? $input;

        return preg_replace('/"(?:\\\\"|[^"])*"/s', '""', $result) ?? $result;
    }

    /**
     * @return list<string>
     */
    private function extractRenderArgumentBlocks(string $source): array
    {
        $blocks = [];

        if (preg_match_all('/\barguments\s*=\s*["\']?\{([^}]+)\}/s', $source, $matches)) {
            $blocks = array_merge($blocks, $matches[1]);
        }

        if (preg_match_all('/\barguments\s*=\s*"([^"]+)"/s', $source, $matches)) {
            foreach ($matches[1] as $block) {
                if (str_contains($block, ':')) {
                    $blocks[] = $block;
                }
            }
        }

        return $blocks;
    }

    /**
     * @param array<string, true> $read
     */
    private function collectReadsFromText(string $text, array &$read): void
    {
        if (preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)(?:\.[a-zA-Z0-9_.]*)?(?:\s*->|\})/', $text, $matches)) {
            foreach ($matches[1] as $name) {
                $read[$name] = true;
            }
        }
    }

    private function extractAttribute(string $attributes, string $name): ?string
    {
        if (preg_match('/\b' . preg_quote($name, '/') . '\s*=\s*"([^"]*)"/i', $attributes, $match) === 1) {
            return $match[1];
        }

        if (preg_match('/\b' . preg_quote($name, '/') . '\s*=\s*\'([^\']*)\'/i', $attributes, $match) === 1) {
            return $match[1];
        }

        return null;
    }
}
