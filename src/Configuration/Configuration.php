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

namespace Cru\Fluidlint\Configuration;

final class Configuration
{
    /**
     * @param list<string> $paths
     * @param list<string> $exclude
     * @param list<string> $entryPoints
     * @param array<string, bool> $rules
     */
    public function __construct(
        public readonly array $paths = [],
        public readonly array $exclude = ['vendor/**', 'typo3/sysext/**', 'public/typo3/**'],
        public readonly bool $includeSystemExtensions = false,
        public readonly array $rules = [],
        public readonly int $nestingDepthWarn = 8,
        public readonly int $nestingDepthError = 12,
        public readonly int $complexityWarn = 10,
        public readonly int $complexityError = 20,
        public readonly array $entryPoints = ['**/Resources/Private/Templates/**'],
        public readonly string $orphanSeverity = 'info',
        public readonly string $failOn = 'warning',
    ) {
    }

    public function isRuleEnabled(string $ruleId): bool
    {
        return $this->rules[$ruleId] ?? true;
    }

    public function failOnSeverity(): \Cru\Fluidlint\Report\Severity
    {
        return match ($this->failOn) {
            'error' => \Cru\Fluidlint\Report\Severity::Error,
            'info' => \Cru\Fluidlint\Report\Severity::Info,
            default => \Cru\Fluidlint\Report\Severity::Warning,
        };
    }

    public function orphanSeverityEnum(): \Cru\Fluidlint\Report\Severity
    {
        return match ($this->orphanSeverity) {
            'warning' => \Cru\Fluidlint\Report\Severity::Warning,
            'error' => \Cru\Fluidlint\Report\Severity::Error,
            default => \Cru\Fluidlint\Report\Severity::Info,
        };
    }

    public static function fromArray(array $data, ?self $defaults = null): self
    {
        $defaults ??= self::loadDefaults();

        $rules = array_merge($defaults->rules, $data['rules'] ?? []);

        return new self(
            paths: $data['paths'] ?? $defaults->paths,
            exclude: $data['exclude'] ?? $defaults->exclude,
            includeSystemExtensions: $data['includeSystemExtensions'] ?? $defaults->includeSystemExtensions,
            rules: $rules,
            nestingDepthWarn: (int)($data['nestingDepth']['warn'] ?? $defaults->nestingDepthWarn),
            nestingDepthError: (int)($data['nestingDepth']['error'] ?? $defaults->nestingDepthError),
            complexityWarn: (int)($data['complexity']['warn'] ?? $defaults->complexityWarn),
            complexityError: (int)($data['complexity']['error'] ?? $defaults->complexityError),
            entryPoints: $data['deadCode']['entryPoints'] ?? $defaults->entryPoints,
            orphanSeverity: $data['deadCode']['orphanSeverity'] ?? $defaults->orphanSeverity,
            failOn: $data['failOn'] ?? $defaults->failOn,
        );
    }

    public static function loadDefaults(): self
    {
        $path = dirname(__DIR__, 2) . '/config/fluidlint.defaults.yaml';
        if (!is_file($path)) {
            return new self();
        }

        $parsed = \Symfony\Component\Yaml\Yaml::parseFile($path);
        if (!is_array($parsed)) {
            return new self();
        }

        return self::fromArray($parsed, new self());
    }

    public static function loadFromProject(string $workingDirectory): self
    {
        $defaults = self::loadDefaults();
        $configFile = $workingDirectory . '/.fluidlint.yaml';
        if (!is_file($configFile)) {
            return $defaults;
        }

        $parsed = \Symfony\Component\Yaml\Yaml::parseFile($configFile);
        if (!is_array($parsed)) {
            return $defaults;
        }

        return self::fromArray($parsed, $defaults);
    }
}
