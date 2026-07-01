<?php
declare(strict_types=1);

namespace App\Command;

use App\Service\JwtTokenGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateToken extends Command
{
    public function __construct(
        private readonly JwtTokenGenerator $jwtTokenGenerator,
    )
    {
        parent::__construct('identity-link:generate-token');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Generate JWT token')

            ->addOption('iss', null, InputOption::VALUE_OPTIONAL, 'Issuer of the JWT', 'identity-link')
            ->addOption('aud', null, InputOption::VALUE_OPTIONAL, 'Recipient for which the JWT is intended', 'identity-link')
            ->addOption('sub', null, InputOption::VALUE_OPTIONAL, 'Subject of the JWT (the user)', '')
            ->addOption('exp-time', null, InputOption::VALUE_OPTIONAL, 'Expiration time in seconds', 3600)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $expTime = $input->getOption('exp-time');
        if (!is_numeric($expTime)) {
            $output->writeln('ERROR: invalid exp-time passed.');
            return self::FAILURE;
        }

        $jwt = $this->jwtTokenGenerator
            ->setIssuer($input->getOption('iss'))
            ->setAudience($input->getOption('aud'))
            ->setSubject($input->getOption('sub'))
            ->setGroups(['administrator'])
            ->setExpTime((int) $expTime)
            ->createToken();

        $output->writeln($jwt);

        return self::SUCCESS;
    }
}