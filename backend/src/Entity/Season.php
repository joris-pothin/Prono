<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\SeasonRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: SeasonRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_SEASON_YEAR', fields: ['year'])]
#[ApiResource(
    operations: [new GetCollection(), new Get()],
    normalizationContext: ['groups' => ['season:read']],
    order: ['year' => 'DESC'],
)]
class Season
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['season:read', 'grand_prix:read', 'ref:read'])]
    private ?int $id = null;

    #[ORM\Column]
    #[Groups(['season:read', 'grand_prix:read', 'ref:read'])]
    private ?int $year = null;

    /**
     * @var Collection<int, GrandPrix>
     */
    #[ORM\OneToMany(targetEntity: GrandPrix::class, mappedBy: 'season', orphanRemoval: true)]
    #[ORM\OrderBy(['round' => 'ASC'])]
    private Collection $grandPrix;

    /**
     * @var Collection<int, SeasonEntry>
     */
    #[ORM\OneToMany(targetEntity: SeasonEntry::class, mappedBy: 'season', orphanRemoval: true)]
    private Collection $entries;

    public function __construct()
    {
        $this->grandPrix = new ArrayCollection();
        $this->entries = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function setYear(int $year): static
    {
        $this->year = $year;

        return $this;
    }

    /**
     * @return Collection<int, GrandPrix>
     */
    public function getGrandPrix(): Collection
    {
        return $this->grandPrix;
    }

    /**
     * @return Collection<int, SeasonEntry>
     */
    public function getEntries(): Collection
    {
        return $this->entries;
    }

    /**
     * Deadline for season-long (championship) predictions: FP1 of round 1.
     */
    #[Groups(['season:read'])]
    public function getPredictionsDeadline(): ?\DateTimeImmutable
    {
        $first = null;
        foreach ($this->grandPrix as $gp) {
            if (1 === $gp->getRound()) {
                $first = $gp;
                break;
            }
        }
        $first ??= $this->grandPrix->isEmpty() ? null : $this->grandPrix->first();

        return $first?->getFp1At();
    }
}
