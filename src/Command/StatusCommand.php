<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Command;

use Nowo\MaintenanceModeBundle\Service\MaintenanceManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use const DATE_ATOM;
use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Show current maintenance state.
 */
#[AsCommand(
    name: 'nowo:maintenance-mode:status',
    description: 'Show maintenance mode status',
)]
final class StatusCommand extends Command
{
    public function __construct(
        private readonly MaintenanceManager $manager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io    = new SymfonyStyle($input, $output);
        $state = $this->manager->getState();

        $io->title('Maintenance mode status');
        $io->definitionList(
            ['Manual enabled' => $state->isEnabled() ? 'yes' : 'no'],
            ['Effectively on'    => $state->isEffectivelyEnabled() ? 'yes' : 'no'],
            ['Message'           => $state->getMessage() ?? '—'],
            ['Activated at'      => $state->getActivatedAt()?->format(DATE_ATOM) ?? '—'],
            ['Deactivated at'    => $state->getDeactivatedAt()?->format(DATE_ATOM) ?? '—'],
            ['Scheduled enable'  => $state->getScheduledEnableAt()?->format(DATE_ATOM) ?? '—'],
            ['Scheduled disable' => $state->getScheduledDisableAt()?->format(DATE_ATOM) ?? '—'],
            ['Updated by'        => $state->getUpdatedBy() ?? '—'],
        );

        if ($output->isVerbose()) {
            $io->writeln(json_encode(
                $state->toArray(),
                JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ));
        }

        return $state->isEffectivelyEnabled() ? 2 : Command::SUCCESS;
    }
}
