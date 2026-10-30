<?php

namespace App\Entity\Enum;

enum LineupChangeType: string
{
    case ADDED = 'added';
    case REMOVED = 'removed';
}
