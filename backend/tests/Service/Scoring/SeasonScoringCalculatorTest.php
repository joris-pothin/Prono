<?php

namespace App\Tests\Service\Scoring;

use App\Entity\ConstructorStanding;
use App\Entity\Driver;
use App\Entity\DriverStanding;
use App\Entity\Season;
use App\Entity\SeasonConstructorPick;
use App\Entity\SeasonDriverPick;
use App\Entity\SeasonPrediction;
use App\Entity\Team;
use App\Entity\User;
use App\Service\Scoring\SeasonScoringCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SeasonScoringCalculatorTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private SeasonScoringCalculator $calculator;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->calculator = $container->get(SeasonScoringCalculator::class);
    }

    public function testDriverAndConstructorPicksScoreIndependently(): void
    {
        $season = new Season();
        $season->setYear(random_int(100_000, 999_999)); // unique per test run, avoids clashing on repeated runs
        $this->em->persist($season);

        $drivers = [];
        foreach (['A', 'B', 'C'] as $code) {
            $driver = new Driver();
            $driver->setCode($code)->setFirstName($code)->setLastName($code)->setExternalRef('season-test-driver-'.$code.'-'.uniqid());
            $this->em->persist($driver);
            $drivers[$code] = $driver;
        }

        $teams = [];
        foreach (['X', 'Y'] as $code) {
            $team = new Team();
            $team->setName('Team '.$code)->setExternalRef('season-test-team-'.$code.'-'.uniqid());
            $this->em->persist($team);
            $teams[$code] = $team;
        }

        $user = new User();
        $user->setEmail('season-test-'.uniqid().'@example.com')->setDisplayName('Season Tester '.uniqid())->setPassword('irrelevant-hash');
        $this->em->persist($user);

        $this->em->flush();

        // Final standings: A 1st, B 2nd, C 3rd — X 1st, Y 2nd.
        foreach (['A' => 1, 'B' => 2, 'C' => 3] as $code => $position) {
            $standing = new DriverStanding();
            $standing->setSeason($season)->setDriver($drivers[$code])->setPosition($position)->setPoints(0)->setUpdatedAt(new \DateTimeImmutable());
            $this->em->persist($standing);
        }
        foreach (['X' => 1, 'Y' => 2] as $code => $position) {
            $standing = new ConstructorStanding();
            $standing->setSeason($season)->setTeam($teams[$code])->setPosition($position)->setPoints(0)->setUpdatedAt(new \DateTimeImmutable());
            $this->em->persist($standing);
        }

        // Prediction: A predicted 1st (exact, 20pts), B predicted 3rd but actual 2nd (same
        // "2nd-3rd" bracket, 10pts), C predicted 2nd but actual 3rd (same bracket too, 10pts).
        $prediction = new SeasonPrediction();
        $prediction->setUser($user)->setSeason($season);
        foreach (['A' => 1, 'B' => 3, 'C' => 2] as $code => $rank) {
            $pick = new SeasonDriverPick();
            $pick->setDriver($drivers[$code])->setPredictedRank($rank);
            $prediction->addDriverPick($pick);
        }
        // Constructors: X predicted 2nd but actual 1st (different bracket for a 2-team
        // table where bracket "1st" and "2nd-3rd" differ, so 0pts), Y predicted 1st but
        // actual 2nd (different bracket too, 0pts).
        foreach (['X' => 2, 'Y' => 1] as $code => $rank) {
            $pick = new SeasonConstructorPick();
            $pick->setTeam($teams[$code])->setPredictedRank($rank);
            $prediction->addConstructorPick($pick);
        }
        $this->em->persist($prediction);
        $this->em->flush();

        $score = $this->calculator->compute($prediction);

        self::assertSame(20 + 10 + 10, $score->getDriversPoints());
        self::assertSame(0, $score->getConstructorsPoints());
        self::assertSame(40, $score->getTotalPoints());
    }

    protected function tearDown(): void
    {
        $this->em->close();
        parent::tearDown();
    }
}
