<?php

namespace App\Controller\Leaderboard;

use App\Entity\GpScore;
use App\Entity\RiskBet;
use App\Entity\Season;
use App\Entity\SeasonScore;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Combines every source of points — per-Grand-Prix scores, the season-long prediction
 * score, and resolved risk bets — into one ranked table for a season.
 */
final class LeaderboardController
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    #[Route('/api/leaderboard/{seasonId}', name: 'app_leaderboard', requirements: ['seasonId' => '\d+'], methods: ['GET'])]
    public function __invoke(int $seasonId): JsonResponse
    {
        $season = $this->entityManager->getRepository(Season::class)->find($seasonId);
        if (null === $season) {
            return new JsonResponse(['error' => 'Saison introuvable.'], 404);
        }

        $users = $this->entityManager->getRepository(User::class)->findAll();

        $gpPointsByUserId = [];
        $gpRows = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(s.user) as userId', 'SUM(s.totalPoints) as points')
            ->from(GpScore::class, 's')
            ->join('s.grandPrix', 'gp')
            ->where('gp.season = :season')
            ->setParameter('season', $season)
            ->groupBy('s.user')
            ->getQuery()
            ->getResult();
        foreach ($gpRows as $row) {
            $gpPointsByUserId[(int) $row['userId']] = (int) $row['points'];
        }

        $seasonPointsByUserId = [];
        foreach ($this->entityManager->getRepository(SeasonScore::class)->findBy(['season' => $season]) as $score) {
            $seasonPointsByUserId[$score->getUser()->getId()] = $score->getTotalPoints();
        }

        $riskPointsByUserId = [];
        foreach ($this->entityManager->getRepository(RiskBet::class)->findBy(['season' => $season]) as $bet) {
            $delta = $bet->getPointsDelta();
            if (0 !== $delta) {
                $uid = $bet->getProposedBy()->getId();
                $riskPointsByUserId[$uid] = ($riskPointsByUserId[$uid] ?? 0) + $delta;
            }
        }

        $rows = [];
        foreach ($users as $user) {
            $uid = $user->getId();
            $gpPoints = $gpPointsByUserId[$uid] ?? 0;
            $seasonPoints = $seasonPointsByUserId[$uid] ?? 0;
            $riskPoints = $riskPointsByUserId[$uid] ?? 0;

            $rows[] = [
                'user' => ['id' => $uid, 'displayName' => $user->getDisplayName()],
                'gpPoints' => $gpPoints,
                'seasonPoints' => $seasonPoints,
                'riskPoints' => $riskPoints,
                'totalPoints' => $gpPoints + $seasonPoints + $riskPoints,
            ];
        }

        usort($rows, static fn (array $a, array $b) => $b['totalPoints'] <=> $a['totalPoints']);

        return new JsonResponse($rows);
    }
}
