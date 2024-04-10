<?php

namespace App\Feature\OptionsDataSync\Events;

class SyncSymbolOptionsDataSuccess
{
    public function __construct(protected string $symbol)
    {
    }

}
