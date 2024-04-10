<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * @var string[]
     */
    protected array $skipRoutes = ['/kube/health-check'];

    /**
     * @var string[]
     */
    protected array $skipCommands = ['horizon:snapshot', 'kafka:consume-by-version'];


    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        Telescope::night();

        $this->hideSensitiveRequestDetails();

        $this->tagRequests();

        Telescope::filter(function (IncomingEntry $entry) {
            if ($this->app->environment([
                'local',
                'production'
            ])) {

                if(is_array($entry->content)) {
                    switch($entry->type) {
                        case EntryType::REQUEST:
                            if(isset($entry->content['uri']) && in_array($entry->content['uri'], $this->skipRoutes)) {
                                return false;
                            }
                            break;
                        case EntryType::COMMAND:
                            if(isset($entry->content['command']) && in_array($entry->content['command'], $this->skipCommands)) {
                                return false;
                            }
                            break;
                    }
                }

                return true;
            }

            return $entry->isReportableException() ||
                   $entry->isFailedRequest() ||
                   $entry->isFailedJob() ||
                   $entry->isScheduledTask() ||
                   $entry->hasMonitoredTag();
        });
    }

    protected function tagRequests()
    {
        Telescope::tag(function (IncomingEntry $entry) {
            if ($entry->type === EntryType::REQUEST) {
                $entryContent = $entry->content;
                $currentRoute = Route::getCurrentRoute();

                $requestTags = [
                    'method:'.$entryContent['method'],
                    'status:'.$entryContent['response_status']
                ];

                //OPTIONS is not a route and sets currentRoute to null.
                if(!empty($currentRoute)) {
                    $currentRouteName = $currentRoute->getName();
                    if(!empty($currentRouteName)) {
                        array_push($requestTags, 'name:'.$currentRouteName);
                    }
                }

                return $requestTags;
            }

            return [];
        });
    }

    /**
     * Prevent sensitive request details from being logged by Telescope.
     *
     * @return void
     */
    protected function hideSensitiveRequestDetails(): void
    {
        if ($this->app->environment('local')) {
            return;
        }

        Telescope::hideRequestParameters(['_token']);

        Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
        ]);
    }

    /**
     * Register the Telescope gate.
     *
     * This gate determines who can access Telescope in non-local environments.
     *
     * @return void
     */
    protected function gate(): void
    {
        Gate::define('viewTelescope', function ($user = null) {
            return true;
        });
    }
}
