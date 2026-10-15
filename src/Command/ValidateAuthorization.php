<?php
declare(strict_types=1);

namespace App\Command;

use App\Security\Authorization\Loader\AuthorizationLoaderInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Exception\ParseException;

#[AsCommand(
    name: 'identity-link:validate-authorization',
    description: 'Validate the authorization configuration, so a deployment fails on an error instead of the first request.'
)]
class ValidateAuthorization extends Command
{
    public function __construct(private readonly AuthorizationLoaderInterface $authorizationLoader)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $registry = $this->authorizationLoader->load();
        } catch (\InvalidArgumentException|ParseException $exception) {
            $output->writeln(sprintf('<error>%s</error>', $exception->getMessage()));

            return self::FAILURE;
        }

        $output->writeln('The authorization configuration is valid. Audiences:');
        foreach ($registry->getAudiences() as $audience) {
            $output->writeln(sprintf(' - %s', $audience));
        }

        return self::SUCCESS;
    }
}
