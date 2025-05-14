<?php

namespace App\LeagueOAuth2\Entity\Traits;

trait SerializableTrait
{

    abstract public static function getSupported(): array;

    public function __construct(readonly string $identifier)
    {
        if (!in_array($identifier, self::getSupported(), true)) {
            throw new \InvalidArgumentException(sprintf('Invalid parameter %s passed.', $identifier));
        }
    }

    public static function convertToStringArray(string $data): array
    {
        $result = json_decode($data, true);
        if ($result === null) {
            return [];
        }

        return $result;
    }

    public static function convertToObjectsArray(string $data): array
    {
        $result = self::convertToStringArray($data);

        $response = [];
        foreach ($result as $id) {
            $response[] = new self($id);
        }

        return $response;
    }

    public function jsonSerialize(): ?string
    {
        return $this->identifier;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function __toString(): string
    {
        return $this->identifier;
    }
}