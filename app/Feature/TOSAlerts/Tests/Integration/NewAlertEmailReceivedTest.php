<?php

namespace App\Feature\TOSAlerts\Tests\Integration;

use App\Feature\TOSAlerts\Events\NewAlertEmailReceived;
use Tests\TestCase;

class NewAlertEmailReceivedTest extends TestCase
{
    public function test_new_alert_email_received_with_multiple_options_must_be_handled()
    {
        event(new NewAlertEmailReceived('messageId', 'Alert: New symbols: .MRVL230616C61, .PLTR230616C16 were added to Mojouiss Scans.'));
        $this->assertTrue(true);
    }

    public function test_new_alert_email_received_with_single_option_must_be_handled()
    {
        event(new NewAlertEmailReceived('messageId', 'Alert: New symbol: .NVDA230609C387.5 was added to Mojouiss Scans.'));
        $this->assertTrue(true);
    }
}
