<?php

namespace App\Entity;

use App\Repository\RiskBetVoteRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: RiskBetVoteRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_RISK_BET_VOTE_USER', fields: ['riskBet', 'user'])]
class RiskBetVote
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: RiskBet::class, inversedBy: 'votes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?RiskBet $riskBet = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['risk_bet:read'])]
    private ?User $user = null;

    #[ORM\Column]
    #[Groups(['risk_bet:read'])]
    private bool $approved = true;

    #[ORM\Column]
    #[Groups(['risk_bet:read'])]
    private ?\DateTimeImmutable $votedAt = null;

    public function __construct()
    {
        $this->votedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRiskBet(): ?RiskBet
    {
        return $this->riskBet;
    }

    public function setRiskBet(?RiskBet $riskBet): static
    {
        $this->riskBet = $riskBet;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function isApproved(): bool
    {
        return $this->approved;
    }

    public function setApproved(bool $approved): static
    {
        $this->approved = $approved;

        return $this;
    }

    public function getVotedAt(): ?\DateTimeImmutable
    {
        return $this->votedAt;
    }
}
