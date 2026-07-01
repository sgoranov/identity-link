<?php
declare(strict_types=1);

namespace App\Repository\Trait;

trait RevocationTrait
{
    abstract public function findOneBy(array $criteria, ?array $orderBy = null);

    abstract protected function getEntityManager();

    private function revoke($identifier)
    {
        $entity = $this->findOneBy(['identifier' => $identifier]);
        if ($entity === null) {
            throw new \InvalidArgumentException('Invalid entity identifier passed.');
        }

        $entity->setIsRevoked(true);

        $entityManager = $this->getEntityManager();
        $entityManager->persist($entity);
        $entityManager->flush();
    }

    private function isRevoked($identifier)
    {
        $entity = $this->findOneBy(['identifier' => $identifier]);
        if ($entity === null) {
            throw new \InvalidArgumentException('Invalid entity identifier passed.');
        }

        return $entity->isRevoked();
    }
}