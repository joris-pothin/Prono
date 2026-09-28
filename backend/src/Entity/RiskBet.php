<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\Enum\RiskBetStatus;
use App\Repository\RiskBetRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * A "prono de risque": a free-form bet a player proposes at any point in the season.
 * It only goes live once every other active player has unanimously approved it
 * (see RiskBetVote), and is later resolved won/lost by an admin, applying
 * pointsIfWin or pointsIfLose to the proposer's total.
 *
 * Written through App\Controller\RiskBet\RiskBetController, not raw API Platform
 * writes, so that the approval/resolution workflow is always enforced.
 */
#[ORM\Entity(repositoryClass: RiskBetRepository::class)]
#[ApiResource(
    operations: [new GetCollection(), new Get()],
    normalizationContext: ['groups' => ['risk_bet:read', 'ref:read']],
    security: 'is_granted("ROLE_USER")',
    order: ['createdAt' => 'DESC'],
)]
#[ApiFilter(SearchFilter::class, properties: ['season' => 'exact', 'status' => 'exact'])]
class RiskBet
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['risk_bet:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Season::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['risk_bet:read'])]
    private ?Season $season = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['risk_bet:read'])]
    private ?User $proposedBy = null;

    #[ORM\Column(length: 150)]
    #[Groups(['risk_bet:read'])]
    private ?string $title = null;

    #[ORM\Column(type: 'text')]
    #[Groups(['risk_bet:read'])]
    private ?string $description = null;

    #[ORM\Column]
    #[Groups(['risk_bet:read'])]
    private int $pointsIfWin = 0;

    /**
     * Stored as a positive number of points lost if the bet fails.
     */
    #[ORM\Column]
    #[Groups(['risk_bet:read'])]
    private int $pointsIfLose = 0;

    #[ORM\Column(length: 20, enumType: RiskBetStatus::class)]
    #[Groups(['risk_bet:read'])]
    private RiskBetStatus $status = RiskBetStatus::PENDING;

    /**
     * @var Collection<int, RiskBetVote>
     */
    #[ORM\OneToMany(targetEntity: RiskBetVote::class, mappedBy: 'riskBet', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['risk_bet:read'])]
    private Collection $votes;

    #[ORM\Column]
    #[Groups(['risk_bet:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['risk_bet:read'])]
    private ?\DateTimeImmutable $resolvedAt = null;

    public function __construct()
    {
        $this->votes = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

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

    public function getProposedBy(): ?User
    {
        return $this->proposedBy;
    }

    public function setProposedBy(?User $proposedBy): static
    {
        $this->proposedBy = $proposedBy;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getPointsIfWin(): int
    {
        return $this->pointsIfWin;
    }

    public function setPointsIfWin(int $pointsIfWin): static
    {
        $this->pointsIfWin = $pointsIfWin;

        return $this;
    }

    public function getPointsIfLose(): int
    {
        return $this->pointsIfLose;
    }

    public function setPointsIfLose(int $pointsIfLose): static
    {
        $this->pointsIfLose = $pointsIfLose;

        return $this;
    }

    public function getStatus(): RiskBetStatus
    {
        return $this->status;
    }

    public function setStatus(RiskBetStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @return Collection<int, RiskBetVote>
     */
    public function getVotes(): Collection
    {
        return $this->votes;
    }

    public function addVote(RiskBetVote $vote): static
    {
        if (!$this->votes->contains($vote)) {
            $this->votes->add($vote);
            $vote->setRiskBet($this);
        }

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getResolvedAt(): ?\DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function setResolvedAt(?\DateTimeImmutable $resolvedAt): static
    {
        $this->resolvedAt = $resolvedAt;

        return $this;
    }

    /**
     * Net points impact on the proposer's total for the season leaderboard.
     */
    public function getPointsDelta(): int
    {
        return match ($this->status) {
            RiskBetStatus::RESOLVED_WIN => $this->pointsIfWin,
            RiskBetStatus::RESOLVED_LOSE => -$this->pointsIfLose,
            default => 0,
        };
    }
}
