<?php

namespace Sandstorm\ContentrepositoryTypo3\Integration\Feature\RootNode\Command;

use Neos\ContentRepository\Core\NodeType\NodeTypeName;
use Neos\ContentRepository\Core\Service\ContentRepositoryMaintainerFactory;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeName;
use Sandstorm\ContentrepositoryTypo3\Integration\Feature\NodeIdHandling\NodeIdGenerator;
use Sandstorm\ContentrepositoryTypo3\Integration\Feature\RootNode\RootNodeCreator;
use Sandstorm\ContentrepositoryTypo3\Registry\ContentRepositoryRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SetupRootNodeCommand extends Command
{
    public function __construct(private readonly ContentRepositoryRegistry $contentRepositoryRegistry, private readonly NodeIdGenerator $nodeIdGenerator)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('cr:setupRootNode')
            ->addArgument(
                'contentRepository',
                InputArgument::OPTIONAL,
                'Identifier of the Content Repository to set up',
                'default'
            )
            ->addArgument(
                'siteNodeName',
                InputArgument::OPTIONAL,
                'Site Node Name',
                'site'
            )
            ->addArgument(
                'siteNodeType',
                InputArgument::OPTIONAL,
                'Site Node type',
                'TYPO3:SiteRootPage'
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
        $siteNodeName = NodeName::fromString($input->getArgument('siteNodeName'));
        $siteNodeType = NodeTypeName::fromString($input->getArgument('siteNodeType'));
        $quiet = $input->getOption('quiet');

        if ($quiet) {
            $output->setVerbosity(OutputInterface::VERBOSITY_QUIET);
        }

        $contentRepositoryId = ContentRepositoryId::fromString($contentRepository);
        $contentRepository = $this->contentRepositoryRegistry->get($contentRepositoryId);

        $rootNodeCreator = new RootNodeCreator($contentRepository, $this->nodeIdGenerator);
        $rootNodeCreator->createSiteNodeIfNotExists($siteNodeName, $siteNodeType);

        $io->success(sprintf('Site node "%s" was set up', $siteNodeName->value));
        return Command::SUCCESS;
    }
}