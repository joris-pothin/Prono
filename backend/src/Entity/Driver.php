<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\DriverRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: DriverRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_DRIVER_EXTERNAL_REF', fields: ['externalRef'])]
#[ApiResource(
    operations: [new GetCollection(), new Get()],
    normalizationContext: ['groups' => ['driver:read']],
    order: ['lastName' => 'ASC'],
)]
class Driver
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['driver:read', 'result:read', 'standing:read', 'ref:read'])]
    private ?int $id = null;

    /**
     * Identifier of this driver in the external F1 data provider (e.g. jolpica-f1 "driverId"),
     * used to match incoming synced data to this entity.
     */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $externalRef = null;

    #[ORM\Column(length: 3)]
    #[Groups(['driver:read', 'result:read', 'standing:read', 'ref:read'])]
    private ?string $code = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['driver:read'])]
    private ?int $number = null;

    #[ORM\Column(length: 100)]
    #[Groups(['driver:read', 'result:read', 'standing:read', 'ref:read'])]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    #[Groups(['driver:read', 'result:read', 'standing:read', 'ref:read'])]
    private ?string $lastName = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['driver:read'])]
    private ?string $nationality = null;

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

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getNumber(): ?int
    {
        return $this->number;
    }

    public function setNumber(?int $number): static
    {
        $this->number = $number;

        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

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

    #[Groups(['driver:read', 'result:read', 'standing:read', 'ref:read'])]
    public function getFullName(): string
    {
        return trim(sprintf('%s %s', $this->firstName, $this->lastName));
    }
}
