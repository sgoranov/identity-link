<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\AuthorizationSession;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuthorizationSession>
 *
 * @method AuthorizationSession|null find($id, $lockMode = null, $lockVersion = null)
 */
class AuthorizationSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuthorizationSession::class);
    }

    public function save(AuthorizationSession $entity): void
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
    }

    public function remove(AuthorizationSession $entity): void
    {
        $em = $this->getEntityManager();
        $em->remove($entity);
        $em->flush();
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