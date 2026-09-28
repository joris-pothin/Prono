<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\SeasonPredictionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * A player's championship-long prediction for one season: a full ranking of drivers
 * and of constructors. Read-only through the API — written exclusively through
 * App\Controller\Prediction\SeasonPredictionController, which enforces the
 * before-FP1-of-round-1 deadline and validates the ranking.
 */
#[ORM\Entity(repositoryClass: SeasonPredictionRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_SEASON_PREDICTION_USER_SEASON', fields: ['user', 'season'])]
#[ApiResource(
    operations: [new GetCollection(), new Get()],
    normalizationContext: ['groups' => ['season_prediction:read', 'ref:read']],
    security: 'is_granted("ROLE_USER")',
)]
#[ApiFilter(SearchFilter::class, properties: ['season' => 'exact', 'user' => 'exact'])]
class SeasonPrediction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['season_prediction:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['season_prediction:read'])]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Season::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['season_prediction:read'])]
    private ?Season $season = null;

    /**
     * @var Collection<int, SeasonDriverPick>
     */
    #[ORM\OneToMany(targetEntity: SeasonDriverPick::class, mappedBy: 'seasonPrediction', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['predictedRank' => 'ASC'])]
    #[Groups(['season_prediction:read'])]
    private Collection $driverPicks;

    /**
     * @var Collection<int, SeasonConstructorPick>
     */
    #[ORM\OneToMany(targetEntity: SeasonConstructorPick::class, mappedBy: 'seasonPrediction', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['predictedRank' => 'ASC'])]
    #[Groups(['season_prediction:read'])]
    private Collection $constructorPicks;

    #[ORM\Column]
    #[Groups(['season_prediction:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->driverPicks = new ArrayCollection();
        $this->constructorPicks = new ArrayCollection();
        $this->updatedAt = new \DateTimeImmutable();
    }

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

    /**
     * @return Collection<int, SeasonDriverPick>
     */
    public function getDriverPicks(): Collection
    {
        return $this->driverPicks;
    }

    public function addDriverPick(SeasonDriverPick $pick): static
    {
        if (!$this->driverPicks->contains($pick)) {
            $this->driverPicks->add($pick);
            $pick->setSeasonPrediction($this);
        }

        return $this;
    }

    public function clearDriverPicks(): static
    {
        $this->driverPicks->clear();

        return $this;
    }

    /**
     * @return Collection<int, SeasonConstructorPick>
     */
    public function getConstructorPicks(): Collection
    {
        return $this->constructorPicks;
    }

    public function addConstructorPick(SeasonConstructorPick $pick): static
    {
        if (!$this->constructorPicks->contains($pick)) {
            $this->constructorPicks->add($pick);
            $pick->setSeasonPrediction($this);
        }

        return $this;
    }

    public function clearConstructorPicks(): static
    {
        $this->constructorPicks->clear();

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): static
    {
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
}
