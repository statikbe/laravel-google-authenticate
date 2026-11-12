<?php

namespace Statikbe\GoogleAuthenticate;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\AbstractUser;
use Statikbe\GoogleAuthenticate\Events\GoogleAccountLinked;
use Statikbe\GoogleAuthenticate\Events\GoogleUserRegistered;
use Statikbe\GoogleAuthenticate\Exceptions\GoogleAuthenticationException;

class GoogleAuthService
{
    protected Model $userModel;

    /**
     * Run every security check, then find or create the user. Final so a subclass cannot skip the checks.
     *
     * @throws GoogleAuthenticationException|\Throwable
     */
    final public function authenticate(AbstractUser $googleUser): Model
    {
        try {
            return $this->loginTransaction($googleUser);
        } catch (GoogleAuthenticationException $e) {
            // The sub identifies the Google account without putting the email in the log.
            Log::warning('google-authenticate: login refused', ['sub' => $googleUser->getId(), 'reason' => $e->getMessage()]);

            throw $e;
        }
    }

    /**
     * @throws GoogleAuthenticationException|\Throwable
     */
    private function loginTransaction(AbstractUser $googleUser): Model
    {
        $hostedDomain = $this->assertLoginAllowed($googleUser);

        // Same transaction as the row: a throwing listener must roll the new user back, otherwise the
        // next login is no longer "recently created" and the listener's work is lost for good.
        // Auth::login comes after the commit, so a failed listener also leaves the visitor logged out.
        return $this->getUserModel()->getConnection()->transaction(function () use ($googleUser, $hostedDomain): Model {
            $authUser = $this->resolveUser($googleUser);

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
     * The fail-closed privilege rule: nobody is allowed while `domains.allowed` is empty. Deliberately not used by
     * assertLoginAllowed() or the D9 guard, where an empty list means something else. A null hosted domain checks
     * the email domain only, for callers without a Google profile; '' (a personal account) never passes.
     */
    public function isAllowedDomain(string $email, ?string $hostedDomain = null): bool
    {
        $parts = explode('@', $email);
        $domains = config('google-authenticate.domains', []);
        $allowed = array_map('strtolower', $domains['allowed'] ?? []);
        $disabled = array_map('strtolower', $domains['disabled'] ?? []);

        if (count($parts) !== 2 || ! $allowed) {
            return false;
        }

        $emailDomain = strtolower($parts[1]);

        return ! in_array($emailDomain, $disabled, true)
            && in_array($emailDomain, $allowed, true)
            && ($hostedDomain === null || in_array(strtolower($hostedDomain), $allowed, true));
    }

    /**
     * Every check that decides whether this Google account may enter at all. It is private and
     * called from authenticate(), so a subclass cannot drop it.
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

        if ($googleUser->getEmail() === null) {
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
    private function linkExisting(Model $user, AbstractUser $googleUser): void
    {
        $emailDomain = $this->emailDomain($googleUser);
        $hostedDomain = (string) ($googleUser->getRaw()['hd'] ?? '');

        // Only Google itself is authoritative for gmail.com addresses; any other domain needs the hd claim as proof of ownership.
        $owned = ($hostedDomain !== '' && strcasecmp($hostedDomain, $emailDomain) === 0)
            || in_array($emailDomain, ['gmail.com', 'googlemail.com'], true);

        if (! $owned) {
            throw new GoogleAuthenticationException('E-mail domain ownership not proven');
        }

        $user->forceFill(['provider' => 'google', 'provider_id' => $googleUser->id]);

        // A password or remember token set before the first Google login could belong to an attacker who pre-registered the address.
        if (array_key_exists('password', $user->getAttributes())) {
            $user->forceFill(['password' => null]);
        }

        if ($user instanceof Authenticatable && array_key_exists($user->getRememberTokenName(), $user->getAttributes())) {
            $user->setRememberToken(Str::random(60));
        }
    }

    /**
     * One lookup for both register modes. The security checks already ran in authenticate().
     *
     * @throws GoogleAuthenticationException
     */
    private function resolveUser(AbstractUser $googleUser): Model
    {
        $model = $this->getUserModel();
        $data = $this->mappedColumns($googleUser);
        $registerEnabled = (bool) config('google-authenticate.register_enabled', true);

        $user = $this->findByGoogleId($googleUser);

        if ($user) {
            if ($registerEnabled) {
                $this->syncColumns($user, $data);
                $this->assertEmailNotTakenByAnotherRow($user);
                $user->save();
            }

            return $user;
        }

        $user = $this->whereEmail($model->newQuery(), (string) $googleUser->email)->whereNull('provider_id')->first();

        if ($user) {
            if ($registerEnabled) {
                $this->syncColumns($user, $data);
            }

            $this->linkExisting($user, $googleUser);
            $user->save();

            // Inside the transaction, so a throwing listener rolls the link back too.
            event(new GoogleAccountLinked($user, $googleUser));

            return $user;
        }

        // never create a duplicate of, or take over, an email that belongs to another identity (this one was not found above)
        $owner = $this->whereEmail($model->newQuery(), (string) $googleUser->email)->whereNotNull('provider_id')->first();

        if ($owner) {
            $provider = $owner->getAttribute('provider');

            throw new GoogleAuthenticationException($provider === null || $provider === 'google'
                ? 'E-mail linked to another Google account'
                : 'E-mail linked to another account');
        }

        if (! $registerEnabled) {
            throw new GoogleAuthenticationException('No user found.');
        }

        $this->assertOpenRegistrationIntended();

        $user = $model->newInstance();
        $this->syncColumns($user, $data);

        try {
            // A nested transaction is a savepoint: Postgres aborts the whole transaction on a failed statement.
            $model->getConnection()->transaction(
                // provider and provider_id are set explicitly (forceFill), independent of $fillable
                fn () => $user->forceFill(['provider' => 'google', 'provider_id' => $googleUser->id])->save()
            );
        } catch (UniqueConstraintViolationException $e) {
            // A parallel callback for the same Google account won the race: use its row.
            // Locking read: a plain SELECT reuses the old REPEATABLE READ snapshot on InnoDB and would not see the committed row.
            return $this->findByGoogleId($googleUser, lock: true) ?? throw $e;
        }

        return $user;
    }

    private function findByGoogleId(AbstractUser $googleUser, bool $lock = false): ?Model
    {
        return $this->getUserModel()->newQuery()
            ->when($lock, fn (Builder $query) => $query->lockForUpdate())
            // NULL provider: rows from older installs or imports. Another provider's ids never match.
            ->where(fn (Builder $query) => $query->where('provider', 'google')->orWhereNull('provider'))
            ->where('provider_id', $googleUser->id)
            ->first();
    }

    /**
     * The column `user_columns` maps the Google email to, 'email' by default.
     */
    private function emailColumn(): string
    {
        $column = array_search(['email'], config('google-authenticate.user_columns', []), true);

        return is_string($column) ? $column : 'email';
    }

    /**
     * Case-insensitive on every database: Postgres and SQLite compare case-sensitively by default.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    private function whereEmail(Builder $query, string $email): Builder
    {
        $column = $query->getQuery()->getGrammar()->wrap($this->emailColumn());

        return $query->whereRaw("LOWER({$column}) = ?", [strtolower($email)]);
    }

    /**
     * A synced Google email must not collide with another row, or two accounts end up on one address.
     *
     * @throws GoogleAuthenticationException
     */
    private function assertEmailNotTakenByAnotherRow(Model $user): void
    {
        $column = $this->emailColumn();

        if ($user->isDirty($column)
            && $this->whereEmail($user->newQuery(), (string) $user->getAttribute($column))->whereKeyNot($user->getKey())->exists()) {
            throw new GoogleAuthenticationException('E-mail linked to another account');
        }
    }

    /**
     * An empty allow-list with registration on would let any Google account create a user. Refuse unless that is
     * declared on purpose. Existing users are not affected, this only guards the create branch.
     *
     * @throws GoogleAuthenticationException
     */
    private function assertOpenRegistrationIntended(): void
    {
        $domains = config('google-authenticate.domains', []);

        if (! ($domains['allowed'] ?? []) && ! ($domains['allow_any'] ?? false)) {
            // At most once an hour: every refused visitor would otherwise flood the log and the error tracker.
            if (Cache::add('google-authenticate:open-registration-reported', true, 3600)) {
                report(new \LogicException('google-authenticate: register_enabled is on but domains.allowed is empty. Set GOOGLE_AUTH_ALLOWED_DOMAINS, or GOOGLE_AUTH_ALLOW_ANY_DOMAIN=true for a public app.'));
            }

            throw new GoogleAuthenticationException(__('google-authenticate::messages.unauthenticated'));
        }
    }

    /**
     * Fill the mapped columns and mark the email verified, but never overwrite an existing verification date.
     *
     * @param  array<string, string|null>  $data
     */
    private function syncColumns(Model $user, array $data): void
    {
        $user->fill(Arr::except($data, ['provider', 'provider_id', 'email_verified_at']));

        if (! $user->getAttribute('email_verified_at') && ($data['email_verified_at'] ?? null)) {
            $user->setAttribute('email_verified_at', $data['email_verified_at']);
        }
    }

    /**
     * Resolve `user_columns` against the Google payload. Only the known claim names are looked up
     * (missing claim = ''), any other value (punctuation, words) is glued in literally.
     *
     * @return array<string, string|null>
     */
    private function mappedColumns(AbstractUser $googleUser): array
    {
        $raw = $googleUser->getRaw();
        $data = [];

        foreach (config('google-authenticate.user_columns', []) as $column => $values) {
            $data[$column] = implode('', array_map(function (string $value) use ($raw): ?string {
                if ($value === 'email_verified') {
                    return filter_var(data_get($raw, $value, false), FILTER_VALIDATE_BOOLEAN) ? now()->toDateTimeString() : null;
                }

                return in_array($value, ['name', 'email', 'given_name', 'family_name', 'picture', 'nickname', 'locale'], true)
                    ? data_get($raw, $value)
                    : $value;
            }, $values));
        }

        return $data;
    }
}
