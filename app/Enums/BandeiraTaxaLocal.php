<?php

namespace App\Enums;

enum BandeiraTaxaLocal: string
{
    case MasterVisa = 'master_visa';
    case HiperEloAmex = 'hiper_elo_amex';
    case Elo = 'elo';
    case Qualquer = 'qualquer';
}
