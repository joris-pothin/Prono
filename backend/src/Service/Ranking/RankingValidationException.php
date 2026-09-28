<?php

namespace App\Service\Ranking;

final class RankingValidationException extends \RuntimeException
{
    /**
     * @param string[] $errors
     */
    public function __construct(private readonly array $errors)
    {
        parent::__construct('Invalid ranking: '.implode(' ', $errors));
    }

    /**
     * @return string[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
