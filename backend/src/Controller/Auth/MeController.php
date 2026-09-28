<?php

namespace App\Controller\Auth;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class MeController
{
    public function __construct(private readonly Security $security)
    {
    }

    #[Route('/api/me', name: 'app_me', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        /** @var User $user */
        $user = $this->security->getUser();

        return new JsonResponse([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'displayName' => $user->getDisplayName(),
        ]);
    }
}
