<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\GpPrediction;
use App\Entity\SeasonPrediction;
use App\Entity\User;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Fair-play rule: a player's prediction stays hidden from every other player until its
 * deadline has passed (FP1 of the Grand Prix, or FP1 of round 1 for a season-long
 * prediction) — so nobody can peek at, and copy, someone else's pick before locking in
 * their own. The prediction's owner can always see their own picks.
 */
final class PredictionVisibilityExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(private readonly Security $security)
    {
    }

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->apply($queryBuilder, $resourceClass);
    }

    public function applyToItem(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, array $identifiers, ?Operation $operation = null, array $context = []): void
    {
        $this->apply($queryBuilder, $resourceClass);
    }

    private function apply(QueryBuilder $queryBuilder, string $resourceClass): void
    {
        if (!\in_array($resourceClass, [SeasonPrediction::class, GpPrediction::class], true)) {
            return;
        }

        $user = $this->security->getUser();
        if ($user instanceof User && \in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return; // admins can see everything
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $now = new \DateTimeImmutable();

        if (SeasonPrediction::class === $resourceClass) {
            // Deadline = FP1 of round 1 of the season this prediction belongs to.
            $queryBuilder
                ->leftJoin(sprintf('%s.season', $rootAlias), 'ppSeason')
                ->leftJoin('ppSeason.grandPrix', 'ppFirstGp', 'WITH', 'ppFirstGp.round = 1')
                ->andWhere($queryBuilder->expr()->orX(
                    sprintf('%s.user = :pp_current_user', $rootAlias),
                    'ppFirstGp.fp1At <= :pp_now',
                ));
        } else {
            // GpPrediction: deadline = FP1 of that Grand Prix.
            $queryBuilder
                ->leftJoin(sprintf('%s.grandPrix', $rootAlias), 'ppGp')
                ->andWhere($queryBuilder->expr()->orX(
                    sprintf('%s.user = :pp_current_user', $rootAlias),
                    'ppGp.fp1At <= :pp_now',
                ));
        }

        $queryBuilder->setParameter('pp_now', $now);
        // A prediction always belongs to *some* user; when nobody is authenticated
        // (shouldn't happen since these resources require ROLE_USER) fall back to 0
        // so the "owner" branch never matches and only unlocked predictions show.
        $queryBuilder->setParameter('pp_current_user', $user instanceof User ? $user : 0);
    }
}
