<?php

namespace App\Controller\Prediction;

use App\Entity\Driver;
use App\Entity\GpDriverPick;
use App\Entity\GpPrediction;
use App\Entity\GrandPrix;
use App\Entity\User;
use App\Repository\GpPredictionRepository;
use App\Repository\SeasonEntryRepository;
use App\Service\Ranking\RankingValidationException;
use App\Service\Ranking\RankingValidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Writes the authenticated player's prediction for a single Grand Prix weekend: the
 * full race classification, pole, fastest lap, and (if applicable) sprint podium.
 * Locked once FP1 has started (GrandPrix::isLocked()).
 *
 * Body shape:
 * {
 *   "driverRanks": [{"driverId": 1, "rank": 1}, ...],
 *   "poleDriverId": 1,
 *   "fastestLapDriverId": 1,
 *   "sprintP1Id": 1, "sprintP2Id": 2, "sprintP3Id": 3   // required only if the GP has a sprint
 * }
 */
final class GpPredictionController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly SeasonEntryRepository $seasonEntryRepository,
        private readonly GpPredictionRepository $gpPredictionRepository,
        private readonly RankingValidator $rankingValidator,
        private readonly SerializerInterface $serializer,
    ) {
    }

    #[Route('/api/predictions/gp/{grandPrixId}', name: 'app_prediction_gp_put', requirements: ['grandPrixId' => '\d+'], methods: ['PUT'])]
    public function __invoke(int $grandPrixId, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->security->getUser();

        $gp = $this->entityManager->getRepository(GrandPrix::class)->find($grandPrixId);
        if (null === $gp) {
            return new JsonResponse(['error' => 'Grand Prix introuvable.'], 404);
        }

        if ($gp->isLocked()) {
            return new JsonResponse(['error' => 'Les pronostics pour ce Grand Prix sont clos depuis le début des EL1.'], 403);
        }

        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload) || !isset($payload['driverRanks']) || !\is_array($payload['driverRanks'])
            || !isset($payload['poleDriverId'], $payload['fastestLapDriverId'])) {
            return new JsonResponse(['error' => 'Corps de requête invalide : "driverRanks", "poleDriverId" et "fastestLapDriverId" sont requis.'], 400);
        }

        [$rankByDriverId, $error] = $this->extractRankMap($payload['driverRanks']);
        if (null !== $error) {
            return new JsonResponse(['error' => $error], 400);
        }

        $entries = $this->seasonEntryRepository->findBy(['season' => $gp->getSeason()]);
        $expectedDriverIds = array_values(array_unique(array_map(static fn ($e) => $e->getDriver()->getId(), $entries)));

        try {
            $this->rankingValidator->validateFullPermutation($rankByDriverId, $expectedDriverIds);
        } catch (RankingValidationException $e) {
            return new JsonResponse(['error' => 'Classement invalide.', 'details' => $e->getErrors()], 422);
        }

        $poleDriverId = (int) $payload['poleDriverId'];
        $fastestLapDriverId = (int) $payload['fastestLapDriverId'];
        if (!\in_array($poleDriverId, $expectedDriverIds, true) || !\in_array($fastestLapDriverId, $expectedDriverIds, true)) {
            return new JsonResponse(['error' => 'poleDriverId et fastestLapDriverId doivent être des pilotes de la saison.'], 422);
        }

        $sprintIds = [];
        if ($gp->isHasSprint()) {
            foreach (['sprintP1Id', 'sprintP2Id', 'sprintP3Id'] as $field) {
                if (!isset($payload[$field]) || !is_numeric($payload[$field])) {
                    return new JsonResponse(['error' => \sprintf('"%s" est requis pour ce week-end sprint.', $field)], 400);
                }
                $id = (int) $payload[$field];
                if (!\in_array($id, $expectedDriverIds, true)) {
                    return new JsonResponse(['error' => \sprintf('"%s" doit être un pilote de la saison.', $field)], 422);
                }
                $sprintIds[$field] = $id;
            }
            if (\count(array_unique($sprintIds)) !== 3) {
                return new JsonResponse(['error' => 'Le podium sprint doit désigner trois pilotes différents.'], 422);
            }
        }

        $prediction = $this->gpPredictionRepository->findOneBy(['user' => $user, 'grandPrix' => $gp])
            ?? (new GpPrediction())->setUser($user)->setGrandPrix($gp);

        $prediction->clearDriverPicks();
        $this->entityManager->flush(); // apply orphan removal of previous picks before re-adding

        foreach ($rankByDriverId as $driverId => $rank) {
            $pick = new GpDriverPick();
            $pick->setDriver($this->entityManager->getReference(Driver::class, $driverId));
            $pick->setPredictedRank($rank);
            $prediction->addDriverPick($pick);
        }

        $prediction->setPoleDriver($this->entityManager->getReference(Driver::class, $poleDriverId));
        $prediction->setFastestLapDriver($this->entityManager->getReference(Driver::class, $fastestLapDriverId));

        if ($gp->isHasSprint()) {
            $prediction->setSprintP1($this->entityManager->getReference(Driver::class, $sprintIds['sprintP1Id']));
            $prediction->setSprintP2($this->entityManager->getReference(Driver::class, $sprintIds['sprintP2Id']));
            $prediction->setSprintP3($this->entityManager->getReference(Driver::class, $sprintIds['sprintP3Id']));
        } else {
            $prediction->setSprintP1(null);
            $prediction->setSprintP2(null);
            $prediction->setSprintP3(null);
        }

        $prediction->touch();
        $this->entityManager->persist($prediction);
        $this->entityManager->flush();

        $json = $this->serializer->serialize($prediction, 'json', ['groups' => ['gp_prediction:read', 'ref:read']]);

        return new JsonResponse($json, 200, [], true);
    }

    /**
     * @return array{0: array<int, int>, 1: ?string}
     */
    private function extractRankMap(array $items): array
    {
        $map = [];
        foreach ($items as $item) {
            if (!\is_array($item) || !isset($item['driverId'], $item['rank']) || !is_numeric($item['driverId']) || !is_numeric($item['rank'])) {
                return [[], 'Chaque entrée doit avoir "driverId" et "rank" numériques.'];
            }
            $id = (int) $item['driverId'];
            if (isset($map[$id])) {
                return [[], \sprintf('driverId en double : %d.', $id)];
            }
            $map[$id] = (int) $item['rank'];
        }

        return [$map, null];
    }
}
