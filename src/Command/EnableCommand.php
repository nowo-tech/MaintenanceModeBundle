<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Command;

use DateTimeImmutable;
use Exception;
use Nowo\MaintenanceModeBundle\Service\MaintenanceManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function is_string;

use const DATE_ATOM;

/**
 * Enable maintenance mode (deploy / ops).
 */
#[AsCommand(
    name: 'nowo:maintenance-mode:enable',
    description: 'Enable maintenance mode',
)]
final class EnableCommand extends Command
{
    public function __construct(
        private readonly MaintenanceManager $manager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('message', 'm', InputOption::VALUE_REQUIRED, 'Public maintenance message')
            ->addOption('actor', null, InputOption::VALUE_REQUIRED, 'Actor recorded in history', 'cli')
            ->addOption('until', null, InputOption::VALUE_REQUIRED, 'Optional scheduled disable time (ATOM / strtotime-compatible)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io      = new SymfonyStyle($input, $output);
        $message = $input->getOption('message');
        $actor   = (string) $input->getOption('actor');
        $until   = $input->getOption('until');

        $state = $this->manager->enable(
            is_string($message) && $message !== '' ? $message : null,
            $actor !== '' ? $actor : 'cli',
        );

        if (is_string($until) && $until !== '') {
            try {
                $disableAt = new DateTimeImmutable($until);
            } catch (Exception $e) {
                $io->error('Invalid --until value: ' . $e->getMessage());

                return Command::FAILURE;
            }
            $state = $this->manager->schedule(disableAt: $disableAt, updatedBy: $actor !== '' ? $actor : 'cli');
        }

        $io->success('Maintenance mode ENABLED.');
        $io->listing([
            'Message: ' . ($state->getMessage() ?? '—'),
            'Effectively on: ' . ($state->isEffectivelyEnabled() ? 'yes' : 'no'),
            'Scheduled disable: ' . ($state->getScheduledDisableAt()?->format(DATE_ATOM) ?? '—'),
        ]);

        return Command::SUCCESS;
    }
}
