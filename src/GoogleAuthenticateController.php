<?php

namespace Statikbe\GoogleAuthenticate;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Statikbe\GoogleAuthenticate\Events\GoogleUserRegistered;
use Statikbe\GoogleAuthenticate\Exceptions\GoogleAuthenticationException;

class GoogleAuthenticateController extends Controller
{
    /*
   |--------------------------------------------------------------------------
   | Login Controller
   |--------------------------------------------------------------------------
   |
   | This controller handles authenticating users for the application and
   | redirecting them to your home screen. The controller uses a trait
   | to conveniently provide its functionality to your applications.
   |
   */

    protected ?string $redirectTo = null;

    const GOOGLE_VALUES = [
        'name', 'email_verified', 'email', 'given_name', 'family_name', 'picture', 'nickname', 'locale',
    ];

    protected $userModel;

    protected bool $registerEnabled = false;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->redirectTo = config('google-authenticate.redirect_url');

        $this->registerEnabled = (bool) config('google-authenticate.register_enabled', true);
    }

    /**
     * Redirect the user to the Google authentication page.
     */
    public function redirectToProvider(): RedirectResponse
    {
        request()->session()->flash('googleLoginUrl', $this->sanitiseLoginUrl(url()->previous()));

        return Socialite::driver('google')->scopes(['openid', 'profile', 'email'])->redirect();
    }

    public function handleProviderCallback(): RedirectResponse
    {
        $sessionUrl = session('googleLoginUrl');
        $loginUrl = $this->sanitiseLoginUrl(is_string($sessionUrl) ? $sessionUrl : null);

        if (request()->has('error')) {
            return Redirect::to($loginUrl)->with(['danger' => __('google-authenticate::messages.error')]);
        }

        try {
            $user = Socialite::driver('google')->user();
            $hostedDomain = $this->assertLoginAllowed($user);
            // Same transaction as the row: a throwing listener must roll the new user back, otherwise the
            // next login is no longer "recently created" and the listener's work is lost for good.
            // Auth::login comes after the commit, so a failed listener also leaves the visitor logged out.
            $authUser = (new User)->getConnection()->transaction(function () use ($user, $hostedDomain): User {
                $authUser = $this->findOrCreate($user, 'google');

                if ($authUser->wasRecentlyCreated) {
                    event(new GoogleUserRegistered($authUser, $user, $hostedDomain));
                }

                return $authUser;
            });

            Auth::login($authUser, (bool) config('google-authenticate.remember', false));
            request()->session()->regenerate();

            return Redirect::to($this->redirectTo)->with('success', __('google-authenticate::messages.success'));
        } catch (GoogleAuthenticationException) {
            return Redirect::to($loginUrl)->with(['danger' => __('google-authenticate::messages.unauthenticated')]);
        } catch (InvalidStateException) {
            return Redirect::to($loginUrl)->with(['danger' => __('google-authenticate::messages.error')]);
        }
    }

    /**
     * The return URL comes from the Referer header, so it is attacker controlled.
     * Only same-host http(s) URLs and relative paths may be redirected to.
     */
    private function sanitiseLoginUrl(?string $url): string
    {
        if (blank($url)) {
            return '/';
        }

        $parts = parse_url($url);
        if ($parts === false) {
            return '/';
        }

        if (! isset($parts['host'])) {
            // A scheme without a host is never same-origin: javascript:, data:, mailto:, ...
            if (isset($parts['scheme'])) {
                return '/';
            }

            // Browsers normalise backslashes to slashes, so //evil.com, /\evil.com and
            // \\evil.com are all protocol-relative even though parse_url reports no host.
            if (preg_match('#^[/\\\\]{2}#', $url) === 1) {
                return '/';
            }

            return $url;
        }

        if (! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return '/';
        }

        // The port is deliberately ignored, so proxied and local setups keep working.
        $allowedHosts = array_filter([parse_url((string) config('app.url'), PHP_URL_HOST), request()->getHost()]);

        foreach ($allowedHosts as $allowedHost) {
            if (strcasecmp($parts['host'], $allowedHost) === 0) {
                return $url;
            }
        }

        return '/';
    }

    public function getUserModel(): Model
    {
        if (! isset($this->userModel)) {
            $userNamespace = config('auth.providers.users.model');
            $this->userModel = new $userNamespace;
        }

        return $this->userModel;
    }

    /**
     * Every check that decides whether this Google account may enter at all. It is private and
     * called from handleProviderCallback, so overriding findOrCreate/createUser cannot drop it.
     *
     * @return string the verified Google hosted domain ('' when Google sent none)
     *
     * @throws GoogleAuthenticationException
     */
    private function assertLoginAllowed(AbstractUser $googleUser): string
    {
        // Google returns a bool, but tolerate the string form. Checked first so no account is ever created or linked unverified.
        if (! filter_var($googleUser->getRaw()['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            throw new GoogleAuthenticationException('E-mail not verified');
        }

        if (! isset($googleUser->email)) {
            throw new GoogleAuthenticationException(__('google-authenticate::messages.unauthenticated'));
        }

        $emailDomain = $this->emailDomain($googleUser);

        $domains = config('google-authenticate.domains', []);
        $disabled = array_map('strtolower', $domains['disabled'] ?? []);
        $allowed = array_map('strtolower', $domains['allowed'] ?? []);

        if (in_array($emailDomain, $disabled, true)
            || ($allowed && ! in_array($emailDomain, $allowed, true))) {
            throw new GoogleAuthenticationException('E-mail domain not allowed');
        }

        $hostedDomain = strtolower((string) ($googleUser->getRaw()['hd'] ?? ''));

        // The hosted-domain claim is only present for Workspace accounts, so it proves org membership; an email domain alone does not.
        if ($allowed && ! in_array($hostedDomain, $allowed, true)) {
            throw new GoogleAuthenticationException('Hosted domain not allowed');
        }

        return $hostedDomain;
    }

    /**
     * @throws GoogleAuthenticationException
     */
    private function emailDomain(AbstractUser $googleUser): string
    {
        $emailParts = explode('@', $googleUser->email);
        if (count($emailParts) !== 2) {
            throw new GoogleAuthenticationException('No e-mail domain found.');
        }

        return strtolower($emailParts[1]);
    }

    /**
     * Attach the Google identity to an existing row that matched by email only. Does not save.
     * Without this guard anyone who controls a Google account on the same address (a personal
     * account registered with a company address, say) could take over a local account.
     *
     * @throws GoogleAuthenticationException
     */
    private function linkExisting(Model $user, AbstractUser $googleUser, string $provider): void
    {
        $emailDomain = $this->emailDomain($googleUser);
        $hostedDomain = (string) ($googleUser->getRaw()['hd'] ?? '');

        // Only Google itself is authoritative for gmail.com addresses; any other domain needs the hd claim as proof of ownership.
        $owned = ($hostedDomain !== '' && strcasecmp($hostedDomain, $emailDomain) === 0)
            || in_array($emailDomain, ['gmail.com', 'googlemail.com'], true);

        if (! $owned) {
            throw new GoogleAuthenticationException('E-mail domain ownership not proven');
        }

        $user->forceFill(['provider' => $provider, 'provider_id' => $googleUser->id]);

        // A password or remember token set before the first Google login could belong to an attacker who pre-registered the address.
        if (array_key_exists('password', $user->getAttributes())) {
            $user->forceFill(['password' => null]);
        }

        if (array_key_exists($user->getRememberTokenName(), $user->getAttributes())) {
            $user->setRememberToken(Str::random(60));
        }
    }

    /**
     * If a user has registered before using social auth, return the user
     * else, create a new user object.
     *
     * The security checks already ran in handleProviderCallback.
     *
     * @throws GoogleAuthenticationException
     */
    protected function findOrCreate(AbstractUser $googleUser, string $provider): User
    {
        // make userFillableArray
        $userData = $this->fillUserData($googleUser);
        $userData['provider'] = $provider;
        $userData['provider_id'] = $googleUser->id;

        if ($this->registerEnabled) {
            return $this->createUser($userData, $googleUser);
        }

        // Registration disabled: only existing users.
        $user = $this->getUserModel()::where('provider_id', $userData['provider_id'])->first()
            ?? $this->getUserModel()::where('email', $userData['email'])->whereNull('provider_id')->first();

        if (! $user) {
            throw new GoogleAuthenticationException('No user found.');
        }

        if (! $user->provider_id) {
            $this->linkExisting($user, $googleUser, $provider);
            $user->save();
        }

        return $user;
    }

    /**
     * @return array
     */
    private function fillUserData(AbstractUser $user): array
    {
        // get user table columns
        $columns = config('google-authenticate.user_columns', []);
        $user = $user->getRaw();
        $data = [];

        foreach ($columns as $columnName => $values) {
            // check for google values
            $this->checkForGoogleData($values, $user);

            // implode values and add them to the correct column
            $data[$columnName] = implode('', $values);
        }

        return $data;
    }

    /**
     * @return User
     *
     * @throws GoogleAuthenticationException
     */
    protected function createUser(array $userData, AbstractUser $googleUser): User
    {
        // provider and provider_id are set explicitly (forceFill), independent of $fillable
        $identity = Arr::only($userData, ['provider', 'provider_id']);
        $emailVerifiedAt = $userData['email_verified_at'] ?? null;
        $attributes = Arr::except($userData, ['provider', 'provider_id', 'email_verified_at']);

        //search for possible user with this email but without Google provider
        $user = $this->getUserModel()::where('email', $attributes['email'])->whereNull('provider_id')->first();
        if ($user) {
            $user->fill($attributes);
            $this->linkExisting($user, $googleUser, $identity['provider']);
        } else {
            // email already linked to another Google account: never create a duplicate or take it over
            if ($this->getUserModel()::where('email', $attributes['email'])->where('provider_id', '!=', $identity['provider_id'])->exists()) {
                throw new GoogleAuthenticationException('E-mail linked to another Google account');
            }

            $user = $this->getUserModel()::where('provider_id', $identity['provider_id'])->first() ?? $this->getUserModel()->newInstance();
            $user->fill($attributes)->forceFill($identity);
        }

        //verify user - only set if not already verified
        if (! $user->email_verified_at && $emailVerifiedAt) {
            $user->email_verified_at = $emailVerifiedAt;
        }

        $user->save();

        return $user;
    }

    /**
     * @param array $user
     */
    private function checkForGoogleData(array &$values, array $user): void
    {
        // loop values provided from config
        foreach ($values as $key => $value) {
            // if email_verified make sure it returns a datetime
            if ($value === 'email_verified') {
                $values[$key] = filter_var($user[$value] ?? false, FILTER_VALIDATE_BOOLEAN) ? now()->toDateTimeString() : null;

                continue;
            }

            // if value found in google_values array, return it's google value
            if (in_array($value, self::GOOGLE_VALUES, true)) {
                $values[$key] = $user[$value] ?? null;
            }
        }
    }
}