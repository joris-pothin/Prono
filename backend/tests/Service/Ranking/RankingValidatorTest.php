<?php

namespace App\Tests\Service\Ranking;

use App\Service\Ranking\RankingValidationException;
use App\Service\Ranking\RankingValidator;
use PHPUnit\Framework\TestCase;

final class RankingValidatorTest extends TestCase
{
    private RankingValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new RankingValidator();
    }

    public function testAcceptsAFullValidPermutation(): void
    {
        $this->validator->validateFullPermutation([10 => 1, 20 => 2, 30 => 3], [10, 20, 30]);
        $this->addToAssertionCount(1); // no exception thrown
    }

    public function testRejectsAMissingEntity(): void
    {
        $this->expectException(RankingValidationException::class);
        $this->validator->validateFullPermutation([10 => 1, 20 => 2], [10, 20, 30]);
    }

    public function testRejectsAnUnexpectedEntity(): void
    {
        $this->expectException(RankingValidationException::class);
        $this->validator->validateFullPermutation([10 => 1, 20 => 2, 99 => 3], [10, 20, 30]);
    }

    public function testRejectsADuplicateRank(): void
    {
        $this->expectException(RankingValidationException::class);
        $this->validator->validateFullPermutation([10 => 1, 20 => 1, 30 => 3], [10, 20, 30]);
    }

    public function testRejectsAGapInRanks(): void
    {
        $this->expectException(RankingValidationException::class);
        $this->validator->validateFullPermutation([10 => 1, 20 => 2, 30 => 4], [10, 20, 30]);
    }
}
