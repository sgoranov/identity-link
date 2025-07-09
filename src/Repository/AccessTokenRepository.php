<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\AccessToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AccessToken>
 *
 * @method AccessToken|null find($id, $lockMode = null, $lockVersion = null)
 * @method AccessToken|null findOneBy(array $criteria, array $orderBy = null)
 * @method AccessToken[]    findAll()
 * @method AccessToken[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AccessTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AccessToken::class);
    }

    public function getByIdentifier(string $tokenId): ?AccessToken
    {
        return $this->findOneBy(['identifier' => $tokenId]);
    }

    public function revokeByUserIdentifier(string $userIdentifier): void
    {
        $qb = $this->_em->createQueryBuilder();
        $qb->update(AccessToken::class, 'a')
            ->set('a.isRevoked', ':revoked')
            ->where('a.userIdentifier = :userIdentifier')
            ->andWhere('a.isRevoked = false')
            ->andWhere('a.expiryDateTime > :now')
            ->setParameter('revoked', true)
            ->setParameter('userIdentifier', $userIdentifier)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }
}
