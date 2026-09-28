<?php

namespace App\Entity\Enum;

enum RiskBetStatus: string
{
    /** Waiting for unanimous approval from the other active players. */
    case PENDING = 'pending';
    /** Unanimously approved, waiting to be resolved. */
    case APPROVED = 'approved';
    /** At least one player voted against it — never went live. */
    case REJECTED = 'rejected';
    /** Approved and resolved: the bet was won, pointsIfWin awarded. */
    case RESOLVED_WIN = 'resolved_win';
    /** Approved and resolved: the bet was lost, pointsIfLose deducted. */
    case RESOLVED_LOSE = 'resolved_lose';
    /** Withdrawn by its proposer before resolution. */
    case CANCELLED = 'cancelled';
}
