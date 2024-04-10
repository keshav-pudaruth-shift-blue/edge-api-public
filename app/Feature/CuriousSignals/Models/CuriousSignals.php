<?php

namespace App\Feature\CuriousSignals\Models;

enum CuriousSignals: string
{
    case SIGNAL_HIGH_VOLUME = 'volume_high';
    case SIGNAL_UNUSUAL_ACTIVITY = 'unusual_activity';
}
