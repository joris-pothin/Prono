<?php

namespace App\Controller\GrandPrix;

use App\Entity\Driver;
use App\Entity\GrandPrix;
use App\Entity\Team;
use App\Entity\User;
use App\Service\Lineup\GrandPrixLineup;
use App\Service\Lineup\LineupException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Reads and edits the lineup of a single Grand Prix (who's racing this weekend). Any player
 * can change it, until predictions lock at FP1 — the change applies to everyone's prediction.
 *
 * Every endpoint answers with the resulting lineup:
 * {
 *   "entries": [{"driver": {...}, "team": {...}, "added": false}, ...],  // drivers to rank
 *   "removed": [{"driver": {...}, "team": {...}}, ...],                  // season drivers sitting it out
 *   "teams":   [{...}, ...]                                              // the season's teams
 * }
 */
final class GrandPrixLineupController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly GrandPrixLineup $lineup,
        private readonly SerializerInterface $serializer,
    ) {
    }

    #[Route('/api/grand-prix/{grandPrixId}/lineup', name: 'app_grand_prix_lineup_get', requirements: ['grandPrixId' => '\d+'], methods: ['GET'])]
    public function show(int $grandPrixId): JsonResponse
    {
        $gp = $this->entityManager->getRepository(GrandPrix::class)->find($grandPrixId);
        if (null === $gp) {
            return new JsonResponse(['error' => 'Grand Prix introuvable.'], 404);
        }

        return $this->json($gp);
    }

    /**
     * Body: {"driverId": 12, "teamId": 3}, or {"newDriver": {"firstName", "lastName", "code", "number"?}, "teamId": 3}
     * to create a driver not known yet. "teamId" can be left out when bringing back a season driver.
     */
    #[Route('/api/grand-prix/{grandPrixId}/lineup', name: 'app_grand_prix_lineup_add', requirements: ['grandPrixId' => '\d+'], methods: ['POST'])]
    public function add(int $grandPrixId, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->security->getUser();

        $gp = $this->entityManager->getRepository(GrandPrix::class)->find($grandPrixId);
        if (null === $gp) {
            return new JsonResponse(['error' => 'Grand Prix introuvable.'], 404);
        }
        if ($gp->isLocked()) {
            return new JsonResponse(['error' => 'Le plateau ne peut plus être modifié depuis le début des EL1.'], 403);
        }

        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload)) {
            return new JsonResponse(['error' => 'Corps de requête invalide.'], 400);
        }

        $team = null;
        if (isset($payload['teamId'])) {
            $team = is_numeric($payload['teamId']) ? $this->entityManager->getRepository(Team::class)->find((int) $payload['teamId']) : null;
            if (null === $team) {
                return new JsonResponse(['error' => 'Écurie introuvable.'], 404);
            }
        }

        if (isset($payload['newDriver'])) {
            [$driver, $error] = $this->buildNewDriver($payload['newDriver']);
            if (null !== $error) {
                return new JsonResponse(['error' => $error], 400);
            }
            if (null === $team) {
                return new JsonResponse(['error' => 'Choisis l’écurie pour laquelle ce pilote court ce week-end.'], 400);
            }
            $this->entityManager->persist($driver);
        } elseif (isset($payload['driverId']) && is_numeric($payload['driverId'])) {
            $driver = $this->entityManager->getRepository(Driver::class)->find((int) $payload['driverId']);
            if (null === $driver) {
                return new JsonResponse(['error' => 'Pilote introuvable.'], 404);
            }
        } else {
            return new JsonResponse(['error' => 'Corps de requête invalide : "driverId" ou "newDriver" est requis.'], 400);
        }

        try {
            $this->lineup->addDriver($gp, $driver, $team, $user);
        } catch (LineupException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        }

        return $this->json($gp);
    }

    #[Route('/api/grand-prix/{grandPrixId}/lineup/{driverId}', name: 'app_grand_prix_lineup_remove', requirements: ['grandPrixId' => '\d+', 'driverId' => '\d+'], methods: ['DELETE'])]
    public function remove(int $grandPrixId, int $driverId): JsonResponse
    {
        /** @var User $user */
        $user = $this->security->getUser();

        $gp = $this->entityManager->getRepository(GrandPrix::class)->find($grandPrixId);
        if (null === $gp) {
            return new JsonResponse(['error' => 'Grand Prix introuvable.'], 404);
        }
        if ($gp->isLocked()) {
            return new JsonResponse(['error' => 'Le plateau ne peut plus être modifié depuis le début des EL1.'], 403);
        }

        $driver = $this->entityManager->getRepository(Driver::class)->find($driverId);
        if (null === $driver) {
            return new JsonResponse(['error' => 'Pilote introuvable.'], 404);
        }

        try {
            $this->lineup->removeDriver($gp, $driver, $user);
        } catch (LineupException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        }

        return $this->json($gp);
    }

    /**
     * @return array{0: ?Driver, 1: ?string}
     */
    private function buildNewDriver(mixed $data): array
    {
        if (!\is_array($data)) {
            return [null, '"newDriver" doit être un objet.'];
        }

        $firstName = trim((string) ($data['firstName'] ?? ''));
        $lastName = trim((string) ($data['lastName'] ?? ''));
        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        if ('' === $firstName || '' === $lastName) {
            return [null, 'Le prénom et le nom du pilote sont requis.'];
        }
        if (!preg_match('/^[A-Z]{3}$/', $code)) {
            return [null, 'Le code pilote doit faire 3 lettres (ex : LIN).'];
        }

        $number = $data['number'] ?? null;
        if (null !== $number && '' !== $number && (!is_numeric($number) || (int) $number < 1 || (int) $number > 99)) {
            return [null, 'Le numéro du pilote doit être compris entre 1 et 99.'];
        }

        // No externalRef: the F1 data sync adopts this driver by its code once the provider knows him.
        $driver = (new Driver())
            ->setFirstName(mb_substr($firstName, 0, 100))
            ->setLastName(mb_substr($lastName, 0, 100))
            ->setCode($code)
            ->setNumber(null === $number || '' === $number ? null : (int) $number);

        return [$driver, null];
    }

    private function json(GrandPrix $gp): JsonResponse
    {
        $teams = [];
        foreach ($gp->getSeason()->getEntries() as $entry) {
            $teams[$entry->getTeam()->getId()] = $entry->getTeam();
        }
        usort($teams, static fn (Team $a, Team $b) => strcmp($a->getName(), $b->getName()));

        $json = $this->serializer->serialize([
            'entries' => $this->lineup->entries($gp),
            'removed' => $this->lineup->removedEntries($gp),
            'teams' => $teams,
        ], 'json', ['groups' => ['ref:read']]);

        return new JsonResponse($json, 200, [], true);
    }
}
