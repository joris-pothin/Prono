<?php

namespace App\Controller\Prediction;

use App\Entity\Driver;
use App\Entity\Season;
use App\Entity\SeasonConstructorPick;
use App\Entity\SeasonDriverPick;
use App\Entity\SeasonPrediction;
use App\Entity\Team;
use App\Entity\User;
use App\Repository\SeasonEntryRepository;
use App\Repository\SeasonPredictionRepository;
use App\Service\Ranking\RankingValidationException;
use App\Service\Ranking\RankingValidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Writes the authenticated player's championship-long prediction for a season: a full
 * ranking of every driver and every constructor racing that season. Locked once FP1 of
 * round 1 has started (Season::getPredictionsDeadline()).
 *
 * Body shape:
 * {
 *   "driverRanks": [{"driverId": 1, "rank": 1}, ...],
 *   "constructorRanks": [{"teamId": 3, "rank": 1}, ...]
 * }
 */
final class SeasonPredictionController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly SeasonEntryRepository $seasonEntryRepository,
        private readonly SeasonPredictionRepository $seasonPredictionRepository,
        private readonly RankingValidator $rankingValidator,
        private readonly SerializerInterface $serializer,
    ) {
    }

    #[Route('/api/predictions/season/{seasonId}', name: 'app_prediction_season_put', requirements: ['seasonId' => '\d+'], methods: ['PUT'])]
    public function __invoke(int $seasonId, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->security->getUser();

        $season = $this->entityManager->getRepository(Season::class)->find($seasonId);
        if (null === $season) {
            return new JsonResponse(['error' => 'Saison introuvable.'], 404);
        }

        $deadline = $season->getPredictionsDeadline();
        if (null !== $deadline && new \DateTimeImmutable() >= $deadline) {
            return new JsonResponse(['error' => 'Les pronostics de classement général sont clos depuis le début de la saison.'], 403);
        }

        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload) || !isset($payload['driverRanks'], $payload['constructorRanks'])
            || !\is_array($payload['driverRanks']) || !\is_array($payload['constructorRanks'])) {
            return new JsonResponse(['error' => 'Corps de requête invalide : "driverRanks" et "constructorRanks" sont requis.'], 400);
        }

        [$rankByDriverId, $error] = $this->extractRankMap($payload['driverRanks'], 'driverId');
        if (null !== $error) {
            return new JsonResponse(['error' => $error], 400);
        }
        [$rankByTeamId, $error] = $this->extractRankMap($payload['constructorRanks'], 'teamId');
        if (null !== $error) {
            return new JsonResponse(['error' => $error], 400);
        }

        $entries = $this->seasonEntryRepository->findBy(['season' => $season]);
        $expectedDriverIds = array_values(array_unique(array_map(static fn ($e) => $e->getDriver()->getId(), $entries)));
        $expectedTeamIds = array_values(array_unique(array_map(static fn ($e) => $e->getTeam()->getId(), $entries)));

        try {
            $this->rankingValidator->validateFullPermutation($rankByDriverId, $expectedDriverIds);
            $this->rankingValidator->validateFullPermutation($rankByTeamId, $expectedTeamIds);
        } catch (RankingValidationException $e) {
            return new JsonResponse(['error' => 'Classement invalide.', 'details' => $e->getErrors()], 422);
        }

        $prediction = $this->seasonPredictionRepository->findOneBy(['user' => $user, 'season' => $season])
            ?? (new SeasonPrediction())->setUser($user)->setSeason($season);

        $prediction->clearDriverPicks();
        $prediction->clearConstructorPicks();
        $this->entityManager->flush(); // apply orphan removal of previous picks before re-adding

        foreach ($rankByDriverId as $driverId => $rank) {
            $pick = new SeasonDriverPick();
            $pick->setDriver($this->entityManager->getReference(Driver::class, $driverId));
            $pick->setPredictedRank($rank);
            $prediction->addDriverPick($pick);
        }

        foreach ($rankByTeamId as $teamId => $rank) {
            $pick = new SeasonConstructorPick();
            $pick->setTeam($this->entityManager->getReference(Team::class, $teamId));
            $pick->setPredictedRank($rank);
            $prediction->addConstructorPick($pick);
        }

        $prediction->touch();
        $this->entityManager->persist($prediction);
        $this->entityManager->flush();

        $json = $this->serializer->serialize($prediction, 'json', ['groups' => ['season_prediction:read', 'ref:read']]);

        return new JsonResponse($json, 200, [], true);
    }

    /**
     * @return array{0: array<int, int>, 1: ?string}
     */
    private function extractRankMap(array $items, string $idField): array
    {
        $map = [];
        foreach ($items as $item) {
            if (!\is_array($item) || !isset($item[$idField], $item['rank']) || !is_numeric($item[$idField]) || !is_numeric($item['rank'])) {
                return [[], \sprintf('Chaque entrée doit avoir "%s" et "rank" numériques.', $idField)];
            }
            $id = (int) $item[$idField];
            if (isset($map[$id])) {
                return [[], \sprintf('%s en double : %d.', $idField, $id)];
            }
            $map[$id] = (int) $item['rank'];
        }

        return [$map, null];
    }
}
