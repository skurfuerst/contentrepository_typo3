<?php

namespace Sandstorm\ContentrepositoryTypo3\Registry\Command;

use Neos\ContentRepository\Core\Service\ContentRepositoryMaintainerFactory;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Sandstorm\ContentrepositoryTypo3\Registry\ContentRepositoryRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CrSetupCommand extends Command
{
    public function __construct(private readonly ContentRepositoryRegistry $contentRepositoryRegistry)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('cr:setup')
            ->setDescription('Sets up and checks required dependencies for a Content Repository instance')
            ->setHelp(
                'Like event store and projection database tables.' . PHP_EOL . PHP_EOL .
                'Note: This command is non-destructive, i.e. it can be executed without side effects even if all dependencies are up-to-date' . PHP_EOL .
                'Therefore it makes sense to include this command into the Continuous Integration' . PHP_EOL . PHP_EOL .
                'To check if the content repository needs to be setup look into cr:status.' . PHP_EOL .
                'That command will also display information what is about to be migrated.'
            )
            ->addArgument(
                'contentRepository',
                InputArgument::OPTIONAL,
                'Identifier of the Content Repository to set up',
                'default'
            )
            ->addOption(
                'quiet',
                'q',
                InputOption::VALUE_NONE,
                'If set, no output is generated. This is useful if only the exit code (0 = all OK, 1 = errors or warnings) is of interest'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $contentRepository = $input->getArgument('contentRepository');
        $quiet = $input->getOption('quiet');

        if ($quiet) {
            $output->setVerbosity(OutputInterface::VERBOSITY_QUIET);
        }

        $contentRepositoryId = ContentRepositoryId::fromString($contentRepository);
        $contentRepositoryMaintainer = $this->contentRepositoryRegistry->buildService(
            $contentRepositoryId,
            new ContentRepositoryMaintainerFactory()
        );

        $result = $contentRepositoryMaintainer->setUp();

        if ($result !== null) {
            $io->error($result->getMessage());
            return Command::FAILURE;
        }

        $io->success(sprintf('Content Repository "%s" was set up', $contentRepositoryId->value));
        return Command::SUCCESS;
    }
}