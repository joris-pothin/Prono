<?php

namespace App\Service\Scoring;

/**
 * A range of finishing positions (inclusive) worth a fixed number of points,
 * e.g. "2nd-3rd place = 10 points".
 */
final readonly class Bracket
{
    public function __construct(
        public int $min,
        public int $max,
        public int $points,
    ) {
    }

    public function contains(int $position): bool
    {
        return $position >= $this->min && $position <= $this->max;
    }
}
