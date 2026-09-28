<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\GpPredictionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * A player's prediction for a single Grand Prix weekend: race classification, pole,
 * fastest lap, and (if applicable) sprint podium. Read-only through the API — written
 * exclusively through App\Controller\Prediction\GpPredictionController, which enforces
 * the before-FP1 deadline.
 */
#[ORM\Entity(repositoryClass: GpPredictionRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_GP_PREDICTION_USER_GP', fields: ['user', 'grandPrix'])]
#[ApiResource(
    operations: [new GetCollection(), new Get()],
    normalizationContext: ['groups' => ['gp_prediction:read', 'ref:read']],
    security: 'is_granted("ROLE_USER")',
)]
#[ApiFilter(SearchFilter::class, properties: ['grandPrix' => 'exact', 'user' => 'exact'])]
class GpPrediction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['gp_prediction:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['gp_prediction:read'])]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: GrandPrix::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['gp_prediction:read'])]
    private ?GrandPrix $grandPrix = null;

    /**
     * @var Collection<int, GpDriverPick>
     */
    #[ORM\OneToMany(targetEntity: GpDriverPick::class, mappedBy: 'gpPrediction', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['predictedRank' => 'ASC'])]
    #[Groups(['gp_prediction:read'])]
    private Collection $driverPicks;

    #[ORM\ManyToOne(targetEntity: Driver::class)]
    #[Groups(['gp_prediction:read'])]
    private ?Driver $poleDriver = null;

    #[ORM\ManyToOne(targetEntity: Driver::class)]
    #[Groups(['gp_prediction:read'])]
    private ?Driver $fastestLapDriver = null;

    #[ORM\ManyToOne(targetEntity: Driver::class)]
    #[Groups(['gp_prediction:read'])]
    private ?Driver $sprintP1 = null;

    #[ORM\ManyToOne(targetEntity: Driver::class)]
    #[Groups(['gp_prediction:read'])]
    private ?Driver $sprintP2 = null;

    #[ORM\ManyToOne(targetEntity: Driver::class)]
    #[Groups(['gp_prediction:read'])]
    private ?Driver $sprintP3 = null;

    #[ORM\Column]
    #[Groups(['gp_prediction:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->driverPicks = new ArrayCollection();
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

    public function getGrandPrix(): ?GrandPrix
    {
        return $this->grandPrix;
    }

    public function setGrandPrix(?GrandPrix $grandPrix): static
    {
        $this->grandPrix = $grandPrix;

        return $this;
    }

    /**
     * @return Collection<int, GpDriverPick>
     */
    public function getDriverPicks(): Collection
    {
        return $this->driverPicks;
    }

    public function addDriverPick(GpDriverPick $pick): static
    {
        if (!$this->driverPicks->contains($pick)) {
            $this->driverPicks->add($pick);
            $pick->setGpPrediction($this);
        }

        return $this;
    }

    public function clearDriverPicks(): static
    {
        $this->driverPicks->clear();

        return $this;
    }

    public function getPoleDriver(): ?Driver
    {
        return $this->poleDriver;
    }

    public function setPoleDriver(?Driver $poleDriver): static
    {
        $this->poleDriver = $poleDriver;

        return $this;
    }

    public function getFastestLapDriver(): ?Driver
    {
        return $this->fastestLapDriver;
    }

    public function setFastestLapDriver(?Driver $fastestLapDriver): static
    {
        $this->fastestLapDriver = $fastestLapDriver;

        return $this;
    }

    public function getSprintP1(): ?Driver
    {
        return $this->sprintP1;
    }

    public function setSprintP1(?Driver $sprintP1): static
    {
        $this->sprintP1 = $sprintP1;

        return $this;
    }

    public function getSprintP2(): ?Driver
    {
        return $this->sprintP2;
    }

    public function setSprintP2(?Driver $sprintP2): static
    {
        $this->sprintP2 = $sprintP2;

        return $this;
    }

    public function getSprintP3(): ?Driver
    {
        return $this->sprintP3;
    }

    public function setSprintP3(?Driver $sprintP3): static
    {
        $this->sprintP3 = $sprintP3;

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
