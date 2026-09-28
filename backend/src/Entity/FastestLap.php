<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\FastestLapRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * The fastest race lap of a Grand Prix (one per race).
 */
#[ORM\Entity(repositoryClass: FastestLapRepository::class)]
#[ApiResource(
    operations: [new GetCollection(), new Get()],
    normalizationContext: ['groups' => ['result:read']],
)]
class FastestLap
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['result:read'])]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: GrandPrix::class)]
    #[ORM\JoinColumn(nullable: false, unique: true)]
    #[Groups(['result:read'])]
    private ?GrandPrix $grandPrix = null;

    #[ORM\ManyToOne(targetEntity: Driver::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['result:read'])]
    private ?Driver $driver = null;

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
}
