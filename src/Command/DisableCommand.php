<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Command;

use Nowo\MaintenanceModeBundle\Service\MaintenanceManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use const DATE_ATOM;

/**
 * Disable maintenance mode (deploy / ops).
 */
#[AsCommand(
    name: 'nowo:maintenance-mode:disable',
    description: 'Disable maintenance mode',
)]
final class DisableCommand extends Command
{
    public function __construct(
        private readonly MaintenanceManager $manager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('actor', null, InputOption::VALUE_REQUIRED, 'Actor recorded in history', 'cli');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io    = new SymfonyStyle($input, $output);
        $actor = (string) $input->getOption('actor');
        $state = $this->manager->disable($actor !== '' ? $actor : 'cli');

        $io->success('Maintenance mode DISABLED.');
        $io->listing([
            'Effectively on: ' . ($state->isEffectivelyEnabled() ? 'yes' : 'no'),
            'Deactivated at: ' . ($state->getDeactivatedAt()?->format(DATE_ATOM) ?? '—'),
        ]);

        return Command::SUCCESS;
    }
}
