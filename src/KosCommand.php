<?php

namespace Kos;

use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class KosCommand extends Command
{
    protected static $defaultName = 'kos';

    /** @var Kos */
    private $kos;

    protected function configure()
    {
        $this
            ->setDescription('Draw colored star with n tips')
            ->setDefinition(
                new InputDefinition([
                    new InputArgument('username', InputArgument::REQUIRED, 'CVUT username'),
                ])
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $info = $input->getArguments();

        try {
            $this->kos = new Kos($input->getArgument('username'), $io);
        } catch (Exception $e) {
            $io->error($e->getMessage());
            return 1;
        }

        return 0;
    }
}
