<?php

namespace App\Tests\Service\Scoring;

use App\Service\Scoring\Bracket;
use App\Service\Scoring\BracketTable;
use PHPUnit\Framework\TestCase;

final class BracketTableTest extends TestCase
{
    private function table(): BracketTable
    {
        // Mirrors the season drivers' table: 1st=20, 2nd-3rd=10, 4th-5th=6, 6th-10th=4, 11th+=2.
        return new BracketTable([
            new Bracket(1, 1, 20),
            new Bracket(2, 3, 10),
            new Bracket(4, 5, 6),
            new Bracket(6, 10, 4),
            new Bracket(11, \PHP_INT_MAX, 2),
        ]);
    }

    public function testExactPositionMatchScoresFullBracketPoints(): void
    {
        self::assertSame(20, $this->table()->scoreForPrediction(1, 1));
    }

    public function testSameBracketDifferentPositionStillScores(): void
    {
        // Predicted 2nd, actual 3rd — both fall in the "2nd-3rd" bracket.
        self::assertSame(10, $this->table()->scoreForPrediction(2, 3));
        self::assertSame(10, $this->table()->scoreForPrediction(3, 2));
    }

    public function testDifferentBracketScoresZeroEvenWhenClose(): void
    {
        // Predicted 3rd (bracket "2nd-3rd"), actual 4th (bracket "4th-5th") — no points,
        // even though missing by only one place.
        self::assertSame(0, $this->table()->scoreForPrediction(3, 4));
    }

    public function testUnboundedTopBracket(): void
    {
        self::assertSame(2, $this->table()->scoreForPrediction(15, 20));
        self::assertSame(0, $this->table()->scoreForPrediction(15, 5));
    }

    public function testIndexForReturnsNullOutsideAllBrackets(): void
    {
        $table = new BracketTable([new Bracket(1, 3, 10)]);
        self::assertNull($table->indexFor(4));
        self::assertSame(0, $table->scoreForPrediction(1, 4));
    }
}
