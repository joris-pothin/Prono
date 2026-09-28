<?php

namespace App\Entity;

use App\Repository\SeasonDriverPickRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: SeasonDriverPickRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_SDP_PREDICTION_DRIVER', fields: ['seasonPrediction', 'driver'])]
#[ORM\UniqueConstraint(name: 'UNIQ_SDP_PREDICTION_RANK', fields: ['seasonPrediction', 'predictedRank'])]
class SeasonDriverPick
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: SeasonPrediction::class, inversedBy: 'driverPicks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?SeasonPrediction $seasonPrediction = null;

    #[ORM\ManyToOne(targetEntity: Driver::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['season_prediction:read'])]
    private ?Driver $driver = null;

    /**
     * Predicted final championship rank (1 = predicted champion).
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

    public function getDriver(): ?Driver
    {
        return $this->driver;
    }

    public function setDriver(?Driver $driver): static
    {
        $this->driver = $driver;

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
