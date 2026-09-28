<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\SeasonEntryRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Which team a driver races for during a given season. Kept separate from Driver/Team
 * because drivers change teams season to season (and occasionally mid-season, which
 * this simplified model does not track per-race).
 */
#[ORM\Entity(repositoryClass: SeasonEntryRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_SEASON_DRIVER', fields: ['season', 'driver'])]
#[ApiResource(
    operations: [new GetCollection(), new Get()],
    normalizationContext: ['groups' => ['season_entry:read', 'ref:read']],
)]
class SeasonEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['season_entry:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Season::class, inversedBy: 'entries')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Season $season = null;

    #[ORM\ManyToOne(targetEntity: Driver::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['season_entry:read'])]
    private ?Driver $driver = null;

    #[ORM\ManyToOne(targetEntity: Team::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['season_entry:read'])]
    private ?Team $team = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['season_entry:read'])]
    private ?int $raceNumber = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getDriver(): ?Driver
    {
        return $this->driver;
    }

    public function setDriver(?Driver $driver): static
    {
        $this->driver = $driver;

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

    public function getRaceNumber(): ?int
    {
        return $this->raceNumber;
    }

    public function setRaceNumber(?int $raceNumber): static
    {
        $this->raceNumber = $raceNumber;

        return $this;
    }
}
