<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function implode;
use function in_array;
use function is_string;
use function password_hash;
use function sprintf;

use const PASSWORD_ARGON2ID;
use const PASSWORD_BCRYPT;
use const PASSWORD_DEFAULT;

/**
 * Hashes a plaintext password for `nowo_maintenance_mode.security.password_hash`.
 */
#[AsCommand(
    name: 'nowo:maintenance-mode:hash-password',
    description: 'Hash a plaintext password for the maintenance panel (password_hash)',
)]
final class HashPasswordCommand extends Command
{
    private const ALGORITHMS = ['bcrypt', 'argon2id', 'default'];

    protected function configure(): void
    {
        $this
            ->addArgument(
                'password',
                InputArgument::OPTIONAL,
                'Plaintext password (omit to be prompted; prefer prompting to avoid shell history)',
            )
            ->addOption(
                'algo',
                'a',
                InputOption::VALUE_REQUIRED,
                'Hash algorithm: bcrypt, argon2id, or default (PASSWORD_DEFAULT)',
                'bcrypt',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $passwordArg = $input->getArgument('password');
        $password    = $passwordArg === null
            ? $io->askHidden('Panel password')
            : $passwordArg;

        if (!is_string($password) || $password === '') {
            $io->error('Password must not be empty.');

            return Command::FAILURE;
        }

        $algoName = (string) $input->getOption('algo');
        if (!in_array($algoName, self::ALGORITHMS, true)) {
            $io->error(sprintf(
                'Invalid --algo "%s". Allowed: %s.',
                $algoName,
                implode(', ', self::ALGORITHMS),
            ));

            return Command::FAILURE;
        }

        $hash = password_hash($password, $this->resolveAlgorithm($algoName));

        $io->writeln($hash);
        $io->note([
            'Store this value in an env var (e.g. MAINTENANCE_PASSWORD_HASH), never commit plaintext.',
            "Config: password_hash: '%env(MAINTENANCE_PASSWORD_HASH)%'",
        ]);

        return Command::SUCCESS;
    }

    private function resolveAlgorithm(string $algoName): string
    {
        return match ($algoName) {
            'argon2id' => PASSWORD_ARGON2ID,
            'default'  => PASSWORD_DEFAULT,
            default    => PASSWORD_BCRYPT,
        };
    }
}
