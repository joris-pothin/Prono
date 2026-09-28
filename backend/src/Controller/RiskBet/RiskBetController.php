<?php

namespace App\Controller\RiskBet;

use App\Entity\Enum\RiskBetStatus;
use App\Entity\RiskBet;
use App\Entity\RiskBetVote;
use App\Entity\Season;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * The "prono de risque" workflow: a player proposes a free-form bet, it goes live only
 * once every other player has unanimously approved it, and it's later resolved
 * won/lost, applying its point swing to the proposer's leaderboard total.
 */
final class RiskBetController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly SerializerInterface $serializer,
    ) {
    }

    #[Route('/api/risk-bets', name: 'app_risk_bet_propose', methods: ['POST'])]
    public function propose(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->security->getUser();

        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload)
            || !isset($payload['seasonId'], $payload['title'], $payload['description'], $payload['pointsIfWin'], $payload['pointsIfLose'])
            || !is_numeric($payload['seasonId']) || !is_numeric($payload['pointsIfWin']) || !is_numeric($payload['pointsIfLose'])
            || '' === trim((string) $payload['title']) || '' === trim((string) $payload['description'])) {
            return new JsonResponse(['error' => 'Corps de requête invalide : "seasonId", "title", "description", "pointsIfWin" et "pointsIfLose" sont requis.'], 400);
        }

        $season = $this->entityManager->getRepository(Season::class)->find((int) $payload['seasonId']);
        if (null === $season) {
            return new JsonResponse(['error' => 'Saison introuvable.'], 404);
        }

        $bet = new RiskBet();
        $bet->setSeason($season);
        $bet->setProposedBy($user);
        $bet->setTitle((string) $payload['title']);
        $bet->setDescription((string) $payload['description']);
        $bet->setPointsIfWin(max(0, (int) $payload['pointsIfWin']));
        $bet->setPointsIfLose(max(0, (int) $payload['pointsIfLose']));
        $bet->setStatus(RiskBetStatus::PENDING);

        $this->entityManager->persist($bet);
        $this->entityManager->flush();

        return $this->json($bet, 201);
    }

    #[Route('/api/risk-bets/{id}/vote', name: 'app_risk_bet_vote', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function vote(int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->security->getUser();

        $bet = $this->entityManager->getRepository(RiskBet::class)->find($id);
        if (null === $bet) {
            return new JsonResponse(['error' => 'Prono de risque introuvable.'], 404);
        }
        if (RiskBetStatus::PENDING !== $bet->getStatus()) {
            return new JsonResponse(['error' => 'Ce prono de risque n\'est plus en attente de vote.'], 403);
        }
        if ($bet->getProposedBy()->getId() === $user->getId()) {
            return new JsonResponse(['error' => 'Le proposant ne vote pas sur son propre prono de risque.'], 403);
        }

        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload) || !\array_key_exists('approved', $payload) || !\is_bool($payload['approved'])) {
            return new JsonResponse(['error' => 'Corps de requête invalide : "approved" (booléen) est requis.'], 400);
        }

        $vote = $this->entityManager->getRepository(RiskBetVote::class)->findOneBy(['riskBet' => $bet, 'user' => $user])
            ?? new RiskBetVote();
        $vote->setRiskBet($bet)->setUser($user)->setApproved((bool) $payload['approved']);
        $bet->addVote($vote);
        $this->entityManager->persist($vote);

        if (!$payload['approved']) {
            $bet->setStatus(RiskBetStatus::REJECTED);
        } else {
            $otherPlayerCount = $this->entityManager->getRepository(User::class)->count([]) - 1;
            $approvedVotes = 0;
            foreach ($bet->getVotes() as $v) {
                if ($v->isApproved()) {
                    ++$approvedVotes;
                }
            }
            if ($otherPlayerCount > 0 && $approvedVotes >= $otherPlayerCount) {
                $bet->setStatus(RiskBetStatus::APPROVED);
            }
        }

        $this->entityManager->flush();

        return $this->json($bet, 200);
    }

    #[Route('/api/risk-bets/{id}/resolve', name: 'app_risk_bet_resolve', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function resolve(int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->security->getUser();

        $bet = $this->entityManager->getRepository(RiskBet::class)->find($id);
        if (null === $bet) {
            return new JsonResponse(['error' => 'Prono de risque introuvable.'], 404);
        }
        if (RiskBetStatus::APPROVED !== $bet->getStatus()) {
            return new JsonResponse(['error' => 'Seul un prono de risque approuvé peut être résolu.'], 403);
        }
        if ($bet->getProposedBy()->getId() === $user->getId()) {
            return new JsonResponse(['error' => 'Le proposant ne résout pas lui-même son propre prono de risque.'], 403);
        }

        $payload = json_decode($request->getContent(), true);
        $result = \is_array($payload) ? ($payload['result'] ?? null) : null;
        if (!\in_array($result, ['win', 'lose'], true)) {
            return new JsonResponse(['error' => 'Corps de requête invalide : "result" doit valoir "win" ou "lose".'], 400);
        }

        $bet->setStatus('win' === $result ? RiskBetStatus::RESOLVED_WIN : RiskBetStatus::RESOLVED_LOSE);
        $bet->setResolvedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $this->json($bet, 200);
    }

    #[Route('/api/risk-bets/{id}/cancel', name: 'app_risk_bet_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancel(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->security->getUser();

        $bet = $this->entityManager->getRepository(RiskBet::class)->find($id);
        if (null === $bet) {
            return new JsonResponse(['error' => 'Prono de risque introuvable.'], 404);
        }
        if ($bet->getProposedBy()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Seul le proposant peut annuler ce prono de risque.'], 403);
        }
        if (!\in_array($bet->getStatus(), [RiskBetStatus::PENDING, RiskBetStatus::APPROVED], true)) {
            return new JsonResponse(['error' => 'Ce prono de risque ne peut plus être annulé.'], 403);
        }

        $bet->setStatus(RiskBetStatus::CANCELLED);
        $this->entityManager->flush();

        return $this->json($bet, 200);
    }

    private function json(RiskBet $bet, int $status): JsonResponse
    {
        $json = $this->serializer->serialize($bet, 'json', ['groups' => ['risk_bet:read', 'ref:read']]);

        return new JsonResponse($json, $status, [], true);
    }
}
