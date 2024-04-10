<?php

namespace App\Feature\TOSAlerts\Console\Commands;

use Webklex\PHPIMAP\Message;

class NewAlertEmailCommand extends \Webklex\IMAP\Commands\ImapIdleCommand
{
    public function onNewMessage(Message $message)
    {
        if($message->from[0]->mail === 'alerts@thinkorswim.com') {
            event(new \App\Feature\TOSAlerts\Events\NewAlertEmailReceived($message->getMessageId(), $message->subject));
        }
    }
}
