<?php

namespace App\Feature\SocialLogin\League\OAuth2\Server\Repositories;

use League\OAuth2\Server\Entities\UserEntityInterface;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;

interface UserSocialRepositoryInterface extends UserRepositoryInterface
{

    /**
     * Get a user entity.
     *
     * @param string $accessToken
     * @param string $provider
     * @param string $grantType The grant type used
     * @param ClientEntityInterface $clientEntity
     *
     * @return UserEntityInterface
     */

    public function getUserFromSocialProvider(
        string $accessToken,
        string $provider,
        string $grantType,
        ClientEntityInterface $clientEntity
    );
}
