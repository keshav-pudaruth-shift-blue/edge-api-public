<?php

namespace App\Feature\SocialLogin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SocialProvider;
use App\Models\UsersSocialProviders;
use Illuminate\Http\Client\HttpClientException;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;

class SocialLoginController extends Controller
{
    public function __invoke(string $socialProvider): \Symfony\Component\HttpFoundation\RedirectResponse|\Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
    {
        if(!in_array($socialProvider, UsersSocialProviders::$socialProviderList)) {
            return response()->json([
                'message' => 'Invalid social provider',
            ], 400);
        }

        switch($socialProvider) {
            case SocialProvider::DISCORD:
                return Socialite::driver($socialProvider)->scopes(['identify', 'email', 'guilds'])->redirect();
            default:
                throw new HttpClientException('Invalid social provider', Response::HTTP_NOT_IMPLEMENTED);
        }
    }
}
