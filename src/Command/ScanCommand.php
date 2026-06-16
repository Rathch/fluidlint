<?php

declare(strict_types=1);

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
