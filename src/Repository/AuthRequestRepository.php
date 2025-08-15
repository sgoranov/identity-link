<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\AuthRequest;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

class AuthRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuthRequest::class);
    }

    public function save(AuthRequest $entity): void
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
    }

    public function remove(AuthRequest $entity): void
    {
        $em = $this->getEntityManager();
        $em->remove($entity);
        $em->flush();
    }

    /**
     * Returns an active (not expired, not consumed) request or null.
     */
    public function findActive(string $id): ?AuthRequest
    {
        $now = new DateTimeImmutable();

        return $this->createQueryBuilder('r')
            ->andWhere('r.id = :id')
            ->andWhere('r.expiresAt > :now')
            ->andWhere('r.consumed = false')
            ->setParameter('id', $id)
            ->setParameter('now', $now)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Optional housekeeping: remove already expired rows.
     */
    public function purgeExpired(): int
    {
        $now = new DateTimeImmutable();

        return $this->createQueryBuilder('r')
            ->delete()
            ->where('r.expiresAt <= :now')
            ->setParameter('now', $now)
            ->getQuery()
            ->execute();
    }
}