<?php

namespace App\Tests\Service\Scoring;

use App\Service\Scoring\ScoringRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Locks in the house rules as data, so a future refactor of the scoring engine can't
 * silently drift from the point values the league agreed on.
 */
final class ScoringRulesTest extends TestCase
{
    /**
     * @return iterable<string, array{int, int}>
     */
    public static function seasonDriverBrackets(): iterable
    {
        yield '1st' => [1, 20];
        yield '2nd' => [2, 10];
        yield '3rd' => [3, 10];
        yield '4th' => [4, 6];
        yield '5th' => [5, 6];
        yield '6th' => [6, 4];
        yield '10th' => [10, 4];
        yield '11th' => [11, 2];
        yield '20th' => [20, 2];
    }

    #[DataProvider('seasonDriverBrackets')]
    public function testSeasonDriversPointsForExactPosition(int $position, int $expectedPoints): void
    {
        self::assertSame($expectedPoints, ScoringRules::seasonDrivers()->scoreForPrediction($position, $position));
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function seasonConstructorBrackets(): iterable
    {
        yield '1st' => [1, 10];
        yield '2nd' => [2, 5];
        yield '3rd' => [3, 5];
        yield '4th' => [4, 2];
        yield '10th' => [10, 2];
    }

    #[DataProvider('seasonConstructorBrackets')]
    public function testSeasonConstructorsPointsForExactPosition(int $position, int $expectedPoints): void
    {
        self::assertSame($expectedPoints, ScoringRules::seasonConstructors()->scoreForPrediction($position, $position));
    }

    public function testSeasonConstructorsEleventhAndBeyondScoreNothing(): void
    {
        self::assertSame(0, ScoringRules::seasonConstructors()->scoreForPrediction(11, 11));
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function gpDriverBrackets(): iterable
    {
        yield 'winner' => [1, 5];
        yield '2nd' => [2, 3];
        yield '3rd' => [3, 3];
        yield '4th' => [4, 2];
        yield '5th' => [5, 2];
        yield '6th' => [6, 1];
        yield '10th' => [10, 1];
        yield '11th' => [11, 0];
    }

    #[DataProvider('gpDriverBrackets')]
    public function testGpDriversPointsForExactPosition(int $position, int $expectedPoints): void
    {
        self::assertSame($expectedPoints, ScoringRules::gpDrivers()->scoreForPrediction($position, $position));
    }

    public function testBonusPointConstants(): void
    {
        self::assertSame(1, ScoringRules::POLE_POINTS);
        self::assertSame(1, ScoringRules::FASTEST_LAP_POINTS);
        self::assertSame(1, ScoringRules::SPRINT_PODIUM_POSITION_POINTS);
    }
}
