<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\DriverStandingRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Current (or final, once the season is over) drivers' championship standing.
 * Refreshed by the F1 data sync after every round — see App\Service\F1Data.
 */
#[ORM\Entity(repositoryClass: DriverStandingRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_DRIVER_STANDING_SEASON_DRIVER', fields: ['season', 'driver'])]
#[ApiResource(
    operations: [new GetCollection(), new Get()],
    normalizationContext: ['groups' => ['standing:read']],
    order: ['position' => 'ASC'],
)]
#[ApiFilter(SearchFilter::class, properties: ['season' => 'exact'])]
class DriverStanding
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['standing:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Season::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Season $season = null;

    #[ORM\ManyToOne(targetEntity: Driver::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['standing:read'])]
    private ?Driver $driver = null;

    #[ORM\Column]
    #[Groups(['standing:read'])]
    private ?int $position = null;

    #[ORM\Column]
    #[Groups(['standing:read'])]
    private float $points = 0.0;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

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

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getPoints(): float
    {
        return $this->points;
    }

    public function setPoints(float $points): static
    {
        $this->points = $points;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
