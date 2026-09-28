<?php

namespace App\Service\Scoring;

/**
 * An ordered set of position brackets. A prediction scores the bracket's full points
 * only when the predicted position and the actual position fall in the *same* bracket
 * (e.g. predicting a driver 2nd and them finishing 3rd both fall in the "2nd-3rd"
 * bracket, so the pick scores its points — but predicting 2nd when they finish 4th does
 * not, even though both are "close").
 */
final readonly class BracketTable
{
    /**
     * @param Bracket[] $brackets
     */
    public function __construct(private array $brackets)
    {
    }

    public function indexFor(int $position): ?int
    {
        foreach ($this->brackets as $index => $bracket) {
            if ($bracket->contains($position)) {
                return $index;
            }
        }

        return null;
    }

    public function scoreForPrediction(int $predictedPosition, int $actualPosition): int
    {
        $predictedIndex = $this->indexFor($predictedPosition);
        if (null === $predictedIndex) {
            return 0;
        }

        return $this->indexFor($actualPosition) === $predictedIndex ? $this->brackets[$predictedIndex]->points : 0;
    }
}
