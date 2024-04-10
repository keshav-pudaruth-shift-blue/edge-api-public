<?php

namespace App\Feature\IBOptionsDataSync\Tests\Integration\Command;

use App\Feature\IBOptionsDataSync\Jobs\SyncSymbolOptionsContractsChainJob;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SyncSymbolOptionsContractsChainCommandTest extends TestCase
{
    public function test_normal()
    {
        Queue::fake();

        $this->artisan('ib:sync-symbol-options-contracts-chain', ['--all' => true])
            ->expectsOutput('Syncing symbol options contracts chain - Start')
            ->expectsOutput('Syncing all enabled symbols')
            ->expectsOutput('Syncing symbol options contracts chain - End')
            ->assertExitCode(0);

        Queue::assertPushed(SyncSymbolOptionsContractsChainJob::class);
    }
}
