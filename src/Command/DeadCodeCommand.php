<?php

declare(strict_types=1);

namespace Cru\Fluidlint\Command;

use Cru\Fluidlint\Analysis\DeadCodeAnalyzer;
use Cru\Fluidlint\Analysis\TemplateGraph;
use Cru\Fluidlint\Discovery\TemplateDiscovery;
use Cru\Fluidlint\Parser\TemplateParserFactory;
use Cru\Fluidlint\Report\Issue;
use Cru\Fluidlint\Report\Reporter;
use Cru\Fluidlint\Report\Severity;
use Cru\Fluidlint\Service\TemplateAnalyzer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3Fluid\Fluid\Core\Parser\Exception as ParserException;
use TYPO3Fluid\Fluid\Core\Parser\ParsingState;

#[AsCommand(name: 'dead-code', description: 'Detect unreachable branches, unused variables and orphan templates')]
final class DeadCodeCommand extends AbstractAnalyzeCommand
{
    private readonly TemplateParserFactory $parserFactory;
    private readonly DeadCodeAnalyzer $deadCodeAnalyzer;

    public function __construct(
        ?TemplateAnalyzer $analyzer = null,
        ?Reporter $reporter = null,
        ?TemplateParserFactory $parserFactory = null,
        ?DeadCodeAnalyzer $deadCodeAnalyzer = null,
    ) {
        parent::__construct($analyzer, $reporter);
        $this->parserFactory = $parserFactory ?? new TemplateParserFactory();
        $this->deadCodeAnalyzer = $deadCodeAnalyzer ?? new DeadCodeAnalyzer();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('paths', InputArgument::IS_ARRAY, 'Paths to scan', ['.'])
            ->setHelp('Detects unreachable Fluid branches, unused variables and orphan partials/layouts.');
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

        /** @var array<string, ParsingState> $parsingStates */
        $parsingStates = [];
        $issues = [];

        foreach ($files as $file) {
            $source = file_get_contents($file);
            if ($source === false) {
                continue;
            }

            try {
                $parsingStates[$file] = $this->parserFactory->parse($source, $file);
            } catch (ParserException $exception) {
                $issues[] = new Issue(
                    ruleId: 'fluid/parse-error',
                    severity: Severity::Error,
                    message: $exception->getMessage(),
                    file: $file,
                );
            }
        }

        if ($parsingStates !== []) {
            $graph = TemplateGraph::build($parsingStates);
            array_push($issues, ...$this->deadCodeAnalyzer->analyzeProject($parsingStates, $graph, $configuration));
        }

        $format = (string)$input->getOption('format');

        return $this->renderAndExit($issues, $format, count($files), $configuration->failOnSeverity(), $output);
    }
}
