<?php

namespace App\Entity;

use App\Repository\GpDriverPickRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: GpDriverPickRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_GDP_PREDICTION_DRIVER', fields: ['gpPrediction', 'driver'])]
#[ORM\UniqueConstraint(name: 'UNIQ_GDP_PREDICTION_RANK', fields: ['gpPrediction', 'predictedRank'])]
class GpDriverPick
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: GpPrediction::class, inversedBy: 'driverPicks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?GpPrediction $gpPrediction = null;

    #[ORM\ManyToOne(targetEntity: Driver::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['gp_prediction:read'])]
    private ?Driver $driver = null;

    /**
     * Predicted race finishing position.
     */
    #[ORM\Column]
    #[Groups(['gp_prediction:read'])]
    private ?int $predictedRank = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGpPrediction(): ?GpPrediction
    {
        return $this->gpPrediction;
    }

    public function setGpPrediction(?GpPrediction $gpPrediction): static
    {
        $this->gpPrediction = $gpPrediction;

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
