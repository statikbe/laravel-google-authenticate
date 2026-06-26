<?php

namespace Statikbe\GoogleAuthenticate;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
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

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->redirectTo = config('google-authenticate.redirect_url');
    }

    /**
     * Redirect the user to the Google authentication page.
     */
    public function redirectToProvider(): RedirectResponse
    {
        request()->session()->flash('googleLoginUrl', url()->previous());

        return Socialite::driver('google')->scopes(['openid', 'profile', 'email'])->redirect();
    }

    public function handleProviderCallback(): RedirectResponse
    {
        $loginUrl = session('googleLoginUrl') ?? '/';

        if (request()->has('error')) {
            return Redirect::to($loginUrl)->with(['danger' => __('google-authenticate::messages.error')]);
        }

        try {
            $user = Socialite::driver('google')->user();
            $authUser = $this->findOrCreate($user, 'google');
            Auth::login($authUser, true);

            return Redirect::to($this->redirectTo)->with('success', __('google-authenticate::messages.success'));
        } catch (GoogleAuthenticationException) {
            return Redirect::to($loginUrl)->with(['danger' => __('google-authenticate::messages.unauthenticated')]);
        } catch (InvalidStateException) {
            return Redirect::to($loginUrl)->with(['danger' => __('google-authenticate::messages.error')]);
        }
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
     * If a user has registered before using social auth, return the user
     * else, create a new user object.
     *
     *
     * @throws GoogleAuthenticationException
     */
    private function findOrCreate(AbstractUser $googleUser, string $provider): User
    {
        if (isset($googleUser->email)) {
            // make userFillableArray
            $userData = $this->fillUserData($googleUser);
            $userData['provider'] = $provider;
            $userData['provider_id'] = $googleUser->id;
            $emailVerified = (bool) ($googleUser->getRaw()['email_verified'] ?? false);

            // get user's mail domain
            $emailParts = explode('@', $googleUser->email);
            if (count($emailParts) !== 2) {
                throw new GoogleAuthenticationException;
            }
            $emailDomain = $emailParts[1];

            // retrieve roles from config and loop them
            $domains = config('google-authenticate.domains', null);
            if (! empty($domains)) {
                // If the disabled array is filled we check the domain against it
                $domainsToIgnore = $domains['disabled'] ?? null;
                if ($domainsToIgnore) {
                    if (in_array($emailDomain, $domainsToIgnore, true)) {
                        throw new GoogleAuthenticationException;
                    }
                }

                // If the allowed array is filled we check the domain against it
                $domainsToValidate = $domains['allowed'] ?? null;
                if (! empty($domainsToValidate)) {
                    if (in_array($emailDomain, $domainsToValidate, true)) {
                        return $this->createUser($userData, $emailVerified);
                    }
                    throw new GoogleAuthenticationException;
                }
            }

            // If no domain stuff is triggered we create a user
            return $this->createUser($userData, $emailVerified);
        }

        throw new GoogleAuthenticationException;
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
    private function createUser(array $userData, bool $emailVerified): User
    {

        // Extract email_verified_at before mass update to handle it separately
        $emailVerifiedAt = $userData['email_verified_at'] ?? null;
        unset($userData['email_verified_at']);

        //search for possible user with this email but without Google provider

        $user = $this->getUserModel()::where('email', $userData['email'])->whereNull('provider_id')->first();
        if ($user) {
            if (! $emailVerified) {
                throw new GoogleAuthenticationException;
            }
            $user->update($userData);
        } else {
            // update or create user and return it
            $user = $this->getUserModel()::updateOrCreate(['provider_id' => $userData['provider_id']], $userData);
        }

        //verify user - only set if not already verified
        if (!$user->email_verified_at && $emailVerifiedAt) {
            $user->email_verified_at = $emailVerifiedAt;

            $user->save();
        }

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
                $values[$key] = ($user[$value]) ? now()->toDateTimeString() : null;

                continue;
            }

            // if value found in google_values array, return it's google value
            if (in_array($value, self::GOOGLE_VALUES, true)) {
                $values[$key] = $user[$value];
            }
        }
    }
}