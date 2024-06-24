<?php
declare(strict_types=1);

namespace App\ModelMapper;

use App\Entity\AccessToken;
use App\Model\OAuth2\AccessTokenModel;

class AccessTokenMapper
{
    public function toEntity(AccessTokenModel $model): AccessToken
    {
        $entity = new AccessToken();
        $entity->setScopes(json_encode($model->getScopes()));
        $entity->setIsRevoked($model->isRevoked());
        $entity->setIdentifier($model->getIdentifier());
        $entity->setExpiryDateTime($model->getExpiryDateTime());
        $entity->setClientIdentifier($model->getClient()->getIdentifier());
        $entity->setUserIdentifier($model->getUserIdentifier());

        return $entity;
    }
}