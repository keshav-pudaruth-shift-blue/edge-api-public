<?php

namespace App\Feature\CuriousSignals\Models;

enum CuriousSignalInterval: string
{
    case FiveSeconds = '5';
    case FifteenSeconds = '15';
    case ThirtySeconds = '30';
    case OneMinute = '60';
    case FiveMinutes = '300';
    case FifteenMinutes = '1500';
    case ThirtyMinutes = '3000';
}
