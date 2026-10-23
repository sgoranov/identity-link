<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

// Execute migrations and fixtures
passthru(sprintf(
    'php "%s/bin/console" doctrine:migrations:migrate --no-interaction --env=test',
    dirname(__DIR__)
));

passthru(sprintf(
    'php "%s/bin/console" doctrine:fixtures:load --no-interaction --env=test',
    dirname(__DIR__)
));
