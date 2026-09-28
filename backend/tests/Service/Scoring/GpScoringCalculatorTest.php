<?php

namespace App\Tests\Service\Scoring;

use App\Entity\Driver;
use App\Entity\Enum\ResultStatus;
use App\Entity\FastestLap;
use App\Entity\GpDriverPick;
use App\Entity\GpPrediction;
use App\Entity\GrandPrix;
use App\Entity\QualifyingResult;
use App\Entity\RaceResult;
use App\Entity\Season;
use App\Entity\SprintResult;
use App\Entity\User;
use App\Service\Scoring\GpScoringCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class GpScoringCalculatorTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private GpScoringCalculator $calculator;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->calculator = $container->get(GpScoringCalculator::class);
    }

    /**
     * Builds a fresh, uniquely-identified Season/GrandPrix/Drivers/User for one test, so
     * tests never interfere with each other's data even though the test database isn't
     * reset between runs.
     *
     * @return array{0: array<string, Driver>, 1: GrandPrix, 2: User}
     *
     * @return array{0: array<string, Driver>, 1: GrandPrix, 2: User}
     */
    private function buildFixture(bool $hasSprint = false): array
    {
        $season = new Season();
        $season->setYear(random_int(100_000, 999_999)); // unique per test run, avoids clashing across test methods
        $this->em->persist($season);

        $gp = new GrandPrix();
        $gp->setSeason($season);
        $gp->setRound(1);
        $gp->setName('Test Grand Prix');
        $gp->setCircuit('Test Circuit');
        $gp->setCountry('Testland');
        $gp->setHasSprint($hasSprint);
        $gp->setFp1At(new \DateTimeImmutable('-1 day'));
        $gp->setRaceAt(new \DateTimeImmutable('-1 hour'));
        $this->em->persist($gp);

        $drivers = [];
        foreach (['A', 'B', 'C', 'D', 'E'] as $i => $code) {
            $driver = new Driver();
            $driver->setCode($code);
            $driver->setFirstName($code);
            $driver->setLastName($code);
            $driver->setExternalRef('gp-test-driver-'.$code.'-'.uniqid());
            $this->em->persist($driver);
            $drivers[$code] = $driver;
        }

        $user = new User();
        $user->setEmail('gp-test-'.uniqid().'@example.com');
        $user->setDisplayName('GP Tester '.uniqid());
        $user->setPassword('irrelevant-hash');
        $this->em->persist($user);

        $this->em->flush();

        return [$drivers, $gp, $user];
    }

    public function testDriverPicksScorePerBracketAgainstActualResults(): void
    {
        [$drivers, $gp, $user] = $this->buildFixture();

        // Actual race result: A 1st, B 2nd, C 3rd, D 4th, E 5th.
        $position = 1;
        foreach (['A', 'B', 'C', 'D', 'E'] as $code) {
            $result = new RaceResult();
            $result->setGrandPrix($gp)->setDriver($drivers[$code])->setPosition($position)->setStatus(ResultStatus::FINISHED)->setPoints(0);
            $this->em->persist($result);
            ++$position;
        }
        $this->em->flush();

        $prediction = new GpPrediction();
        $prediction->setUser($user)->setGrandPrix($gp);
        // Predict A 1st (exact, bracket "winner" = 5pts), predict B 3rd but actual is 2nd
        // (same "2nd-3rd" bracket = 3pts anyway), predict C 4th but actual is 3rd (different
        // bracket = 0pts).
        $picks = ['A' => 1, 'B' => 3, 'C' => 4, 'D' => 5, 'E' => 2];
        foreach ($picks as $code => $rank) {
            $pick = new GpDriverPick();
            $pick->setDriver($drivers[$code])->setPredictedRank($rank);
            $prediction->addDriverPick($pick);
        }
        $this->em->persist($prediction);
        $this->em->flush();

        $score = $this->calculator->compute($prediction);

        // A: 5 (winner), B: 3 (same 2nd-3rd bracket), C: 0 (3rd predicted 4th, different bracket),
        // D: 0 (5th predicted 5th... wait D actual is 4th, predicted 5th -> same 4th-5th bracket = 2),
        // E: predicted 2nd, actual 5th -> different bracket = 0.
        self::assertSame(5 + 3 + 0 + 2 + 0, $score->getDriversBracketPoints());
        self::assertSame(0, $score->getPolePoints());
        self::assertSame(0, $score->getFastestLapPoints());
        self::assertSame(0, $score->getSprintPoints());
        self::assertSame($score->getDriversBracketPoints(), $score->getTotalPoints());
    }

    public function testPoleAndFastestLapBonusesRequireAnExactMatch(): void
    {
        [$drivers, $gp, $user] = $this->buildFixture();

        $quali = new QualifyingResult();
        $quali->setGrandPrix($gp)->setDriver($drivers['B'])->setPosition(1);
        $this->em->persist($quali);

        $fastestLap = new FastestLap();
        $fastestLap->setGrandPrix($gp)->setDriver($drivers['C']);
        $this->em->persist($fastestLap);
        $this->em->flush();

        $prediction = new GpPrediction();
        $prediction->setUser($user)->setGrandPrix($gp);
        $prediction->setPoleDriver($drivers['B']); // correct
        $prediction->setFastestLapDriver($drivers['A']); // wrong (actual is C)
        $this->em->persist($prediction);
        $this->em->flush();

        $score = $this->calculator->compute($prediction);

        self::assertSame(1, $score->getPolePoints());
        self::assertSame(0, $score->getFastestLapPoints());
    }

    public function testSprintPodiumScoresOnePointPerCorrectPosition(): void
    {
        [$drivers, $gp, $user] = $this->buildFixture(hasSprint: true);

        foreach ([1 => 'A', 2 => 'B', 3 => 'C'] as $pos => $code) {
            $sprint = new SprintResult();
            $sprint->setGrandPrix($gp)->setDriver($drivers[$code])->setPosition($pos)->setStatus(ResultStatus::FINISHED);
            $this->em->persist($sprint);
        }

        $prediction = new GpPrediction();
        $prediction->setUser($user)->setGrandPrix($gp);
        $prediction->setSprintP1($drivers['A']); // correct
        $prediction->setSprintP2($drivers['C']); // wrong slot (C was actually P3)
        $prediction->setSprintP3($drivers['C']); // correct
        $this->em->persist($prediction);
        $this->em->flush();

        $score = $this->calculator->compute($prediction);

        self::assertSame(2, $score->getSprintPoints());
    }

    protected function tearDown(): void
    {
        $this->em->close();
        parent::tearDown();
    }
}
