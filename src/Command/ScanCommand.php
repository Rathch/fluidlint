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

use Cru\Fluidlint\Discovery\TemplateDiscovery;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'scan', description: 'Run all Fluid linting, complexity and dead-code checks')]
final class ScanCommand extends AbstractAnalyzeCommand
{
    protected function configure(): void
    {
        $this
            ->addArgument('paths', InputArgument::IS_ARRAY, 'Paths to scan', ['.'])
            ->setHelp('Scans Fluid templates for structural issues, complexity thresholds and dead code.');
        $this->configureCommonOptions();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configuration = $this->loadConfiguration($input);
        $discovery = new TemplateDiscovery($configuration);
        $files = $discovery->discover($this->resolvePaths($input));

        if ($files === []) {
            $output->writeln('<comment>No Fluid templates found.</comment>');
            return 0;
        }

        $result = $this->analyzer->analyzeFiles($files, $configuration, true, true);
        $format = (string)$input->getOption('format');

        return $this->renderAndExit(
            $result['issues'],
            $format,
            count($files),
            $configuration->failOnSeverity(),
            $output,
        );
    }
}
