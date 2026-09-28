<?php

namespace App\Repository;

use App\Entity\SeasonConstructorPick;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SeasonConstructorPick>
 */
class SeasonConstructorPickRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SeasonConstructorPick::class);
    }
}
