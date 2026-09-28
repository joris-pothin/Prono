<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\TeamRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: TeamRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_TEAM_EXTERNAL_REF', fields: ['externalRef'])]
#[ApiResource(
    operations: [new GetCollection(), new Get()],
    normalizationContext: ['groups' => ['team:read']],
    order: ['name' => 'ASC'],
)]
class Team
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['team:read', 'result:read', 'standing:read', 'ref:read'])]
    private ?int $id = null;

    /**
     * Identifier of this team in the external F1 data provider (e.g. jolpica-f1 "constructorId").
     */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $externalRef = null;

    #[ORM\Column(length: 100)]
    #[Groups(['team:read', 'result:read', 'standing:read', 'ref:read'])]
    private ?string $name = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['team:read'])]
    private ?string $nationality = null;

    /**
     * Hex color used for UI accents (e.g. "#3671C6").
     */
    #[ORM\Column(length: 7, nullable: true)]
    #[Groups(['team:read', 'ref:read'])]
    private ?string $colorHex = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getExternalRef(): ?string
    {
        return $this->externalRef;
    }

    public function setExternalRef(?string $externalRef): static
    {
        $this->externalRef = $externalRef;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getNationality(): ?string
    {
        return $this->nationality;
    }

    public function setNationality(?string $nationality): static
    {
        $this->nationality = $nationality;

        return $this;
    }

    public function getColorHex(): ?string
    {
        return $this->colorHex;
    }

    public function setColorHex(?string $colorHex): static
    {
        $this->colorHex = $colorHex;

        return $this;
    }
}
