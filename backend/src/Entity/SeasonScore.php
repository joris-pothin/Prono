<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\SeasonScoreRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * A player's score for the championship-long prediction, computed by
 * App\Service\Scoring\SeasonScoringCalculator against the final standings.
 */
#[ORM\Entity(repositoryClass: SeasonScoreRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_SEASON_SCORE_USER_SEASON', fields: ['user', 'season'])]
#[ApiResource(
    operations: [new GetCollection(), new Get()],
    normalizationContext: ['groups' => ['score:read', 'ref:read']],
    security: 'is_granted("ROLE_USER")',
)]
#[ApiFilter(SearchFilter::class, properties: ['season' => 'exact', 'user' => 'exact'])]
class SeasonScore
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

    #[ORM\ManyToOne(targetEntity: Season::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['score:read'])]
    private ?Season $season = null;

    #[ORM\Column]
    #[Groups(['score:read'])]
    private int $driversPoints = 0;

    #[ORM\Column]
    #[Groups(['score:read'])]
    private int $constructorsPoints = 0;

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

    public function getSeason(): ?Season
    {
        return $this->season;
    }

    public function setSeason(?Season $season): static
    {
        $this->season = $season;

        return $this;
    }

    public function getDriversPoints(): int
    {
        return $this->driversPoints;
    }

    public function getConstructorsPoints(): int
    {
        return $this->constructorsPoints;
    }

    public function getTotalPoints(): int
    {
        return $this->totalPoints;
    }

    public function setBreakdown(int $driversPoints, int $constructorsPoints): static
    {
        $this->driversPoints = $driversPoints;
        $this->constructorsPoints = $constructorsPoints;
        $this->totalPoints = $driversPoints + $constructorsPoints;
        $this->computedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getComputedAt(): ?\DateTimeImmutable
    {
        return $this->computedAt;
    }
}
