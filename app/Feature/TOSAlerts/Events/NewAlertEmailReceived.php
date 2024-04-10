<?php

namespace App\Feature\TOSAlerts\Events;

use Illuminate\Contracts\Queue\ShouldQueue;

class NewAlertEmailReceived implements ShouldQueue
{
    public function __construct(public string $messageId, public string $subject){}
}
