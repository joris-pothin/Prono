<?php

namespace App\Service\Lineup;

use App\Entity\Driver;
use App\Entity\Enum\LineupChangeType;
use App\Entity\GpDriverPick;
use App\Entity\GpPrediction;
use App\Entity\GrandPrix;
use App\Entity\GrandPrixLineupChange;
use App\Entity\SeasonEntry;
use App\Entity\Team;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * The drivers actually taking part in a given Grand Prix: the season entries, minus the
 * ones sitting this weekend out, plus stand-ins added for it (GrandPrixLineupChange).
 *
 * Changing the lineup also adapts every prediction already submitted for that GP so they
 * stay valid full rankings: a removed driver's pick is dropped (ranks below move up one,
 * pole/fastest-lap/sprint picks on him are cleared), an added driver is appended last.
 */
final class GrandPrixLineup
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @return list<array{driver: Driver, team: Team, added: bool}> season entries first (in their usual order), then added drivers
     */
    public function entries(GrandPrix $gp): array
    {
        $changes = $this->changesByDriverId($gp);

        $entries = [];
        foreach ($this->seasonEntries($gp) as $entry) {
            if (!isset($changes[$entry->getDriver()->getId()])) {
                $entries[] = ['driver' => $entry->getDriver(), 'team' => $entry->getTeam(), 'added' => false];
            }
        }
        foreach ($changes as $change) {
            if (LineupChangeType::ADDED === $change->getType()) {
                $entries[] = ['driver' => $change->getDriver(), 'team' => $change->getTeam(), 'added' => true];
            }
        }

        return $entries;
    }

    /**
     * Season drivers sitting this Grand Prix out.
     *
     * @return list<array{driver: Driver, team: Team}>
     */
    public function removedEntries(GrandPrix $gp): array
    {
        $changes = $this->changesByDriverId($gp);

        $removed = [];
        foreach ($this->seasonEntries($gp) as $entry) {
            if (LineupChangeType::REMOVED === ($changes[$entry->getDriver()->getId()] ?? null)?->getType()) {
                $removed[] = ['driver' => $entry->getDriver(), 'team' => $entry->getTeam()];
            }
        }

        return $removed;
    }

    /**
     * @return int[]
     */
    public function driverIds(GrandPrix $gp): array
    {
        return array_map(static fn (array $e) => $e['driver']->getId(), $this->entries($gp));
    }

    /**
     * Adds a driver to this Grand Prix. A season driver previously removed simply comes back
     * with his season team; anyone else needs the team he races for this weekend.
     *
     * @throws LineupException
     */
    public function addDriver(GrandPrix $gp, Driver $driver, ?Team $team, User $by): void
    {
        if (\in_array($driver->getId(), $this->driverIds($gp), true)) {
            throw new LineupException(\sprintf('%s fait déjà partie du plateau de ce Grand Prix.', $driver->getFullName()));
        }

        $change = $this->changesByDriverId($gp)[$driver->getId()] ?? null;
        if (null !== $change) {
            // Only a REMOVED change can be left at this point: undo it.
            $this->entityManager->remove($change);
        } else {
            if (null === $team) {
                throw new LineupException('Choisis l’écurie pour laquelle ce pilote court ce week-end.');
            }
            $this->entityManager->persist(
                (new GrandPrixLineupChange())
                    ->setGrandPrix($gp)
                    ->setDriver($driver)
                    ->setType(LineupChangeType::ADDED)
                    ->setTeam($team)
                    ->setCreatedBy($by),
            );
        }

        foreach ($this->predictions($gp) as $prediction) {
            $prediction->addDriverPick(
                (new GpDriverPick())
                    ->setDriver($driver)
                    ->setPredictedRank($prediction->getDriverPicks()->count() + 1),
            );
        }

        $this->entityManager->flush();
    }

    /**
     * @throws LineupException
     */
    public function removeDriver(GrandPrix $gp, Driver $driver, User $by): void
    {
        if (!\in_array($driver->getId(), $this->driverIds($gp), true)) {
            throw new LineupException(\sprintf('%s ne fait pas partie du plateau de ce Grand Prix.', $driver->getFullName()));
        }

        $change = $this->changesByDriverId($gp)[$driver->getId()] ?? null;
        if (null !== $change) {
            // Only an ADDED change can be left at this point: the stand-in is simply dropped.
            $this->entityManager->remove($change);
        } else {
            $this->entityManager->persist(
                (new GrandPrixLineupChange())
                    ->setGrandPrix($gp)
                    ->setDriver($driver)
                    ->setType(LineupChangeType::REMOVED)
                    ->setCreatedBy($by),
            );
        }

        foreach ($this->predictions($gp) as $prediction) {
            $remaining = [];
            foreach ($prediction->getDriverPicks() as $pick) {
                if ($pick->getDriver()->getId() !== $driver->getId()) {
                    $remaining[] = $pick->getDriver();
                }
            }

            // Rebuild rather than renumber in place: UNIQ_GDP_PREDICTION_RANK would trip over
            // Doctrine running the updates before the deletion of the removed pick.
            $prediction->clearDriverPicks();
            $this->entityManager->flush();
            foreach ($remaining as $i => $remainingDriver) {
                $prediction->addDriverPick((new GpDriverPick())->setDriver($remainingDriver)->setPredictedRank($i + 1));
            }

            if ($prediction->getPoleDriver()?->getId() === $driver->getId()) {
                $prediction->setPoleDriver(null);
            }
            if ($prediction->getFastestLapDriver()?->getId() === $driver->getId()) {
                $prediction->setFastestLapDriver(null);
            }
            if ($prediction->getSprintP1()?->getId() === $driver->getId()) {
                $prediction->setSprintP1(null);
            }
            if ($prediction->getSprintP2()?->getId() === $driver->getId()) {
                $prediction->setSprintP2(null);
            }
            if ($prediction->getSprintP3()?->getId() === $driver->getId()) {
                $prediction->setSprintP3(null);
            }
        }

        $this->entityManager->flush();
    }

    /**
     * @return SeasonEntry[]
     */
    private function seasonEntries(GrandPrix $gp): array
    {
        return $this->entityManager->getRepository(SeasonEntry::class)->findBy(['season' => $gp->getSeason()], ['id' => 'ASC']);
    }

    /**
     * @return array<int, GrandPrixLineupChange>
     */
    private function changesByDriverId(GrandPrix $gp): array
    {
        $changes = [];
        foreach ($this->entityManager->getRepository(GrandPrixLineupChange::class)->findBy(['grandPrix' => $gp], ['id' => 'ASC']) as $change) {
            $changes[$change->getDriver()->getId()] = $change;
        }

        return $changes;
    }

    /**
     * @return GpPrediction[]
     */
    private function predictions(GrandPrix $gp): array
    {
        return $this->entityManager->getRepository(GpPrediction::class)->findBy(['grandPrix' => $gp]);
    }
}
