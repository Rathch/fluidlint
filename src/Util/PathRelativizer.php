<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: 2025 Christian Rath-Ulrich
//
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Cru\Fluidlint\Util;

final class PathRelativizer
{
    /**
     * @param list<string> $inputPaths
     */
    public static function resolveBase(array $inputPaths): string
    {
        $paths = $inputPaths !== [] ? $inputPaths : [getcwd() ?: '.'];
        $normalized = array_map(self::normalize(...), $paths);

        if (count($normalized) === 1) {
            $path = $normalized[0];
            if (is_file($path)) {
                return dirname($path);
            }

            return is_dir($path) ? $path : dirname($path);
        }

        $roots = array_map(static function (string $path): string {
            return is_file($path) ? dirname($path) : $path;
        }, $normalized);

        return self::commonDirectoryPrefix($roots);
    }

    public static function relativize(string $path, string $base): string
    {
        $path = self::normalize($path);
        $base = rtrim(self::normalize($base), '/');

        if ($path === $base) {
            return '.';
        }

        if (str_starts_with($path, $base . '/')) {
            return substr($path, strlen($base) + 1);
        }

        return self::makeRelative($path, $base);
    }

    /**
     * @param array<string, mixed> $complexityByFile
     * @return array<string, mixed>
     */
    public static function relativizeComplexityKeys(array $complexityByFile, string $base): array
    {
        $relativized = [];
        foreach ($complexityByFile as $file => $measurement) {
            $relativized[self::relativize($file, $base)] = $measurement;
        }

        return $relativized;
    }

    private static function normalize(string $path): string
    {
        $resolved = realpath($path);

        return str_replace('\\', '/', $resolved !== false ? $resolved : $path);
    }

    /**
     * @param list<string> $paths
     */
    private static function commonDirectoryPrefix(array $paths): string
    {
        if ($paths === []) {
            return self::normalize(getcwd() ?: '.');
        }

        $segments = array_map(
            static fn (string $path): array => explode('/', rtrim(self::normalize($path), '/')),
            $paths,
        );

        $common = [];
        foreach ($segments[0] as $index => $segment) {
            foreach ($segments as $pathSegments) {
                if (($pathSegments[$index] ?? null) !== $segment) {
                    return implode('/', $common) !== '' ? implode('/', $common) : '/';
                }
            }
            $common[] = $segment;
        }

        return implode('/', $common);
    }

    private static function makeRelative(string $path, string $base): string
    {
        $pathSegments = explode('/', $path);
        $baseSegments = explode('/', $base);

        while ($pathSegments !== [] && $baseSegments !== [] && $pathSegments[0] === $baseSegments[0]) {
            array_shift($pathSegments);
            array_shift($baseSegments);
        }

        $relative = array_merge(array_fill(0, count($baseSegments), '..'), $pathSegments);

        return $relative === [] ? '.' : implode('/', $relative);
    }
}
