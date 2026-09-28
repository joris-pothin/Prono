<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\GpScoreRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * A player's score for a single Grand Prix's prediction, computed by
 * App\Service\Scoring\GpScoringCalculator once results are in.
 */
#[ORM\Entity(repositoryClass: GpScoreRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_GP_SCORE_USER_GP', fields: ['user', 'grandPrix'])]
#[ApiResource(
    operations: [new GetCollection(), new Get()],
    normalizationContext: ['groups' => ['score:read', 'ref:read']],
    security: 'is_granted("ROLE_USER")',
)]
#[ApiFilter(SearchFilter::class, properties: ['grandPrix' => 'exact', 'user' => 'exact'])]
class GpScore
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['score:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['score:read'])]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: GrandPrix::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['score:read'])]
    private ?GrandPrix $grandPrix = null;

    #[ORM\Column]
    #[Groups(['score:read'])]
    private int $driversBracketPoints = 0;

    #[ORM\Column]
    #[Groups(['score:read'])]
    private int $polePoints = 0;

    #[ORM\Column]
    #[Groups(['score:read'])]
    private int $fastestLapPoints = 0;

    #[ORM\Column]
    #[Groups(['score:read'])]
    private int $sprintPoints = 0;

    #[ORM\Column]
    #[Groups(['score:read'])]
    private int $totalPoints = 0;

    #[ORM\Column]
    private ?\DateTimeImmutable $computedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getGrandPrix(): ?GrandPrix
    {
        return $this->grandPrix;
    }

    public function setGrandPrix(?GrandPrix $grandPrix): static
    {
        $this->grandPrix = $grandPrix;

        return $this;
    }

    public function getDriversBracketPoints(): int
    {
        return $this->driversBracketPoints;
    }

    public function getPolePoints(): int
    {
        return $this->polePoints;
    }

    public function getFastestLapPoints(): int
    {
        return $this->fastestLapPoints;
    }

    public function getSprintPoints(): int
    {
        return $this->sprintPoints;
    }

    public function getTotalPoints(): int
    {
        return $this->totalPoints;
    }

    public function setBreakdown(int $driversBracketPoints, int $polePoints, int $fastestLapPoints, int $sprintPoints): static
    {
        $this->driversBracketPoints = $driversBracketPoints;
        $this->polePoints = $polePoints;
        $this->fastestLapPoints = $fastestLapPoints;
        $this->sprintPoints = $sprintPoints;
        $this->totalPoints = $driversBracketPoints + $polePoints + $fastestLapPoints + $sprintPoints;
        $this->computedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getComputedAt(): ?\DateTimeImmutable
    {
        return $this->computedAt;
    }
}
