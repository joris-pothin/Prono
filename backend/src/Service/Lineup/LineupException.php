<?php

namespace App\Service\Lineup;

/**
 * A lineup change that can't be applied; the message is meant for the player.
 */
final class LineupException extends \RuntimeException
{
}
