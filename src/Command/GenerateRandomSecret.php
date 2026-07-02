<?php
declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'identity-link:generate-random-secret',
    description: 'Generate a cryptographically secure random secret.'
)]
class GenerateRandomSecret extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption(
                'path',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'The file path(value) where the secret should be saved. Can be passed multiple times.'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $paths = $input->getOption('path');

        if (empty($paths)) {
            $output->writeln($this->generateRandomString());
            return self::SUCCESS;
        }

        foreach ($paths as $path) {
            if (file_exists($path)) {
                $output->writeln(sprintf('<error>File "%s" already exists. Skipped.</error>', $path));
                continue;
            }

            if (file_put_contents($path, $this->generateRandomString()) === false) {
                $output->writeln(sprintf('<error>Failed to write secret to "%s".</error>', $path));
                continue;
            }

            if (!chmod($path, 0600)) {
                $output->writeln(sprintf('<error>Could not set secure permissions (chmod 600) on "%s".</error>', $path));
            } else {
                $output->writeln(sprintf('Secret successfully generated and saved to "%s".', $path));
            }
        }

        return self::SUCCESS;
    }

    private function generateRandomString(): string
    {
        return bin2hex(random_bytes(16));
    }
}