<?php

namespace App\Repository;

use App\Entity\ConstructorStanding;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ConstructorStanding>
 */
class ConstructorStandingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ConstructorStanding::class);
    }
}
