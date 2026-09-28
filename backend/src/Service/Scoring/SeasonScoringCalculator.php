<?php

namespace App\Service\Scoring;

use App\Entity\ConstructorStanding;
use App\Entity\DriverStanding;
use App\Entity\Season;
use App\Entity\SeasonPrediction;
use App\Entity\SeasonScore;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Scores a player's championship-long prediction against the current (or final)
 * drivers'/constructors' standings.
 */
final class SeasonScoringCalculator
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function compute(SeasonPrediction $prediction): SeasonScore
    {
        $season = $prediction->getSeason();

        $driversPoints = $this->scoreDriverPicks($prediction, $season);
        $constructorsPoints = $this->scoreConstructorPicks($prediction, $season);

        $score = $this->entityManager->getRepository(SeasonScore::class)->findOneBy([
            'user' => $prediction->getUser(),
            'season' => $season,
        ]) ?? new SeasonScore();

        $score->setUser($prediction->getUser())
            ->setSeason($season)
            ->setBreakdown($driversPoints, $constructorsPoints);

        $this->entityManager->persist($score);

        return $score;
    }

    /**
     * Recomputes and persists the score of every player who submitted a season prediction.
     *
     * @return SeasonScore[]
     */
    public function computeForAllPlayers(Season $season): array
    {
        $predictions = $this->entityManager->getRepository(SeasonPrediction::class)->findBy(['season' => $season]);

        $scores = [];
        foreach ($predictions as $prediction) {
            $scores[] = $this->compute($prediction);
        }
        $this->entityManager->flush();

        return $scores;
    }

    private function scoreDriverPicks(SeasonPrediction $prediction, Season $season): int
    {
        $positionByDriverId = [];
        foreach ($this->entityManager->getRepository(DriverStanding::class)->findBy(['season' => $season]) as $standing) {
            $positionByDriverId[$standing->getDriver()->getId()] = $standing->getPosition();
        }

        $table = ScoringRules::seasonDrivers();
        $points = 0;
        foreach ($prediction->getDriverPicks() as $pick) {
            $actual = $positionByDriverId[$pick->getDriver()->getId()] ?? null;
            if (null !== $actual) {
                $points += $table->scoreForPrediction($pick->getPredictedRank(), $actual);
            }
        }

        return $points;
    }

    private function scoreConstructorPicks(SeasonPrediction $prediction, Season $season): int
    {
        $positionByTeamId = [];
        foreach ($this->entityManager->getRepository(ConstructorStanding::class)->findBy(['season' => $season]) as $standing) {
            $positionByTeamId[$standing->getTeam()->getId()] = $standing->getPosition();
        }

        $table = ScoringRules::seasonConstructors();
        $points = 0;
        foreach ($prediction->getConstructorPicks() as $pick) {
            $actual = $positionByTeamId[$pick->getTeam()->getId()] ?? null;
            if (null !== $actual) {
                $points += $table->scoreForPrediction($pick->getPredictedRank(), $actual);
            }
        }

        return $points;
    }
}
