<?php

namespace App\Repositories;

use App\Feature\SocialLogin\League\OAuth2\Server\Repositories\UserSocialRepositoryInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use Laravel\Passport\Bridge\User;
use Socialite;
use InvalidArgumentException;
use League\OAuth2\Server\Exception\OAuthServerException;
class UserSocialProvidersRepository implements UserSocialRepositoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function getUserEntityByUserCredentials(
        $username,
        $password,
        $grantType,
        ClientEntityInterface $clientEntity
    ) {
        // no use implemented as UserSocialRepository extends UserRepository Class
        return;
    }

    /**
     * {@inheritdoc}
     * @throws OAuthServerException
     */
    public function getUserFromSocialProvider(
        $accessToken,
        $socialProvider,
        $grantType,
        ClientEntityInterface $clientEntity
    ) {
        try {
            $socialite = Socialite::with($socialProvider);
            $socialUser = $socialite->userFromToken($accessToken);

            $provider = config('auth.guards.api.provider');
            if (is_null($model = config('auth.providers.' . $provider . '.model'))) {
                throw OAuthServerException::serverError('Unable to determine authentication model from configuration.');
            }
            if (method_exists($model, 'findForPassportSocialite')) {
                $user = $model::findForPassportSocialite($socialProvider, $socialUser->getId());
                if (!$user) {
                    return;
                }
                return new User($user->getAuthIdentifier());
            } else {
                throw OAuthServerException::serverError(
                    'method "findForPassportSocialite" not implemented in authentication model from configuration.'
                );
            }
        } catch (InvalidArgumentException $e) {
            throw OAuthServerException::invalidRequest('provider');
        } catch (\Exception $e) {
            throw OAuthServerException::serverError($e->getMessage());
        }
    }
}
