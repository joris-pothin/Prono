<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\QualifyingResultRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: QualifyingResultRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_QUALI_RESULT_GP_DRIVER', fields: ['grandPrix', 'driver'])]
#[ApiResource(
    operations: [new GetCollection(), new Get()],
    normalizationContext: ['groups' => ['result:read']],
    order: ['position' => 'ASC'],
)]
#[ApiFilter(SearchFilter::class, properties: ['grandPrix' => 'exact'])]
class QualifyingResult
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['result:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: GrandPrix::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['result:read'])]
    private ?GrandPrix $grandPrix = null;

    #[ORM\ManyToOne(targetEntity: Driver::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['result:read'])]
    private ?Driver $driver = null;

    #[ORM\Column]
    #[Groups(['result:read'])]
    private ?int $position = null;

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

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    /**
     * Convenience accessor used by the scoring engine to find who took pole (P1).
     */
    public function isPole(): bool
    {
        return 1 === $this->position;
    }
}
