<?php

declare(strict_types=1);

namespace Cru\Fluidlint\Command;

use Cru\Fluidlint\Analysis\ComplexityAnalyzer;
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

#[AsCommand(name: 'complexity', description: 'Measure cyclomatic complexity of Fluid templates')]
final class ComplexityCommand extends AbstractAnalyzeCommand
{
    private readonly TemplateParserFactory $parserFactory;
    private readonly ComplexityAnalyzer $complexityAnalyzer;

    public function __construct(
        ?TemplateAnalyzer $analyzer = null,
        ?Reporter $reporter = null,
        ?TemplateParserFactory $parserFactory = null,
        ?ComplexityAnalyzer $complexityAnalyzer = null,
    ) {
        parent::__construct($analyzer, $reporter);
        $this->parserFactory = $parserFactory ?? new TemplateParserFactory();
        $this->complexityAnalyzer = $complexityAnalyzer ?? new ComplexityAnalyzer();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('paths', InputArgument::IS_ARRAY, 'Paths to scan', ['.'])
            ->setHelp('Reports cyclomatic complexity per Fluid template.');
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

        $issues = [];
        foreach ($files as $file) {
            $source = file_get_contents($file);
            if ($source === false) {
                continue;
            }

            try {
                $parsingState = $this->parserFactory->parse($source, $file);
            } catch (ParserException $exception) {
                $issues[] = new Issue(
                    ruleId: 'fluid/parse-error',
                    severity: Severity::Error,
                    message: $exception->getMessage(),
                    file: $file,
                );
                continue;
            }

            $measurement = $this->complexityAnalyzer->measure($source, $parsingState);
            array_push($issues, ...$this->complexityAnalyzer->analyze($file, $source, $parsingState, $configuration));

            if ((string)$input->getOption('format') === 'text') {
                $output->writeln(sprintf(
                    'Complexity: %d – %s',
                    $measurement['complexity'],
                    $file,
                ));
                foreach ($measurement['contributions'] as $contribution) {
                    $line = $contribution['line'] !== null ? 'line ' . $contribution['line'] : 'unknown line';
                    $output->writeln(sprintf(
                        '  +%d  %s (%s)',
                        $contribution['points'],
                        $contribution['viewHelper'],
                        $line,
                    ));
                }
            }
        }

        $format = (string)$input->getOption('format');
        if ($format !== 'text') {
            return $this->renderAndExit($issues, $format, count($files), $configuration->failOnSeverity(), $output);
        }

        if ($issues !== []) {
            $output->writeln('');
            $output->write($this->reporter->renderText($issues));
        }

        return $this->reporter->exceedsFailThreshold($issues, $configuration->failOnSeverity()) ? 1 : 0;
    }
}
