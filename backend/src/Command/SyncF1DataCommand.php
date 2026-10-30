<?php

namespace App\Command;

use App\Entity\Driver;
use App\Entity\Enum\ResultStatus;
use App\Entity\FastestLap;
use App\Entity\GrandPrix;
use App\Entity\QualifyingResult;
use App\Entity\RaceResult;
use App\Entity\Season;
use App\Entity\SeasonEntry;
use App\Entity\SprintResult;
use App\Entity\Team;
use App\Service\F1Data\JolpicaClient;
use App\Service\Scoring\GpScoringCalculator;
use App\Service\Scoring\SeasonScoringCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Pulls calendar, drivers, constructors, standings and results for one season from
 * jolpica-f1 (the Ergast-compatible successor API) and upserts them into our database.
 * Safe to re-run at any point in the season — every entity is matched on a stable
 * external id, so results already scored are simply refreshed in place.
 *
 * Run it regularly (e.g. a cron a few hours after each session) to keep standings and
 * results current. Every player's scores (App\Service\Scoring) are recomputed right
 * after: per-Grand-Prix scores for any round with fresh results, and the season-long
 * score against the latest standings (so it reads as a live "if the season ended today"
 * total until the season actually does).
 */
#[AsCommand(name: 'app:f1:sync', description: 'Sync calendar, drivers, constructors, standings and results from jolpica-f1 for one season')]
final class SyncF1DataCommand extends Command
{
    public function __construct(
        private readonly JolpicaClient $client,
        private readonly EntityManagerInterface $entityManager,
        private readonly GpScoringCalculator $gpScoringCalculator,
        private readonly SeasonScoringCalculator $seasonScoringCalculator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('year', InputArgument::REQUIRED, 'Season year, e.g. 2026');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $year = (int) $input->getArgument('year');

        $season = $this->entityManager->getRepository(Season::class)->findOneBy(['year' => $year])
            ?? new Season();
        $season->setYear($year);
        $this->entityManager->persist($season);
        $this->entityManager->flush();

        $io->section("Calendrier {$year}");
        $races = $this->syncCalendar($season, $io);

        $io->section('Pilotes');
        $this->syncDrivers($year, $io);

        $io->section('Écuries');
        $this->syncTeams($year, $io);

        $io->section('Classement pilotes & alignements de saison');
        $this->syncDriverStandings($season, $year, $io);

        $io->section('Classement constructeurs');
        $this->syncConstructorStandings($season, $year, $io);

        $io->section('Résultats par Grand Prix');
        foreach ($races as $race) {
            $this->syncRound($season, $year, $race, $io);
        }

        $io->section('Recalcul des scores');
        $seasonScores = $this->seasonScoringCalculator->computeForAllPlayers($season);
        $io->writeln(\sprintf('  %d score(s) de classement général recalculé(s).', \count($seasonScores)));

        $this->entityManager->clear(); // syncRound() detached entities across many flush()es; start fresh for scoring reads
        $season = $this->entityManager->getRepository(Season::class)->find($season->getId());
        foreach ($season->getGrandPrix() as $gp) {
            $scores = $this->gpScoringCalculator->computeForAllPlayers($gp);
            if ([] !== $scores) {
                $io->writeln(\sprintf('  Round %d — %d score(s) recalculé(s).', $gp->getRound(), \count($scores)));
            }
        }

        $io->success("Synchronisation {$year} terminée.");

        return Command::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>> the raw race entries from the calendar, for reuse in syncRound()
     */
    private function syncCalendar(Season $season, SymfonyStyle $io): array
    {
        $data = $this->client->fetch("/{$season->getYear()}.json");
        $races = $data['MRData']['RaceTable']['Races'] ?? [];

        foreach ($races as $race) {
            $round = (int) $race['round'];
            $gp = $this->entityManager->getRepository(GrandPrix::class)->findOneBy(['season' => $season, 'round' => $round])
                ?? new GrandPrix();

            $gp->setSeason($season);
            $gp->setRound($round);
            $gp->setName($race['raceName']);
            $gp->setCircuit($race['Circuit']['circuitName'] ?? '');
            $gp->setCountry($race['Circuit']['Location']['country'] ?? '');
            $gp->setExternalRef($race['Circuit']['circuitId'] ?? null);
            $gp->setHasSprint(isset($race['Sprint']));

            $fp1 = $this->combineDateTime($race['FirstPractice']['date'] ?? null, $race['FirstPractice']['time'] ?? null)
                ?? $this->combineDateTime($race['date'], $race['time'] ?? '00:00:00Z')?->modify('-3 days');
            $gp->setFp1At($fp1 ?? new \DateTimeImmutable($race['date']));

            $gp->setQualifyingAt($this->combineDateTime($race['Qualifying']['date'] ?? null, $race['Qualifying']['time'] ?? null));
            $gp->setSprintAt($this->combineDateTime($race['Sprint']['date'] ?? null, $race['Sprint']['time'] ?? null));
            $gp->setRaceAt($this->combineDateTime($race['date'], $race['time'] ?? '00:00:00Z') ?? new \DateTimeImmutable($race['date']));

            $this->entityManager->persist($gp);
            $io->writeln(\sprintf('  Round %d — %s%s', $round, $gp->getName(), $gp->isHasSprint() ? ' (sprint)' : ''));
        }
        $this->entityManager->flush();

        return $races;
    }

    private function syncDrivers(int $year, SymfonyStyle $io): void
    {
        $data = $this->client->fetch("/{$year}/drivers.json");
        $drivers = $data['MRData']['DriverTable']['Drivers'] ?? [];

        foreach ($drivers as $d) {
            $code = $d['code'] ?? strtoupper(substr($d['familyName'] ?? $d['driverId'], 0, 3));
            // A stand-in a player added by hand to a Grand Prix lineup has no externalRef yet:
            // adopt him by code rather than creating a duplicate the results would point to.
            $driver = $this->entityManager->getRepository(Driver::class)->findOneBy(['externalRef' => $d['driverId']])
                ?? $this->entityManager->getRepository(Driver::class)->findOneBy(['externalRef' => null, 'code' => $code])
                ?? new Driver();

            $driver->setExternalRef($d['driverId']);
            $driver->setCode($code);
            $driver->setNumber(isset($d['permanentNumber']) ? (int) $d['permanentNumber'] : null);
            $driver->setFirstName($d['givenName'] ?? '');
            $driver->setLastName($d['familyName'] ?? $d['driverId']);
            $driver->setNationality($d['nationality'] ?? null);

            $this->entityManager->persist($driver);
        }
        $this->entityManager->flush();
        $io->writeln(\sprintf('  %d pilotes synchronisés.', \count($drivers)));
    }

    private function syncTeams(int $year, SymfonyStyle $io): void
    {
        $data = $this->client->fetch("/{$year}/constructors.json");
        $teams = $data['MRData']['ConstructorTable']['Constructors'] ?? [];

        foreach ($teams as $t) {
            $team = $this->entityManager->getRepository(Team::class)->findOneBy(['externalRef' => $t['constructorId']])
                ?? new Team();

            $team->setExternalRef($t['constructorId']);
            $team->setName($t['name']);
            $team->setNationality($t['nationality'] ?? null);

            $this->entityManager->persist($team);
        }
        $this->entityManager->flush();
        $io->writeln(\sprintf('  %d écuries synchronisées.', \count($teams)));
    }

    private function syncDriverStandings(Season $season, int $year, SymfonyStyle $io): void
    {
        $data = $this->client->fetch("/{$year}/driverStandings.json");
        $lists = $data['MRData']['StandingsTable']['StandingsLists'] ?? [];
        if ([] === $lists) {
            $io->writeln('  Pas encore de classement pilotes disponible pour cette saison.');

            return;
        }

        $now = new \DateTimeImmutable();
        foreach ($lists[0]['DriverStandings'] as $ds) {
            $driver = $this->entityManager->getRepository(Driver::class)->findOneBy(['externalRef' => $ds['Driver']['driverId']]);
            if (null === $driver) {
                continue;
            }

            $constructors = $ds['Constructors'] ?? [];
            $latestConstructor = $constructors[array_key_last($constructors)] ?? null;
            if (null !== $latestConstructor) {
                $team = $this->entityManager->getRepository(Team::class)->findOneBy(['externalRef' => $latestConstructor['constructorId']]);
                if (null !== $team) {
                    $entry = $this->entityManager->getRepository(SeasonEntry::class)->findOneBy(['season' => $season, 'driver' => $driver])
                        ?? new SeasonEntry();
                    $entry->setSeason($season);
                    $entry->setDriver($driver);
                    $entry->setTeam($team);
                    $entry->setRaceNumber($driver->getNumber());
                    $this->entityManager->persist($entry);
                }
            }

            $standing = $this->entityManager->getRepository(\App\Entity\DriverStanding::class)->findOneBy(['season' => $season, 'driver' => $driver])
                ?? new \App\Entity\DriverStanding();
            $standing->setSeason($season);
            $standing->setDriver($driver);
            $standing->setPosition((int) $ds['position']);
            $standing->setPoints((float) $ds['points']);
            $standing->setUpdatedAt($now);
            $this->entityManager->persist($standing);
        }
        $this->entityManager->flush();
        $io->writeln(\sprintf('  %d pilotes classés.', \count($lists[0]['DriverStandings'])));
    }

    private function syncConstructorStandings(Season $season, int $year, SymfonyStyle $io): void
    {
        $data = $this->client->fetch("/{$year}/constructorStandings.json");
        $lists = $data['MRData']['StandingsTable']['StandingsLists'] ?? [];
        if ([] === $lists) {
            $io->writeln('  Pas encore de classement constructeurs disponible pour cette saison.');

            return;
        }

        $now = new \DateTimeImmutable();
        foreach ($lists[0]['ConstructorStandings'] as $cs) {
            $team = $this->entityManager->getRepository(Team::class)->findOneBy(['externalRef' => $cs['Constructor']['constructorId']]);
            if (null === $team) {
                continue;
            }

            $standing = $this->entityManager->getRepository(\App\Entity\ConstructorStanding::class)->findOneBy(['season' => $season, 'team' => $team])
                ?? new \App\Entity\ConstructorStanding();
            $standing->setSeason($season);
            $standing->setTeam($team);
            $standing->setPosition((int) $cs['position']);
            $standing->setPoints((float) $cs['points']);
            $standing->setUpdatedAt($now);
            $this->entityManager->persist($standing);
        }
        $this->entityManager->flush();
        $io->writeln(\sprintf('  %d écuries classées.', \count($lists[0]['ConstructorStandings'])));
    }

    /**
     * @param array<string, mixed> $race the raw calendar entry for this round, from syncCalendar()
     */
    private function syncRound(Season $season, int $year, array $race, SymfonyStyle $io): void
    {
        $round = (int) $race['round'];
        $gp = $this->entityManager->getRepository(GrandPrix::class)->findOneBy(['season' => $season, 'round' => $round]);
        if (null === $gp) {
            return;
        }

        $resultCount = $this->syncResults($gp, $year, $round);
        $qualiCount = $this->syncQualifying($gp, $year, $round);
        $sprintCount = $gp->isHasSprint() ? $this->syncSprint($gp, $year, $round) : 0;

        $io->writeln(\sprintf(
            '  Round %d — %s : %d résultats course, %d qualifs%s',
            $round,
            $gp->getName(),
            $resultCount,
            $qualiCount,
            $gp->isHasSprint() ? \sprintf(', %d sprint', $sprintCount) : '',
        ));
    }

    private function syncResults(GrandPrix $gp, int $year, int $round): int
    {
        $data = $this->client->fetch("/{$year}/{$round}/results.json");
        $races = $data['MRData']['RaceTable']['Races'] ?? [];
        if ([] === $races) {
            return 0;
        }

        $count = 0;
        foreach ($races[0]['Results'] as $r) {
            $driver = $this->entityManager->getRepository(Driver::class)->findOneBy(['externalRef' => $r['Driver']['driverId']]);
            if (null === $driver) {
                continue;
            }

            $result = $this->entityManager->getRepository(RaceResult::class)->findOneBy(['grandPrix' => $gp, 'driver' => $driver])
                ?? new RaceResult();
            $result->setGrandPrix($gp);
            $result->setDriver($driver);
            $result->setPosition(is_numeric($r['position'] ?? null) ? (int) $r['position'] : null);
            $result->setStatus($this->mapStatus($r['status'] ?? 'Finished'));
            $result->setPoints(is_numeric($r['points'] ?? null) ? (float) $r['points'] : null);
            $this->entityManager->persist($result);
            ++$count;

            if ('1' === ($r['FastestLap']['rank'] ?? null)) {
                $fastestLap = $this->entityManager->getRepository(FastestLap::class)->findOneBy(['grandPrix' => $gp])
                    ?? new FastestLap();
                $fastestLap->setGrandPrix($gp);
                $fastestLap->setDriver($driver);
                $this->entityManager->persist($fastestLap);
            }
        }
        $this->entityManager->flush();

        return $count;
    }

    private function syncQualifying(GrandPrix $gp, int $year, int $round): int
    {
        $data = $this->client->fetch("/{$year}/{$round}/qualifying.json");
        $races = $data['MRData']['RaceTable']['Races'] ?? [];
        if ([] === $races) {
            return 0;
        }

        $count = 0;
        foreach ($races[0]['QualifyingResults'] as $q) {
            $driver = $this->entityManager->getRepository(Driver::class)->findOneBy(['externalRef' => $q['Driver']['driverId']]);
            if (null === $driver || !is_numeric($q['position'] ?? null)) {
                continue;
            }

            $result = $this->entityManager->getRepository(QualifyingResult::class)->findOneBy(['grandPrix' => $gp, 'driver' => $driver])
                ?? new QualifyingResult();
            $result->setGrandPrix($gp);
            $result->setDriver($driver);
            $result->setPosition((int) $q['position']);
            $this->entityManager->persist($result);
            ++$count;
        }
        $this->entityManager->flush();

        return $count;
    }

    private function syncSprint(GrandPrix $gp, int $year, int $round): int
    {
        $data = $this->client->fetch("/{$year}/{$round}/sprint.json");
        $races = $data['MRData']['RaceTable']['Races'] ?? [];
        if ([] === $races) {
            return 0;
        }

        $count = 0;
        foreach ($races[0]['SprintResults'] as $s) {
            $driver = $this->entityManager->getRepository(Driver::class)->findOneBy(['externalRef' => $s['Driver']['driverId']]);
            if (null === $driver) {
                continue;
            }

            $result = $this->entityManager->getRepository(SprintResult::class)->findOneBy(['grandPrix' => $gp, 'driver' => $driver])
                ?? new SprintResult();
            $result->setGrandPrix($gp);
            $result->setDriver($driver);
            $result->setPosition(is_numeric($s['position'] ?? null) ? (int) $s['position'] : null);
            $result->setStatus($this->mapStatus($s['status'] ?? 'Finished'));
            $this->entityManager->persist($result);
            ++$count;
        }
        $this->entityManager->flush();

        return $count;
    }

    private function mapStatus(string $raw): ResultStatus
    {
        $normalized = strtolower($raw);

        if (str_contains($normalized, 'disqualified')) {
            return ResultStatus::DSQ;
        }
        if (str_contains($normalized, 'did not start') || str_contains($normalized, 'withdrew')) {
            return ResultStatus::DNS;
        }
        if ('finished' === $normalized || str_starts_with($normalized, '+')) {
            return ResultStatus::FINISHED;
        }

        return ResultStatus::DNF;
    }

    private function combineDateTime(?string $date, ?string $time): ?\DateTimeImmutable
    {
        if (null === $date) {
            return null;
        }

        $time ??= '00:00:00Z';

        try {
            return new \DateTimeImmutable(\sprintf('%sT%s', $date, $time));
        } catch (\Exception) {
            return null;
        }
    }
}
