<?php
declare(strict_types=1);

namespace App\Command;

use Defuse\Crypto\Key;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'identity-link:generate-encryption-key',
    description: 'Generate a shared secret key for symmetric data encryption.'
)]
class GenerateEncryptionKey extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('key-path', null, InputOption::VALUE_REQUIRED)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $key = Key::createNewRandomKey();
        $path = $input->getOption('key-path');

        if (!$path) {
            $output->writeln('<error>Error: The --key-path option is required to run this command.</error>');
            return self::FAILURE;
        }

        if (file_exists($path)) {
            $output->writeln(sprintf('<error>File "%s" already exists.</error>', $path));
            return self::SUCCESS;
        }

        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException(sprintf('Directory "%s" could not be created', $dir));
        }

        file_put_contents($path, $key->saveToAsciiSafeString());
        if (!chmod($path, 0600)) {
            $output->writeln(sprintf('<error>Warning: Could not set secure permissions (chmod 600) on %s</error>', $path));
        }

        $output->writeln(sprintf('Encryption key created at %s', $path));

        return self::SUCCESS;
    }

}