<?php

namespace App\Services;

use Webklex\IMAP\Facades\Client;
use Webklex\PHPIMAP\Exceptions\AuthFailedException;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;
use Webklex\PHPIMAP\Exceptions\FolderFetchingException;
use Webklex\PHPIMAP\Exceptions\GetMessagesFailedException;
use Webklex\PHPIMAP\Exceptions\ImapBadRequestException;
use Webklex\PHPIMAP\Exceptions\ImapServerErrorException;
use Webklex\PHPIMAP\Exceptions\ResponseException;
use Webklex\PHPIMAP\Exceptions\RuntimeException;

class GmailService
{
    /**
     * @var \Webklex\PHPIMAP\Client
     */
    private $client;

    /**
     * @throws ImapBadRequestException
     * @throws RuntimeException
     * @throws ResponseException
     * @throws ConnectionFailedException
     * @throws ImapServerErrorException
     * @throws AuthFailedException
     */
    public function __construct()
    {
        $this->client = Client::account('default');
        $this->client->connect();
    }

    /**
     * @throws RuntimeException
     * @throws ResponseException
     * @throws ImapServerErrorException
     * @throws GetMessagesFailedException
     * @throws FolderFetchingException
     * @throws ImapBadRequestException
     * @throws ConnectionFailedException
     * @throws AuthFailedException
     */
    public function getUnreadEmails($folderName='INBOX'): \Webklex\PHPIMAP\Support\MessageCollection
    {
        $folder = $this->client->getFolderByName($folderName);
        $messages = $folder->query()->unseen()->get();
        return $messages;
    }
}
