<?php

namespace Sandstorm\ContentrepositoryTypo3\Registry\Command;

use Neos\ContentRepository\Core\Projection\ProjectionStatusType;
use Neos\ContentRepository\Core\Service\ContentRepositoryMaintainerFactory;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\Subscription\DetachedSubscriptionStatus;
use Neos\ContentRepository\Core\Subscription\ProjectionSubscriptionStatus;
use Neos\ContentRepository\Core\Subscription\SubscriptionStatus;
use Neos\EventStore\Model\EventStore\StatusType;
use Sandstorm\ContentrepositoryTypo3\Registry\ContentRepositoryRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CrStatusCommand extends Command
{
    private ContentRepositoryRegistry $contentRepositoryRegistry;

    public function __construct(ContentRepositoryRegistry $contentRepositoryRegistry)
    {
        $this->contentRepositoryRegistry = $contentRepositoryRegistry;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('cr:status')
            ->setDescription('Determine and output the status of the event store and all registered projections for a given Content Repository')
            ->setHelp(
                'In verbose mode it will also display information what should and will be migrated when cr:setup is used.'
            )
            ->addArgument(
                'contentRepository',
                InputArgument::OPTIONAL,
                'Identifier of the Content Repository to determine the status for',
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
        $verbose = $input->getOption('verbose');
        $quiet = $input->getOption('quiet');

        if ($quiet) {
            $output->setVerbosity(OutputInterface::VERBOSITY_QUIET);
        }

        $contentRepositoryId = ContentRepositoryId::fromString($contentRepository);
        $contentRepositoryMaintainer = $this->contentRepositoryRegistry->buildService(
            $contentRepositoryId,
            new ContentRepositoryMaintainerFactory()
        );

        $crStatus = $contentRepositoryMaintainer->status();
        $hasErrors = false;
        $replayRequired = false;
        $setupRequired = false;

        // Event Store Status
        $io->section('Event Store:');
        $setupStatus = match ($crStatus->eventStoreStatus->type) {
            StatusType::OK => '<fg=green>OK</>',
            StatusType::SETUP_REQUIRED => '<fg=yellow>Setup required!</>',
            StatusType::ERROR => '<fg=red>ERROR</>',
        };
        $io->text('  Setup: ' . $setupStatus);

        if ($crStatus->eventStorePosition) {
            $io->text(sprintf('  Position: %d', $crStatus->eventStorePosition->value));
        } else {
            $io->text('  Position: <fg=red>Loading failed!</>');
        }

        $hasErrors |= $crStatus->eventStoreStatus->type === StatusType::ERROR;

        if ($verbose && $crStatus->eventStoreStatus->details !== '') {
            $details = explode("\n", $crStatus->eventStoreStatus->details);
            foreach ($details as $line) {
                $io->text('    ' . $line);
            }
        }

        // Subscriptions Status
        $io->newLine();
        $io->section('Subscriptions:');

        if ($crStatus->subscriptionStatus->isEmpty()) {
            $io->error('There are no registered subscriptions yet, please run "./flow cr:setup"');
            return Command::FAILURE;
        }

        foreach ($crStatus->subscriptionStatus as $status) {
            if ($status instanceof DetachedSubscriptionStatus) {
                $io->text(sprintf('  <options=bold>%s</>', $status->subscriptionId->value));
                $io->text(sprintf('    Subscription: %s <fg=yellow>DETACHED</> at position <options=bold>%d</>',
                    $status->subscriptionId->value,
                    $status->subscriptionPosition->value
                ));
            }

            if ($status instanceof ProjectionSubscriptionStatus) {
                $io->text(sprintf('  <options=bold>%s</>', $status->subscriptionId->value));

                $setupStatusText = match ($status->setupStatus->type) {
                    ProjectionStatusType::OK => '<fg=green>OK</>',
                    ProjectionStatusType::SETUP_REQUIRED => '<fg=yellow>SETUP REQUIRED</>',
                    ProjectionStatusType::ERROR => '<fg=red>ERROR</>',
                };
                $io->text('    Setup: ' . $setupStatusText);

                $hasErrors |= $status->setupStatus->type === ProjectionStatusType::ERROR;
                $setupRequired |= $status->setupStatus->type === ProjectionStatusType::SETUP_REQUIRED;

                if ($verbose && ($status->setupStatus->type !== ProjectionStatusType::OK || $status->setupStatus->details)) {
                    $details = $status->setupStatus->details ?: '<fg=yellow>No details available.</>';
                    $lines = explode("\n", $details);
                    foreach ($lines as $line) {
                        $io->text('      ' . $line);
                    }
                    $io->newLine();
                }

                $subscriptionStatusText = match ($status->subscriptionStatus) {
                    SubscriptionStatus::NEW => '<fg=yellow>NEW</>',
                    SubscriptionStatus::BOOTING => '<fg=yellow>BOOTING</>',
                    SubscriptionStatus::ACTIVE => '<fg=green>ACTIVE</>',
                    SubscriptionStatus::DETACHED => '<fg=yellow>DETACHED</>',
                    SubscriptionStatus::ERROR => '<fg=red>ERROR</>',
                };

                $positionText = '';
                if ($crStatus->eventStorePosition?->value > $status->subscriptionPosition->value) {
                    // projection is behind
                    $positionText = sprintf(' at position <fg=red>%d</>', $status->subscriptionPosition->value);
                } else {
                    $positionText = sprintf(' at position <options=bold>%d</>', $status->subscriptionPosition->value);
                }

                $io->text('    Projection: ' . $subscriptionStatusText . $positionText);

                $hasErrors |= $status->subscriptionStatus === SubscriptionStatus::ERROR;
                $replayRequired |= $status->subscriptionStatus === SubscriptionStatus::ERROR;
                $replayRequired |= $status->subscriptionStatus === SubscriptionStatus::BOOTING;
                $replayRequired |= $status->subscriptionStatus === SubscriptionStatus::DETACHED;

                if ($verbose && $status->subscriptionError !== null) {
                    $errorMessage = $status->subscriptionError->errorMessage ?: '<fg=yellow>No details available.</>';
                    $lines = explode("\n", $errorMessage);
                    foreach ($lines as $line) {
                        $io->text('<fg=red>      ' . $line . '</>');
                    }
                }
            }
        }

        if ($verbose) {
            $io->newLine();
            if ($setupRequired) {
                $io->note('Setup required, please run "./flow cr:setup"');
            }
            if ($replayRequired) {
                $io->note('Replay needed for BOOTING, ERROR or DETACHED subscriptions, please run "./flow subscription:replay [subscription-id]"');
            }
        }

        return $hasErrors ? Command::FAILURE : Command::SUCCESS;
    }
}