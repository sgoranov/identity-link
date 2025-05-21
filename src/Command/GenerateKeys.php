<?php
declare(strict_types=1);

namespace App\Command;

use Defuse\Crypto\Key;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'identity-link:generate-keys')]
class GenerateKeys extends Command
{
    private string $defaultOutputDir;

    public function __construct(string $projectDir)
    {
        $this->defaultOutputDir = $projectDir . '/var';
        parent::__construct('identity-link:generate-keys');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Generate public and private keys used to sign and verify JWTs transmitted')

            ->addOption('output-dir', 'o', InputOption::VALUE_REQUIRED, $this->defaultOutputDir)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $outputDir = $this->defaultOutputDir;
        if (!is_null($input->getOption('output-dir'))) {
            $outputDir = $input->getOption('output-dir');
        }

        if (!is_dir($outputDir)) {
            $output->writeln(sprintf('Specified output directory %s does not exist.', $outputDir));
            return self::FAILURE;
        }

        if (!is_writable($outputDir)) {
            $output->writeln(sprintf('Specified output directory %s is not writable.', $outputDir));
            return self::FAILURE;
        }

        $pkGenerate = openssl_pkey_new(array(
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA
        ));

        // get the private key
        openssl_pkey_export($pkGenerate, $pkGeneratePrivate);
        $pkGenerateDetails = openssl_pkey_get_details($pkGenerate);
        $pkGeneratePublic = $pkGenerateDetails['key'];

        $path = $outputDir . '/private.key';
        file_put_contents($path, $pkGeneratePrivate);
        chmod($path, 0600);
        $output->writeln(sprintf('Private key %s', $path));

        $path = $outputDir . '/public.key';
        file_put_contents($path, $pkGeneratePublic);
        $output->writeln(sprintf('Public key %s', $path));

        $key = Key::createNewRandomKey();
        $path = $outputDir . '/encryption.key';
        file_put_contents($path, $key->saveToAsciiSafeString());
        $output->writeln(sprintf('Encryption key %s', $path));

        $output->writeln('Done!');
        return self::SUCCESS;
    }
}