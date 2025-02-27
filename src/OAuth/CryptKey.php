<?php

namespace App\OAuth;

class CryptKey extends \League\OAuth2\Server\CryptKey
{
    private ?string $id;

    public function __construct($keyPath, $passPhrase = null, $keyPermissionsCheck = true, $id = null) {
        $this->id = $id;
        parent::__construct($keyPath, $passPhrase, $keyPermissionsCheck);
    }

    public function getId(): string
    {
        return $this->id;
    }
}