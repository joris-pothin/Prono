<?php

namespace App\Entity;

use App\Entity\Enum\LineupChangeType;
use App\Repository\GrandPrixLineupChangeRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A one-off deviation from the season lineup for a single Grand Prix: a season driver
 * sitting this weekend out (injury, withdrawal), or a stand-in/reserve driver racing it.
 * The effective lineup of a GP is the season entries, minus REMOVED drivers, plus ADDED ones
 * (see App\Service\Lineup\GrandPrixLineup) — storing only the overrides keeps it following
 * season-level changes coming from the F1 data sync.
 */
#[ORM\Entity(repositoryClass: GrandPrixLineupChangeRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_GPLC_GRAND_PRIX_DRIVER', fields: ['grandPrix', 'driver'])]
class GrandPrixLineupChange
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: GrandPrix::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?GrandPrix $grandPrix = null;

    #[ORM\ManyToOne(targetEntity: Driver::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Driver $driver = null;

    #[ORM\Column(length: 10, enumType: LineupChangeType::class)]
    private ?LineupChangeType $type = null;

    /**
     * Team the added driver races for this weekend; null for REMOVED changes.
     */
    #[ORM\ManyToOne(targetEntity: Team::class)]
    private ?Team $team = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getDriver(): ?Driver
    {
        return $this->driver;
    }

    public function setDriver(?Driver $driver): static
    {
        $this->driver = $driver;

        return $this;
    }

    public function getType(): ?LineupChangeType
    {
        return $this->type;
    }

    public function setType(LineupChangeType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getTeam(): ?Team
    {
        return $this->team;
    }

    public function setTeam(?Team $team): static
    {
        $this->team = $team;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
