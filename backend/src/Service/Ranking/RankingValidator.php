<?php

namespace App\Service\Ranking;

/**
 * Validates that a player's ranking of drivers/constructors is a full, unambiguous
 * permutation: every expected entity is ranked exactly once, and ranks are exactly
 * 1..N with no gaps or duplicates.
 */
final class RankingValidator
{
    /**
     * @param array<int, int> $rankById   map of entityId => predictedRank submitted by the client
     * @param int[]           $expectedIds the full set of ids that must be ranked
     *
     * @throws RankingValidationException
     */
    public function validateFullPermutation(array $rankById, array $expectedIds): void
    {
        $errors = [];

        $submittedIds = array_keys($rankById);
        sort($submittedIds);
        $expected = $expectedIds;
        sort($expected);

        if ($submittedIds !== $expected) {
            if ($missing = array_diff($expected, $submittedIds)) {
                $errors[] = \sprintf('Missing entries for ids: %s.', implode(', ', $missing));
            }
            if ($unexpected = array_diff($submittedIds, $expected)) {
                $errors[] = \sprintf('Unexpected ids not part of this ranking: %s.', implode(', ', $unexpected));
            }
        }

        $ranks = array_values($rankById);
        sort($ranks);
        $expectedRanks = range(1, \count($expectedIds));
        if ($ranks !== $expectedRanks) {
            $errors[] = \sprintf('Ranks must be exactly the numbers 1 to %d, each used once.', \count($expectedIds));
        }

        if ($errors) {
            throw new RankingValidationException($errors);
        }
    }
}
