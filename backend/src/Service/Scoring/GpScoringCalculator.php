<?php

namespace App\Service\Scoring;

use App\Entity\FastestLap;
use App\Entity\GpPrediction;
use App\Entity\GpScore;
use App\Entity\GrandPrix;
use App\Entity\QualifyingResult;
use App\Entity\RaceResult;
use App\Entity\SprintResult;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Scores a player's prediction for a single Grand Prix weekend against the actual
 * qualifying/race/sprint results, once they're in.
 */
final class GpScoringCalculator
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function compute(GpPrediction $prediction): GpScore
    {
        $gp = $prediction->getGrandPrix();

        $driversPoints = $this->scoreDriverPicks($prediction, $gp);
        $polePoints = $this->scorePole($prediction, $gp);
        $fastestLapPoints = $this->scoreFastestLap($prediction, $gp);
        $sprintPoints = $gp->isHasSprint() ? $this->scoreSprint($prediction, $gp) : 0;

        $score = $this->entityManager->getRepository(GpScore::class)->findOneBy([
            'user' => $prediction->getUser(),
            'grandPrix' => $gp,
        ]) ?? new GpScore();

        $score->setUser($prediction->getUser())
            ->setGrandPrix($gp)
            ->setBreakdown($driversPoints, $polePoints, $fastestLapPoints, $sprintPoints);

        $this->entityManager->persist($score);

        return $score;
    }

    /**
     * Recomputes and persists the score of every player who submitted a prediction for this Grand Prix.
     *
     * @return GpScore[]
     */
    public function computeForAllPlayers(GrandPrix $grandPrix): array
    {
        $predictions = $this->entityManager->getRepository(GpPrediction::class)->findBy(['grandPrix' => $grandPrix]);

        $scores = [];
        foreach ($predictions as $prediction) {
            $scores[] = $this->compute($prediction);
        }
        $this->entityManager->flush();

        return $scores;
    }

    private function scoreDriverPicks(GpPrediction $prediction, GrandPrix $gp): int
    {
        $positionByDriverId = [];
        foreach ($this->entityManager->getRepository(RaceResult::class)->findBy(['grandPrix' => $gp]) as $result) {
            if (null !== $result->getPosition()) {
                $positionByDriverId[$result->getDriver()->getId()] = $result->getPosition();
            }
        }

        $table = ScoringRules::gpDrivers();
        $points = 0;
        foreach ($prediction->getDriverPicks() as $pick) {
            $actual = $positionByDriverId[$pick->getDriver()->getId()] ?? null;
            if (null !== $actual) {
                $points += $table->scoreForPrediction($pick->getPredictedRank(), $actual);
            }
        }

        return $points;
    }

    private function scorePole(GpPrediction $prediction, GrandPrix $gp): int
    {
        if (null === $prediction->getPoleDriver()) {
            return 0;
        }

        $pole = $this->entityManager->getRepository(QualifyingResult::class)->findOneBy(['grandPrix' => $gp, 'position' => 1]);

        return (null !== $pole && $pole->getDriver()->getId() === $prediction->getPoleDriver()->getId())
            ? ScoringRules::POLE_POINTS
            : 0;
    }

    private function scoreFastestLap(GpPrediction $prediction, GrandPrix $gp): int
    {
        if (null === $prediction->getFastestLapDriver()) {
            return 0;
        }

        $fastestLap = $this->entityManager->getRepository(FastestLap::class)->findOneBy(['grandPrix' => $gp]);

        return (null !== $fastestLap && $fastestLap->getDriver()->getId() === $prediction->getFastestLapDriver()->getId())
            ? ScoringRules::FASTEST_LAP_POINTS
            : 0;
    }

    private function scoreSprint(GpPrediction $prediction, GrandPrix $gp): int
    {
        $actualByPosition = [];
        foreach ($this->entityManager->getRepository(SprintResult::class)->findBy(['grandPrix' => $gp]) as $result) {
            if (null !== $result->getPosition() && $result->getPosition() <= 3) {
                $actualByPosition[$result->getPosition()] = $result->getDriver()->getId();
            }
        }

        $predictedByPosition = [
            1 => $prediction->getSprintP1(),
            2 => $prediction->getSprintP2(),
            3 => $prediction->getSprintP3(),
        ];

        $points = 0;
        foreach ($predictedByPosition as $position => $driver) {
            if (null !== $driver && ($actualByPosition[$position] ?? null) === $driver->getId()) {
                $points += ScoringRules::SPRINT_PODIUM_POSITION_POINTS;
            }
        }

        return $points;
    }
}
