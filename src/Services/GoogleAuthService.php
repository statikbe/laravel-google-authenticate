<?php

namespace Statikbe\GoogleAuthenticate\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laravel\Socialite\AbstractUser;
use Statikbe\GoogleAuthenticate\Events\GoogleUserRegistered;
use Statikbe\GoogleAuthenticate\Exceptions\GoogleAuthenticationException;

class GoogleAuthService
{
    public const GOOGLE_VALUES = [
        'name', 'email_verified', 'email', 'given_name', 'family_name', 'picture', 'nickname', 'locale',
    ];

    protected Model $userModel;

    protected bool $registerEnabled = false;

    public function __construct()
    {
        $this->registerEnabled = (bool) config('google-authenticate.register_enabled', true);
    }

    /**
     * Run every security check, then find or create the user. Final so a subclass cannot skip the checks.
     *
     * @throws GoogleAuthenticationException|\Throwable
     */
    final public function authenticate(AbstractUser $googleUser): User
    {
        $hostedDomain = $this->assertLoginAllowed($googleUser);

        // Same transaction as the row: a throwing listener must roll the new user back, otherwise the
        // next login is no longer "recently created" and the listener's work is lost for good.
        // Auth::login comes after the commit, so a failed listener also leaves the visitor logged out.
        return (new User)->getConnection()->transaction(function () use ($googleUser, $hostedDomain): User {
            $authUser = $this->findOrCreate($googleUser, 'google');

            if ($authUser->wasRecentlyCreated) {
                event(new GoogleUserRegistered($authUser, $googleUser, $hostedDomain));
            }

            return $authUser;
        });
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
     * called from authenticate(), so overriding findOrCreate/createUser cannot drop it.
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
     * The security checks already ran in authenticate().
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
     * @throws GoogleAuthenticationException
     */
    protected function createUser(array $userData, AbstractUser $googleUser): User
    {
        // provider and provider_id are set explicitly (forceFill), independent of $fillable
        $identity = Arr::only($userData, ['provider', 'provider_id']);
        $emailVerifiedAt = $userData['email_verified_at'] ?? null;
        $attributes = Arr::except($userData, ['provider', 'provider_id', 'email_verified_at']);

        // search for possible user with this email but without Google provider
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

        // verify user - only set if not already verified
        if (! $user->email_verified_at && $emailVerifiedAt) {
            $user->email_verified_at = $emailVerifiedAt;
        }

        $user->save();

        return $user;
    }

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
