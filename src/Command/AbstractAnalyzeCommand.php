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

namespace Cru\Fluidlint\Command;

use Cru\Fluidlint\Configuration\Configuration;
use Cru\Fluidlint\Report\Reporter;
use Cru\Fluidlint\Report\Severity;
use Cru\Fluidlint\Service\TemplateAnalyzer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractAnalyzeCommand extends Command
{
    protected readonly TemplateAnalyzer $analyzer;
    protected readonly Reporter $reporter;

    public function __construct(
        ?TemplateAnalyzer $analyzer = null,
        ?Reporter $reporter = null,
    ) {
        $this->analyzer = $analyzer ?? new TemplateAnalyzer();
        $this->reporter = $reporter ?? new Reporter();
        parent::__construct();
    }

    protected function configureCommonOptions(): void
    {
        $this
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format: text, json, sarif', 'text')
            ->addOption('fail-on', null, InputOption::VALUE_REQUIRED, 'Minimum severity to fail: info, warning, error', null)
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to .fluidlint.yaml')
            ->addOption('exclude', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Exclude glob pattern')
            ->addOption('include-system-extensions', null, InputOption::VALUE_NONE, 'Include TYPO3 system extension templates');
    }

    /**
     * @return list<string>
     */
    protected function resolvePaths(InputInterface $input): array
    {
        return array_values(array_filter(array_map('strval', $input->getArgument('paths') ?? [])));
    }

    protected function loadConfiguration(InputInterface $input): Configuration
    {
        $configPath = $input->getOption('config');
        if (is_string($configPath) && $configPath !== '') {
            $parsed = \Symfony\Component\Yaml\Yaml::parseFile($configPath);
            $configuration = Configuration::fromArray(is_array($parsed) ? $parsed : []);
        } else {
            $configuration = Configuration::loadFromProject(getcwd() ?: '.');
        }

        $excludes = $input->getOption('exclude');
        if (is_array($excludes) && $excludes !== []) {
            $configuration = Configuration::fromArray([
                'exclude' => [...$configuration->exclude, ...$excludes],
                'paths' => $configuration->paths,
                'rules' => $configuration->rules,
                'nestingDepth' => ['warn' => $configuration->nestingDepthWarn, 'error' => $configuration->nestingDepthError],
                'complexity' => ['warn' => $configuration->complexityWarn, 'error' => $configuration->complexityError],
                'deadCode' => ['entryPoints' => $configuration->entryPoints, 'orphanSeverity' => $configuration->orphanSeverity],
                'failOn' => $configuration->failOn,
                'includeSystemExtensions' => $input->getOption('include-system-extensions') ? true : $configuration->includeSystemExtensions,
            ]);
        } elseif ($input->getOption('include-system-extensions')) {
            $configuration = Configuration::fromArray([
                'includeSystemExtensions' => true,
                'paths' => $configuration->paths,
                'exclude' => $configuration->exclude,
                'rules' => $configuration->rules,
                'nestingDepth' => ['warn' => $configuration->nestingDepthWarn, 'error' => $configuration->nestingDepthError],
                'complexity' => ['warn' => $configuration->complexityWarn, 'error' => $configuration->complexityError],
                'deadCode' => ['entryPoints' => $configuration->entryPoints, 'orphanSeverity' => $configuration->orphanSeverity],
                'failOn' => $configuration->failOn,
            ]);
        }

        $failOn = $input->getOption('fail-on');
        if (is_string($failOn) && $failOn !== '') {
            $configuration = Configuration::fromArray([
                'failOn' => $failOn,
                'paths' => $configuration->paths,
                'exclude' => $configuration->exclude,
                'rules' => $configuration->rules,
                'nestingDepth' => ['warn' => $configuration->nestingDepthWarn, 'error' => $configuration->nestingDepthError],
                'complexity' => ['warn' => $configuration->complexityWarn, 'error' => $configuration->complexityError],
                'deadCode' => ['entryPoints' => $configuration->entryPoints, 'orphanSeverity' => $configuration->orphanSeverity],
                'includeSystemExtensions' => $configuration->includeSystemExtensions,
            ]);
        }

        return $configuration;
    }

    /**
     * @param list<\Cru\Fluidlint\Report\Issue> $issues
     */
    protected function renderAndExit(array $issues, string $format, int $filesScanned, Severity $failOn, OutputInterface $output): int
    {
        $rendered = match ($format) {
            'json' => $this->reporter->renderJson($issues, $filesScanned),
            'sarif' => $this->reporter->renderSarif($issues, $filesScanned),
            default => $this->reporter->renderText($issues),
        };

        $output->write($rendered);

        return $this->reporter->exceedsFailThreshold($issues, $failOn) ? 1 : 0;
    }
}
