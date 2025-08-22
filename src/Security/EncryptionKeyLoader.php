<?php
declare(strict_types=1);

namespace App\Security;

use Defuse\Crypto\Key;

class EncryptionKeyLoader
{
    private string $encryptionKeyPath;

    public function setEncryptionKeyPath(string $encryptionKeyPath): void
    {
        $this->encryptionKeyPath = $encryptionKeyPath;
    }

    public function loadEncryptionKey(): Key
    {
        try {
            return Key::loadFromAsciiSafeString(file_get_contents($this->encryptionKeyPath));
        } catch (\Throwable $exception) {
            throw new \RuntimeException('Unable to load the encryption key.', 0, $exception);
        }
    }
}