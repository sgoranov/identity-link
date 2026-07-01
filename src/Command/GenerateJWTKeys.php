<?php
declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'identity-link:generate-jwt-keys',
    description: 'Generate RSA public and private keys for JWT signing.'
)]
class GenerateJWTKeys extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('public-key-path', null, InputOption::VALUE_REQUIRED, 'Public key path, e.g. /var/keys/public.key')
            ->addOption('private-key-path', null, InputOption::VALUE_REQUIRED, 'Private key path, e.g. /var/keys/private.key')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        list($publicKey, $privateKey) = $this->generateKeys();

        $publicPath = $input->getOption('public-key-path');
        $privatePath = $input->getOption('private-key-path');

        if (!$publicPath || !$privatePath) {
            $output->writeln('<error>Error: Both --public-key-path and --private-key-path options are required to run this command.</error>');

            return self::FAILURE;
        }

        try {
            $this->writeKey($publicPath, $publicKey);
            $output->writeln(sprintf('Public key created at %s', $publicPath));
        } catch (\RuntimeException $e) {
            $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
        }

        try {
            $this->writeKey($privatePath, $privateKey);
            if (!chmod($privatePath, 0600)) {
                $output->writeln(sprintf('<error>Warning: Could not set secure permissions (chmod 600) on %s</error>', $privatePath));
            }

            $output->writeln(sprintf('Private key created at %s', $privatePath));
        } catch (\RuntimeException $e) {
            $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
        }

        return self::SUCCESS;
    }

    /**
     * Generates a new RSA key pair.
     *
     * @return array{0: string, 1: string} Returns an array where 0 is the public key and 1 is the private key.
     */
    private function generateKeys(): array
    {
        $pkGenerate = openssl_pkey_new(array(
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA
        ));

        openssl_pkey_export($pkGenerate, $pkGeneratePrivate);
        $pkGenerateDetails = openssl_pkey_get_details($pkGenerate);
        $pkGeneratePublic = $pkGenerateDetails['key'];

        return [$pkGeneratePublic, $pkGeneratePrivate];
    }

    private function writeKey(string $path, string $key): void
    {
        if (file_exists($path)) {
            throw new \RuntimeException(sprintf('File "%s" already exists', $path));
        }

        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException(sprintf('Directory "%s" could not be created', $dir));
        }

        file_put_contents($path, $key);
    }
}