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
            ->setSubject($input->getOption('sub'))
            ->setExpTime((int) $expTime)
            ->createToken();

        $output->writeln($jwt);

        return self::SUCCESS;
    }
}
