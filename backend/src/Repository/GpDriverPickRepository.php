<?php

namespace App\Repository;

use App\Entity\GpDriverPick;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GpDriverPick>
 */
class GpDriverPickRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GpDriverPick::class);
    }
}
