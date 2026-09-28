<?php

namespace App\Service\Scoring;

/**
 * The league's point tables, straight from the house rules.
 */
final class ScoringRules
{
    public const int POLE_POINTS = 1;
    public const int FASTEST_LAP_POINTS = 1;
    /** Per correctly guessed sprint position (P1, P2 or P3). */
    public const int SPRINT_PODIUM_POSITION_POINTS = 1;

    /**
     * Season-long prediction: drivers' final championship standing.
     * 1st: 20, 2nd-3rd: 10, 4th-5th: 6, 6th-10th: 4, 11th+: 2.
     */
    public static function seasonDrivers(): BracketTable
    {
        return new BracketTable([
            new Bracket(1, 1, 20),
            new Bracket(2, 3, 10),
            new Bracket(4, 5, 6),
            new Bracket(6, 10, 4),
            new Bracket(11, \PHP_INT_MAX, 2),
        ]);
    }

    /**
     * Season-long prediction: constructors' final championship standing.
     * 1st: 10, 2nd-3rd: 5, 4th-10th: 2.
     */
    public static function seasonConstructors(): BracketTable
    {
        return new BracketTable([
            new Bracket(1, 1, 10),
            new Bracket(2, 3, 5),
            new Bracket(4, 10, 2),
        ]);
    }

    /**
     * Per-Grand-Prix bonus: predicted race classification.
     * Winner: +5, 2nd-3rd: +3, 4th-5th: +2, 6th-10th: +1.
     */
    public static function gpDrivers(): BracketTable
    {
        return new BracketTable([
            new Bracket(1, 1, 5),
            new Bracket(2, 3, 3),
            new Bracket(4, 5, 2),
            new Bracket(6, 10, 1),
        ]);
    }
}
