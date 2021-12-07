<?php

namespace Statikbe\GoogleAuthenticate;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
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

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/';

    const GOOGLE_VALUES = [
        'name',
        'email_verified',
        'email',
        'given_name',
        'family_name',
        'picture',
        'nickname',
        'locale',
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

        $userNamespace = config('auth.providers.users.model');
        $this->userModel = new $userNamespace;
    }

    /**
     * Redirect the user to the Google authentication page.
     *
     * @return \Illuminate\Http\Response
     */
    public function redirectToProvider()
    {
        request()->session()->flash('googleLoginUrl', url()->previous());

        return Socialite::driver('google')->scopes(['openid', 'profile', 'email'])->redirect();
    }

    public function handleProviderCallback()
    {
        $loginUrl = session('googleLoginUrl') ?? '/';

        try {
            $user = Socialite::driver('google')->user();
            $authUser = $this->findOrCreate($user, 'google');
            Auth::login($authUser, true);

            return Redirect::to($this->redirectTo)->with('success', __('google-authenticate::messages.success'));
        } catch (GoogleAuthenticationException $e) {
            return Redirect::to($loginUrl)->with(['danger' => __('google-authenticate::messages.unauthenticated')]);
        } catch (InvalidStateException $e) {
            return Redirect::to($loginUrl)->with(['danger' => __('google-authenticate::messages.error')]);
        }
    }

    /**
     * If a user has registered before using social auth, return the user
     * else, create a new user object.
     *
     * @param AbstractUser $googleUser
     * @param string $provider
     *
     * @return  User
     * @throws GoogleAuthenticationException
     */
    private function findOrCreate(AbstractUser $googleUser, string $provider)
    {
        if (isset($googleUser->email)) {
            //make userFillableArray
            $userData = $this->fillUserData($googleUser);
            $userData['provider'] = $provider;
            $userData['provider_id'] = $googleUser->id;

            //get user's mail domain
            $emailArray = explode('@', $googleUser->email);
            $emailDomain = $emailArray[1];

            //retrieve roles from config and loop them
            $domains = config('google-authenticate.domains', null);
            if (!empty($domains)) {
                //If the disabled array is filled we check the domain against it
                $domainsToIgnore = $domains['disabled'] ?? null;
                if ($domainsToIgnore) {
                    if (in_array($emailDomain, $domainsToIgnore)) {
                        throw new GoogleAuthenticationException();
                    }
                }

                //If the allowed array is filled we check the domain against it
                $domainsToValidate = $domains['allowed'] ?? null;
                if (!empty($domainsToValidate)) {
                    if (in_array($emailDomain, $domainsToValidate)) {
                        return $this->createUser($userData);
                    }
                    throw new GoogleAuthenticationException();
                }
            }

            //If no domain stuff is triggered we create a user
            return $this->createUser($userData);
        }

        throw new GoogleAuthenticationException();
    }

    /**
     * @param AbstractUser $user
     * @return array $data
     */
    private function fillUserData(AbstractUser $user)
    {
        //get user table columns
        $columns = config('google-authenticate.user_columns', []);
        $user = $user->getRaw();
        $data = [];

        foreach ($columns as $columnName => $values) {
            //check for google values
            $this->checkForGoogleData($values, $user);

            //implode values and add them to the correct column
            $data[$columnName] = implode('', $values);
        }

        return $data;
    }

    /**
     * @param array $userData
     * @param Role $roleModel
     * @return User $user
     */
    private function createUser($userData)
    {
        //search for possible user with this email but without google provider
        $user = $this->userModel::where('email', $userData['email'])->whereNull('provider_id')->first();
        if ($user) {
            //filling found user
            $user->update($userData);
        } else {
            //update or create user and return it
            $user = $this->userModel::updateOrCreate(['provider_id' => $userData['provider_id']], $userData);
        }

        //verify user
        if (!$user->email_verified_at && $userData['email_verified_at']) {
            $user->email_verified_at = $userData['email_verified_at'];
            $user->save();
        }

        return $user;
    }

    /**
     * @param array $values
     * @param $user Socialite user object
     */
    private function checkForGoogleData(&$values, $user)
    {
        //loop values provided from configInvalidStateException
        foreach ($values as $key => $value) {

            //if email_verified make sure it returns a datetime
            if ($value === 'email_verified') {
                $values[$key] = ($user[$value]) ? now()->toDateTimeString() : null;
                continue;
            }

            //if value found in google_values array, return it's google value
            if (in_array($value, self::GOOGLE_VALUES)) {
                $values[$key] = $user[$value];
                continue;
            }
        }
    }
}
