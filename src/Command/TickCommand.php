<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Command;

use Nowo\MaintenanceModeBundle\Model\MaintenanceState;
use Nowo\MaintenanceModeBundle\Service\MaintenanceManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Materialises scheduled windows so their start / end dispatch MaintenanceEnabledEvent / MaintenanceDisabledEvent
 * (run every minute from cron or Symfony Scheduler). Without it, schedules still apply at read time, silently.
 */
#[AsCommand(
    name: 'nowo:maintenance-mode:tick',
    description: 'Apply a scheduled maintenance window that started or ended (dispatches the enabled/disabled events)',
)]
final class TickCommand extends Command
{
    public function __construct(
        private readonly MaintenanceManagerInterface $manager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('actor', null, InputOption::VALUE_REQUIRED, 'Actor recorded in history and events', MaintenanceManagerInterface::ACTOR_SCHEDULE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io    = new SymfonyStyle($input, $output);
        $actor = (string) $input->getOption('actor');

        $state = $this->manager->applySchedule(actor: $actor !== '' ? $actor : MaintenanceManagerInterface::ACTOR_SCHEDULE);

        if (!$state instanceof MaintenanceState) {
            if ($output->isVerbose()) {
                $io->writeln('No scheduled transition due.');
            }

            return Command::SUCCESS;
        }

        $io->success($state->isEnabled() ? 'Scheduled maintenance window started: maintenance ENABLED.' : 'Scheduled maintenance window ended: maintenance DISABLED.');

        return Command::SUCCESS;
    }
}
