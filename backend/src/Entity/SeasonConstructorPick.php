<?php

namespace App\Entity;

use App\Repository\SeasonConstructorPickRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: SeasonConstructorPickRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_SCP_PREDICTION_TEAM', fields: ['seasonPrediction', 'team'])]
#[ORM\UniqueConstraint(name: 'UNIQ_SCP_PREDICTION_RANK', fields: ['seasonPrediction', 'predictedRank'])]
class SeasonConstructorPick
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: SeasonPrediction::class, inversedBy: 'constructorPicks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?SeasonPrediction $seasonPrediction = null;

    #[ORM\ManyToOne(targetEntity: Team::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['season_prediction:read'])]
    private ?Team $team = null;

    /**
     * Predicted final championship rank (1 = predicted champion team).
     */
    #[ORM\Column]
    #[Groups(['season_prediction:read'])]
    private ?int $predictedRank = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSeasonPrediction(): ?SeasonPrediction
    {
        return $this->seasonPrediction;
    }

    public function setSeasonPrediction(?SeasonPrediction $seasonPrediction): static
    {
        $this->seasonPrediction = $seasonPrediction;

        return $this;
    }

    public function getTeam(): ?Team
    {
        return $this->team;
    }

    public function setTeam(?Team $team): static
    {
        $this->team = $team;

        return $this;
    }

    public function getPredictedRank(): ?int
    {
        return $this->predictedRank;
    }

    public function setPredictedRank(int $predictedRank): static
    {
        $this->predictedRank = $predictedRank;

        return $this;
    }
}
