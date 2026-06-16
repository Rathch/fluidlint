<?php

declare(strict_types=1);

namespace Cru\Fluidlint\Typo3\Command;

use Cru\Fluidlint\Configuration\Configuration;
use Cru\Fluidlint\Discovery\TemplateDiscovery;
use Cru\Fluidlint\Report\Reporter;
use Cru\Fluidlint\Service\TemplateAnalyzer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Core\Environment;

final class AnalyzeCommand extends Command
{
    public function __construct(
        private readonly TemplateAnalyzer $analyzer = new TemplateAnalyzer(),
        private readonly Reporter $reporter = new Reporter(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Analyze Fluid templates for linting, complexity and dead code')
            ->addArgument('paths', InputArgument::IS_ARRAY, 'Paths to scan', [])
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format: text, json, sarif', 'text')
            ->addOption('fail-on', null, InputOption::VALUE_REQUIRED, 'Minimum severity to fail: info, warning, error');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectPath = Environment::getProjectPath();
        $configuration = Configuration::loadFromProject($projectPath);
        $paths = $input->getArgument('paths');
        $scanPaths = is_array($paths) && $paths !== [] ? $paths : [$projectPath];

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

        $discovery = new TemplateDiscovery($configuration);
        $files = $discovery->discover($scanPaths);

        if ($files === []) {
            $output->writeln('<comment>No Fluid templates found.</comment>');
            return Command::SUCCESS;
        }

        $result = $this->analyzer->analyzeFiles($files, $configuration, true, true);
        $format = (string)$input->getOption('format');

        $rendered = match ($format) {
            'json' => $this->reporter->renderJson($result['issues'], count($files)),
            'sarif' => $this->reporter->renderSarif($result['issues'], count($files)),
            default => $this->reporter->renderText($result['issues']),
        };

        $output->write($rendered);

        return $this->reporter->exceedsFailThreshold($result['issues'], $configuration->failOnSeverity())
            ? Command::FAILURE
            : Command::SUCCESS;
    }
}
