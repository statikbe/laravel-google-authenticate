<?php

namespace Statikbe\GoogleAuthenticate;

use App\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Str;

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
     * @param  $googleUser Socialite user object
     * @param $provider Socialite auth provider
     *
     * @return  User
     * @throws AuthenticationException
     */
    private function findOrCreate($googleUser, $provider)
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
            $roles = config('google-auth.roles');
            foreach ($roles as $role => $domains) {

                //find ignorable domains and place them from the domains array to the domainsToIgnore array
                $domainsToIgnore = $this->cleanDomains($domains);

                //continue if the user domain is found inside the domainsToIgnore array
                if ($domainsToIgnore){
                    if (in_array($emailDomain, $domainsToIgnore)) {
                        continue;
                    }
                }

                //find first or create $roleModel
                $roleModel = ($role === 'no_role') ? null : Role::firstOrCreate(['name' => $role]);

                //if there are domains registered we need to check if the user domain is found in the array, otherwise continue
                if ($domains) {
                    if (in_array($emailDomain, $domains)) {
                        $user = $this->createUser($userData, $roleModel);
                    }
                    continue;
                }

                //if no continues above have been called a user will be created (for example if no domains were provided)
                $user = $this->createUser($userData, $roleModel);
                continue;
            }

            //return the found or created user or throw exception
            if ($user){
                return $user;
            }
        }

        throw new AuthenticationException();
    }

    /**
     * @param $user Socialite user object
     * @return array $data
     */
    private function fillUserData($user)
    {
        //get user table columns
        $columns = config('google-auth.user_columns');
        $user = $user->getRaw();
        $data = [];

        foreach ($columns as  $columnName => $values) {
            //check for google values
            $this->checkForGoogleData($values, $user);

            //implode values and add them to the correct column
            $data[$columnName] = implode('',$values);
        }

        return $data;
    }

    /**
     * @param array $userData
     * @param Role $roleModel
     * @return User $user
     */
    private function createUser($userData, $roleModel)
    {
        //search for possible user with this email but without google provider
        $user = User::where('email' , $userData['email'])->whereNull('provider_id')->first();
        if ($user){
            //filling found user
            $user->update($userData);
        } else {
            //update or create user and return it
            $user = User::updateOrCreate(['provider_id' => $userData['provider_id']] , $userData);
        }

        //verify user
        if (!$user->email_verified_at && $userData['email_verified_at']){
            $user->email_verified_at = $userData['email_verified_at'];
            $user->save();
        }

        //add role
        if ($roleModel) {
            $user->assignRole($roleModel);
        }

        return $user;
    }

    /**
     * @param array $domains
     * @return array $domainsToIgnore
     */
    private function cleanDomains(&$domains){
        $domainsToIgnore = [];
        if ($domains){
            foreach ($domains as $key => $domain){
                //find domains starting with !, remove them from domains array, add them to ignore list
                if (substr($domain, 0, 1 ) === "!"){
                    $domainsToIgnore[] = Str::replaceFirst('!', '',$domain);
                    unset($domains[$key]);
                }
            }
        }

        return $domainsToIgnore;
    }

    /**
     * @param array $values
     * @param $user Socialite user object
     */
    private function checkForGoogleData(&$values, $user){

        //loop values provided from config
        foreach ($values as $key => $value){

            //if email_verified make sure it returns a datetime
            if ($value === 'email_verified') {
                $values[$key] = ($user[$value]) ? now()->toDateTimeString() : null;
                continue;
            }

            //if value found in google_values array, return it's google value
            if (in_array($value,self::GOOGLE_VALUES)){
                $values[$key] = $user[$value];
                continue;
            }

        }
    }
}