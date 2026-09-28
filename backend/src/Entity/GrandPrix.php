<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\GrandPrixRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: GrandPrixRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_SEASON_ROUND', fields: ['season', 'round'])]
#[ApiResource(
    operations: [new GetCollection(), new Get()],
    normalizationContext: ['groups' => ['grand_prix:read']],
    order: ['season' => 'DESC', 'round' => 'ASC'],
)]
#[ApiFilter(SearchFilter::class, properties: ['season' => 'exact'])]
class GrandPrix
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['grand_prix:read', 'result:read', 'ref:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Season::class, inversedBy: 'grandPrix')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['grand_prix:read'])]
    private ?Season $season = null;

    #[ORM\Column]
    #[Groups(['grand_prix:read', 'result:read', 'ref:read'])]
    private ?int $round = null;

    #[ORM\Column(length: 150)]
    #[Groups(['grand_prix:read', 'result:read', 'ref:read'])]
    private ?string $name = null;

    #[ORM\Column(length: 150)]
    #[Groups(['grand_prix:read'])]
    private ?string $circuit = null;

    #[ORM\Column(length: 100)]
    #[Groups(['grand_prix:read'])]
    private ?string $country = null;

    #[ORM\Column]
    #[Groups(['grand_prix:read', 'ref:read'])]
    private bool $hasSprint = false;

    /**
     * Start of FP1 — the deadline for this Grand Prix's predictions.
     */
    #[ORM\Column]
    #[Groups(['grand_prix:read', 'ref:read'])]
    private ?\DateTimeImmutable $fp1At = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['grand_prix:read'])]
    private ?\DateTimeImmutable $sprintAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['grand_prix:read'])]
    private ?\DateTimeImmutable $qualifyingAt = null;

    #[ORM\Column]
    #[Groups(['grand_prix:read'])]
    private ?\DateTimeImmutable $raceAt = null;

    /**
     * Identifier of this race in the external F1 data provider, used to match synced data.
     */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $externalRef = null;

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

    public function getRound(): ?int
    {
        return $this->round;
    }

    public function setRound(int $round): static
    {
        $this->round = $round;

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

    public function getCircuit(): ?string
    {
        return $this->circuit;
    }

    public function setCircuit(string $circuit): static
    {
        $this->circuit = $circuit;

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(string $country): static
    {
        $this->country = $country;

        return $this;
    }

    public function isHasSprint(): bool
    {
        return $this->hasSprint;
    }

    public function setHasSprint(bool $hasSprint): static
    {
        $this->hasSprint = $hasSprint;

        return $this;
    }

    public function getFp1At(): ?\DateTimeImmutable
    {
        return $this->fp1At;
    }

    public function setFp1At(\DateTimeImmutable $fp1At): static
    {
        $this->fp1At = $fp1At;

        return $this;
    }

    public function getSprintAt(): ?\DateTimeImmutable
    {
        return $this->sprintAt;
    }

    public function setSprintAt(?\DateTimeImmutable $sprintAt): static
    {
        $this->sprintAt = $sprintAt;

        return $this;
    }

    public function getQualifyingAt(): ?\DateTimeImmutable
    {
        return $this->qualifyingAt;
    }

    public function setQualifyingAt(?\DateTimeImmutable $qualifyingAt): static
    {
        $this->qualifyingAt = $qualifyingAt;

        return $this;
    }

    public function getRaceAt(): ?\DateTimeImmutable
    {
        return $this->raceAt;
    }

    public function setRaceAt(\DateTimeImmutable $raceAt): static
    {
        $this->raceAt = $raceAt;

        return $this;
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

    /**
     * Predictions for this Grand Prix are locked once FP1 has started.
     */
    public function isLocked(?\DateTimeImmutable $now = null): bool
    {
        $now ??= new \DateTimeImmutable();

        return null === $this->fp1At || $now >= $this->fp1At;
    }
}
