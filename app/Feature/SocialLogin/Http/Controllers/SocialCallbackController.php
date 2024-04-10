<?php

namespace App\Feature\SocialLogin\Http\Controllers;

use App\Feature\SocialLogin\Services\DiscordAPIService;
use App\Http\Controllers\Controller;

use App\Models\SocialProvider;
use App\Models\User;
use App\Models\UsersSocialProviders;
use App\Repositories\UsersWhitelist;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;

class SocialCallbackController extends Controller
{
    /**
     * @var array|int[]
     */
    protected array $allowedDiscordGuilds = [
        809289191766491175, //Hungry Bot Trading
        934370952182120448 //CashDash
    ];

    public function __construct(
        protected UsersWhitelist $usersWhitelistRepository,
        protected DiscordAPIService $discordAPIService
    ) {
    }

    public function __invoke(string $socialProvider, Request $request)
    {
        if (!in_array($socialProvider, UsersSocialProviders::$socialProviderList)) {
            return response()->json([
                'message' => 'Invalid social provider',
            ], 400);
        }

        switch ($socialProvider) {
            case SocialProvider::DISCORD:
                return $this->loginDiscord($request);
            case SocialProvider::REDDIT:
                return $this->loginReddit($request);
            case SocialProvider::GOOGLE:
                return $this->loginGoogle($request);
        }
    }

    /**
     * @throws \Throwable
     */
    private function loginDiscord(Request $request)
    {
        if ($request->has('error')) {
            return response()->json([
                'message' => 'Discord login failed',
            ], 400);
        }

        $discord = Socialite::driver(SocialProvider::DISCORD)->user();
        if ($discord->token) {
            $socialUser = UsersSocialProviders::where('provider', SocialProvider::DISCORD)
                ->where('provider_user_id', $discord->id)
                ->first();

            if ($socialUser) {
                $socialUser->user->update([
                    'name' => $discord->name,
                    'email' => $discord->email,
                ]);
            } else {
                //Check guilds
                if ($this->validateDiscordLogin($discord) === false) {
                    return response()->json([
                        'message' => 'Discord login is disallowed with current user. Please contact administrator: Mysteryos on Discord',
                    ], 400);
                }

                DB::transaction(function () use ($discord) {
                    $socialUser = User::create([
                        'name' => $discord->name,
                        'email' => $discord->email,
                        'password' => Hash::make(Str::random(24)),
                    ]);

                    UsersSocialProviders::create([
                        'provider' => SocialProvider::DISCORD,
                        'provider_user_id' => $discord->id,
                        'user_id' => $socialUser->id,
                        'discord_guilds' => $discord->user['guilds'] ?? [],
                    ]);
                }, 3);
            }

            $accesstoken = $this->issueToken($request, SocialProvider::DISCORD, $discord->token);
            Log::info('muh access token', [$accesstoken]);
            if (empty($accesstoken)) {
                return response()->json([
                    'message' => 'Invalid credentials from passport',
                ], 400);
            } else {
                return redirect()->to(env('PORTAL_URL'))->withCookie(
                    cookie(
                        'edge_passport',
                        $accesstoken,
                        env('COOKIE_LIFETIME', 60),
                        null,
                        env('COOKIE_DOMAIN'),
                        app()->environment('production'),
                        false
                    )
                );
            }
        }
    }

    private function validateDiscordLogin($discord): bool
    {
        $loginAllowed = true;

        //Check guilds
        $guildsResponse = $this->discordAPIService->setAccessToken($discord->token)->getUserGuild();
        if ($guildsResponse->successful()) {
            $guilds = $guildsResponse->json();
            $discord->user['guilds'] = $guilds;

            $guilds = Arr::pluck($guilds, 'id');
            $guilds = array_intersect($guilds, $this->allowedDiscordGuilds);

            if (count($guilds) === 0) {
                Log::info('User tried to login with discord but is not in any of the allowed guilds', [
                    'email' => $discord->email,
                    'guilds' => $guilds,
                ]);
                $loginAllowed = false;
            } else {
                Log::info('User logged in with discord.', [
                    'email' => $discord->email,
                    'guilds' => $guilds,
                ]);
            }
        } else {
            Log::warning('Failed to get guilds', [
                'response' => $guildsResponse->body(),
                'response_code' => $guildsResponse->status(),
            ]);
            $loginAllowed = false;
        }

        //If guild check fails, we fall back to user email check
        if ($loginAllowed === false && env('USER_REGISTRATION_WHITELIST_ENABLED', true) === true) {
            $loginAllowed = $this->usersWhitelistRepository->isWhitelisted($discord->email);
        }

        return $loginAllowed;
    }

    private function loginReddit(Request $request)
    {
        return response()->json([
            'message' => 'Unsupported login method',
        ], 422);
    }

    private function loginGoogle(Request $request)
    {
        return response()->json([
            'message' => 'Unsupported login method',
        ], 422);
    }

    public function issueToken($request, $provider, $accessToken)
    {
        /**
         * Here we will request our app to generate access token
         * and refresh token for the user using its social identity by providing access token
         * and provider name of the provider. (I hope it's not confusing)
         * and then it goes through social grant and which fetches providers user id then calls
         * findForPassportSocialite from your user model if it returns User object then it generates
         * oauth tokens or else will throw error message normally like other oauth requests.
         */
        $params = [
            'grant_type' => 'social',
            'client_id' => env('SOCIALITE_PASSWORD_GRANT_CLIENT_ID'),
            'client_secret' => env('SOCIALITE_PASSWORD_GRANT_CLIENT_SECRET'),
            'accessToken' => $accessToken, // access token from provider
            'provider' => $provider, // i.e. facebook
        ];
        $request->request->add($params);

        $requestToken = Request::create("oauth/token", "POST");
        $response = Route::dispatch($requestToken);

        $decodedResponse = json_decode($response->getContent(), true);
        return Arr::get($decodedResponse, 'access_token');
    }
}
