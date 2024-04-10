<?php

namespace App\Providers;

use App\Feature\SocialLogin\League\OAuth2\Server\Grant\SocialGrantExtends;
use App\Repositories\UserSocialProvidersRepository;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Bridge\RefreshTokenRepository;
use Laravel\Passport\Passport;
use League\OAuth2\Server\AuthorizationServer;
class PassportSocialiteServiceProvider extends ServiceProvider {

    public function register () {
        app()->afterResolving(AuthorizationServer::class, function(AuthorizationServer $oauthServer) {
            $oauthServer->enableGrantType($this->makeSocialGrant(), Passport::tokensExpireIn());
        });
    }

    /**
     * Create and configure Social Grant
     *
     * @return Schedula\League\OAuth2\Server\Grant\SocialGrant
     */
    public function makeSocialGrant() {
        $grant = new SocialGrantExtends(
            $this->app->make(UserSocialProvidersRepository::class),
            $this->app->make(RefreshTokenRepository::class)
        );
        $grant->setRefreshTokenTTL(Passport::refreshTokensExpireIn());
        return $grant;
    }
}
