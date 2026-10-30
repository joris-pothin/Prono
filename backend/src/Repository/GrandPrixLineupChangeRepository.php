<?php

namespace App\Repository;

use App\Entity\GrandPrixLineupChange;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GrandPrixLineupChange>
 */
class GrandPrixLineupChangeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GrandPrixLineupChange::class);
    }
}
