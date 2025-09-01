<?php

namespace Sandstorm\ContentrepositoryTypo3\Registry\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CrSetupCommand extends Command
{
    #protected static $defaultName = 'cr:setup';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Hello World!');

        return Command::SUCCESS;
    }
}