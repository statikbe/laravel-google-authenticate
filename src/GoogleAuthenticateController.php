<?php

namespace Statikbe\GoogleAuthenticate;

use App\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Spatie\Permission\Models\Role;

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

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->redirectTo = config('google-auth.redirect_url');
    }

    public function getLogout()
    {
        $this->auth->logout();
        Session::flush();

        return redirect('/');
    }

    /**
     * Redirect the user to the Google authentication page.
     *
     * @return \Illuminate\Http\Response
     */
    public function redirectToProvider()
    {
        return Socialite::driver('google')->scopes(['openid', 'profile', 'email'])->redirect();
    }

    public function handleProviderCallback()
    {
        try {
            $user = Socialite::driver('google')->user();
            $authUser = $this->findOrCreate($user, 'google');
            Auth::login($authUser, true);

            return redirect($this->redirectTo)->with('success', __('laravel-google-authenticate::google-auth.success'));
        } catch (AuthenticationException $e) {
            return redirect('/')->with(['danger', __('laravel-google-authenticate::google-auth.unauthenticated')]);
        } catch (\Exception $e) {
            return redirect('/')->with(['danger', __('laravel-google-authenticate::google-auth.error')]);
        }
    }

    /**
     * If a user has registered before using social auth, return the user
     * else, create a new user object.
     *
     * @param  $user Socialite user object
     * @param $provider Socialite auth provider
     *
     * @return  User
     * @throws AuthenticationException
     */
    private function findOrCreate($user, $provider)
    {
        if (isset($user->email)) {

            $userData = $this->fillUserData($user);
            $userData['provider'] = $provider;
            $userData['provider_id'] = $user->id;

            $emailArray = explode('@', $user->email);
            $emailDomain = $emailArray[1];

            //if ($emailArray[1] !== env('AUTH_ROLE_DOMAIN')) {
            //    throw new AuthenticationException();
            //}

            $roles = config('google-auth.roles');
            foreach ($roles as $role => $domains) {

                //find first or create $roleModel
                $roleModel = ($role === 'no_role') ? null : Role::firstOrCreate(['name' => $role]);

                if ($domains) {
                    if (in_array($emailDomain, $domains)) {
                        $user = $this->createUser($userData, $roleModel);
                    }
                    continue;
                }

                $user = $this->createUser($userData, $roleModel);
                continue;
            }

            return $user;
        }

        throw new AuthenticationException();
    }

    /**
     * @param $user Socialite user object
     * @return array
     */
    private function fillUserData($user)
    {
        $columns = config('google-auth.user_columns');
        $user = $user->getRaw();

        $data = [];

        foreach ($columns as $googleData => $columnName) {
            if ($googleData === 'email_verified') {
                $data[$columnName] = ($user[$googleData]) ? now()->toDateTimeString() : null;

                continue;
            }
            $data[$columnName] = $user[$googleData];
        }

        return $data;
    }

    public function createUser($userData, $roleModel)
    {
        $user = User::updateOrCreate(['provider_id' => $userData['provider_id']] , $userData);

        //verify user
        if (!$user->email_verified_at && $userData['email_verified_at']){
            $user->email_verified_at = $userData['email_verified_at'];
            $user->save();
        }

        if ($roleModel) {
            $user->assignRole($roleModel);
        }

        return $user;
    }
}