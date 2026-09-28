<?php

namespace App\Entity\Enum;

enum ResultStatus: string
{
    case FINISHED = 'finished';
    case DNF = 'dnf';
    case DSQ = 'dsq';
    case DNS = 'dns';
}
