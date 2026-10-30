<?php

namespace App\Tests\Service\Lineup;

use App\Entity\Driver;
use App\Entity\GpDriverPick;
use App\Entity\GpPrediction;
use App\Entity\GrandPrix;
use App\Entity\Season;
use App\Entity\SeasonEntry;
use App\Entity\Team;
use App\Entity\User;
use App\Service\Lineup\GrandPrixLineup;
use App\Service\Lineup\LineupException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class GrandPrixLineupTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private GrandPrixLineup $lineup;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->lineup = $container->get(GrandPrixLineup::class);
    }

    /**
     * Season with drivers A, B, C (one team) entered for the season, a Grand Prix still open
     * for predictions, a stand-in driver S not entered, and a user.
     *
     * @return array{0: array<string, Driver>, 1: GrandPrix, 2: Team, 3: User}
     */
    private function buildFixture(): array
    {
        $season = new Season();
        $season->setYear(random_int(100_000, 999_999));
        $this->em->persist($season);

        $team = new Team();
        $team->setName('Test Team '.uniqid());
        $team->setExternalRef('lineup-test-team-'.uniqid());
        $this->em->persist($team);

        $gp = new GrandPrix();
        $gp->setSeason($season);
        $gp->setRound(1);
        $gp->setName('Test Grand Prix');
        $gp->setCircuit('Test Circuit');
        $gp->setCountry('Testland');
        $gp->setFp1At(new \DateTimeImmutable('+1 day'));
        $gp->setRaceAt(new \DateTimeImmutable('+3 days'));
        $this->em->persist($gp);

        $drivers = [];
        foreach (['A', 'B', 'C', 'S'] as $code) {
            $driver = new Driver();
            $driver->setCode($code);
            $driver->setFirstName($code);
            $driver->setLastName($code);
            $driver->setExternalRef('lineup-test-driver-'.$code.'-'.uniqid());
            $this->em->persist($driver);
            $drivers[$code] = $driver;

            if ('S' !== $code) {
                $entry = new SeasonEntry();
                $entry->setSeason($season)->setDriver($driver)->setTeam($team);
                $this->em->persist($entry);
            }
        }

        $user = new User();
        $user->setEmail('lineup-test-'.uniqid().'@example.com');
        $user->setDisplayName('Lineup Tester '.uniqid());
        $user->setPassword('irrelevant-hash');
        $this->em->persist($user);

        $this->em->flush();

        return [$drivers, $gp, $team, $user];
    }

    /**
     * @param array<string, Driver> $drivers
     * @param string[]              $codes   in predicted order
     */
    private function predict(GrandPrix $gp, User $user, array $drivers, array $codes, string $pole): GpPrediction
    {
        $prediction = (new GpPrediction())->setUser($user)->setGrandPrix($gp)->setPoleDriver($drivers[$pole]);
        foreach ($codes as $i => $code) {
            $prediction->addDriverPick((new GpDriverPick())->setDriver($drivers[$code])->setPredictedRank($i + 1));
        }
        $this->em->persist($prediction);
        $this->em->flush();

        return $prediction;
    }

    /**
     * @return array<int, string> rank => driver code
     */
    private function picks(GpPrediction $prediction): array
    {
        $this->em->refresh($prediction);
        $picks = [];
        foreach ($prediction->getDriverPicks() as $pick) {
            $picks[$pick->getPredictedRank()] = $pick->getDriver()->getCode();
        }
        ksort($picks);

        return $picks;
    }

    public function testLineupDefaultsToTheSeasonEntries(): void
    {
        [$drivers, $gp] = $this->buildFixture();

        self::assertSame(
            [$drivers['A']->getId(), $drivers['B']->getId(), $drivers['C']->getId()],
            $this->lineup->driverIds($gp),
        );
        self::assertSame([], $this->lineup->removedEntries($gp));
    }

    public function testRemovingADriverDropsHimFromExistingPredictions(): void
    {
        [$drivers, $gp, , $user] = $this->buildFixture();
        $prediction = $this->predict($gp, $user, $drivers, ['B', 'A', 'C'], 'A');

        $this->lineup->removeDriver($gp, $drivers['A'], $user);

        self::assertSame([$drivers['B']->getId(), $drivers['C']->getId()], $this->lineup->driverIds($gp));
        self::assertCount(1, $this->lineup->removedEntries($gp));
        self::assertSame([1 => 'B', 2 => 'C'], $this->picks($prediction));
        self::assertNull($prediction->getPoleDriver());
    }

    public function testAddingAStandInAppendsHimToExistingPredictions(): void
    {
        [$drivers, $gp, $team, $user] = $this->buildFixture();
        $prediction = $this->predict($gp, $user, $drivers, ['C', 'B', 'A'], 'B');

        $this->lineup->addDriver($gp, $drivers['S'], $team, $user);

        self::assertSame(
            [$drivers['A']->getId(), $drivers['B']->getId(), $drivers['C']->getId(), $drivers['S']->getId()],
            $this->lineup->driverIds($gp),
        );
        self::assertTrue($this->lineup->entries($gp)[3]['added']);
        self::assertSame([1 => 'C', 2 => 'B', 3 => 'A', 4 => 'S'], $this->picks($prediction));
        self::assertSame($drivers['B']->getId(), $prediction->getPoleDriver()->getId());
    }

    public function testBringingBackARemovedSeasonDriverNeedsNoTeam(): void
    {
        [$drivers, $gp, , $user] = $this->buildFixture();
        $this->lineup->removeDriver($gp, $drivers['B'], $user);

        $this->lineup->addDriver($gp, $drivers['B'], null, $user);

        self::assertSame(
            [$drivers['A']->getId(), $drivers['B']->getId(), $drivers['C']->getId()],
            $this->lineup->driverIds($gp),
        );
        self::assertSame([], $this->lineup->removedEntries($gp));
    }

    public function testRemovingAStandInForgetsHimEntirely(): void
    {
        [$drivers, $gp, $team, $user] = $this->buildFixture();
        $this->lineup->addDriver($gp, $drivers['S'], $team, $user);

        $this->lineup->removeDriver($gp, $drivers['S'], $user);

        self::assertSame(
            [$drivers['A']->getId(), $drivers['B']->getId(), $drivers['C']->getId()],
            $this->lineup->driverIds($gp),
        );
        self::assertSame([], $this->lineup->removedEntries($gp));
    }

    public function testAddingANewDriverRequiresATeam(): void
    {
        [$drivers, $gp, , $user] = $this->buildFixture();

        $this->expectException(LineupException::class);
        $this->lineup->addDriver($gp, $drivers['S'], null, $user);
    }

    public function testCannotAddADriverAlreadyInTheLineup(): void
    {
        [$drivers, $gp, $team, $user] = $this->buildFixture();

        $this->expectException(LineupException::class);
        $this->lineup->addDriver($gp, $drivers['A'], $team, $user);
    }

    public function testCannotRemoveADriverNotInTheLineup(): void
    {
        [$drivers, $gp, , $user] = $this->buildFixture();

        $this->expectException(LineupException::class);
        $this->lineup->removeDriver($gp, $drivers['S'], $user);
    }
}
