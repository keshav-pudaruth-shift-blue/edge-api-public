<?php

namespace App\Feature\TOSAlerts\Jobs;

use App\Feature\TOSAlerts\Events\NewAlertEmailReceived;
use App\Job\BasicJob;
use App\Services\GmailService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Log;
use Spatie\ArtisanDispatchable\Jobs\ArtisanDispatchable;
use Webklex\PHPIMAP\Exceptions\AuthFailedException;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;
use Webklex\PHPIMAP\Exceptions\FolderFetchingException;
use Webklex\PHPIMAP\Exceptions\GetMessagesFailedException;
use Webklex\PHPIMAP\Exceptions\ImapBadRequestException;
use Webklex\PHPIMAP\Exceptions\ImapServerErrorException;
use Webklex\PHPIMAP\Exceptions\ResponseException;
use Webklex\PHPIMAP\Exceptions\RuntimeException;

class PollEmailInboxForAlertsJob extends BasicJob implements ArtisanDispatchable, ShouldBeUnique
{
    public int $tries = 1;

    /**
     * @var GmailService
     */
    private $gmailService;

    /**
     * @throws RuntimeException
     * @throws GetMessagesFailedException
     * @throws ResponseException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ConnectionFailedException
     * @throws ImapServerErrorException
     * @throws AuthFailedException
     */
    public function handle(): void
    {
        $this->initializeServices();

        $unreadEmails = $this->gmailService->getUnreadEmails('TosAlerts');

        if($unreadEmails->count() > 0) {
            $unreadEmails->each(function($email) {
                event(new NewAlertEmailReceived($email->getMessageId(), $email->getSubject()));
                $email->setFlag('SEEN');
            });
        } else {
            Log::info('PollEmailInboxForAlertsJob - No new emails found');
        }
    }

    private function initializeServices()
    {
        $this->gmailService = app(GmailService::class);
    }

    public function tags(): array
    {
        return ['poll-email-inbox-for-alerts'];
    }
}
