<?php
declare(strict_types=1);

namespace App\Security\Authorization\Loader;

use App\Security\Authorization\AuthorizationRegistry;

interface AuthorizationLoaderInterface
{
    public function load(): AuthorizationRegistry;
}