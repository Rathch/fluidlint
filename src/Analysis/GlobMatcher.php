<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: 2025 Christian Rath-Ulrich
//
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Cru\Fluidlint\Analysis;

final class GlobMatcher
{
    /**
     * @param list<string> $globs
     */
    public static function matchesAny(string $value, array $globs): bool
    {
        foreach ($globs as $glob) {
            if (self::matches($value, $glob)) {
                return true;
            }
        }

        return false;
    }

    public static function matches(string $value, string $glob): bool
    {
        $pattern = '/^' . self::globToRegex($glob) . '$/i';

        return preg_match($pattern, $value) === 1 || preg_match($pattern, basename($value)) === 1;
    }

    private static function globToRegex(string $glob): string
    {
        $glob = str_replace('\\', '/', $glob);
        $placeholder = "\0";
        $escaped = str_replace('**', $placeholder, $glob);
        $escaped = str_replace('*', '[^/]*', $escaped);
        $escaped = str_replace($placeholder, '.*', $escaped);

        return str_replace('/', '\\/', $escaped);
    }
}
