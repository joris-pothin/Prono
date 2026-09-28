<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\Enum\ResultStatus;
use App\Repository\SprintResultRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: SprintResultRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_SPRINT_RESULT_GP_DRIVER', fields: ['grandPrix', 'driver'])]
#[ApiResource(
    operations: [new GetCollection(), new Get()],
    normalizationContext: ['groups' => ['result:read']],
    order: ['position' => 'ASC'],
)]
#[ApiFilter(SearchFilter::class, properties: ['grandPrix' => 'exact'])]
class SprintResult
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

    #[ORM\Column(nullable: true)]
    #[Groups(['result:read'])]
    private ?int $position = null;

    #[ORM\Column(length: 20, enumType: ResultStatus::class)]
    #[Groups(['result:read'])]
    private ResultStatus $status = ResultStatus::FINISHED;

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

    public function setPosition(?int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getStatus(): ResultStatus
    {
        return $this->status;
    }

    public function setStatus(ResultStatus $status): static
    {
        $this->status = $status;

        return $this;
    }
}
