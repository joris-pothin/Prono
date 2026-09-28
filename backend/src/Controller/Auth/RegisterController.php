<?php

namespace App\Controller\Auth;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class RegisterController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UserRepository $userRepository,
        private readonly ValidatorInterface $validator,
        private readonly string $registrationInviteCode,
    ) {
    }

    #[Route('/api/register', name: 'app_register', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] RegisterPayload $payload): JsonResponse
    {
        if (!hash_equals($this->registrationInviteCode, $payload->inviteCode)) {
            return new JsonResponse(['error' => "Code d'invitation invalide."], 403);
        }

        $violations = $this->validator->validate($payload);
        if (\count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }

            return new JsonResponse(['error' => 'Données invalides.', 'details' => $errors], 422);
        }

        if (null !== $this->userRepository->findOneBy(['email' => $payload->email])) {
            return new JsonResponse(['error' => 'Un compte existe déjà avec cet email.'], 409);
        }

        if (null !== $this->userRepository->findOneBy(['displayName' => $payload->displayName])) {
            return new JsonResponse(['error' => 'Ce pseudo est déjà pris.'], 409);
        }

        $user = new User();
        $user->setEmail($payload->email);
        $user->setDisplayName($payload->displayName);
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($this->passwordHasher->hashPassword($user, $payload->password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return new JsonResponse([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'displayName' => $user->getDisplayName(),
        ], 201);
    }
}

final class RegisterPayload
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public string $email = '';

    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 50)]
    public string $displayName = '';

    #[Assert\NotBlank]
    #[Assert\Length(min: 8)]
    public string $password = '';

    #[Assert\NotBlank]
    public string $inviteCode = '';
}
