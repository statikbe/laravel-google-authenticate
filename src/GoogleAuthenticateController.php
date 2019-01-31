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
        //$this->middleware('auth');
        $this->middleware('guest')->except('logout');
        $this->redirectTo = env('AUTH_REDIRECT_URL');
    }
    
    public function getLogout()
    {
        $this->auth->logout();
        Session::flush();
        return redirect('/');
    }
    
    /**
     * Redirect the user to the GitHub authentication page.
     *
     * @return \Illuminate\Http\Response
     */
    public function redirectToProvider()
    {
        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }
    
    public function handleProviderCallback()
    {
        try {
            $user = Socialite::driver('google')->user();
            $authUser = $this->findOrCreateUser($user, 'google');
            Auth::login($authUser, true);
            
            return redirect($this->redirectTo)->with('success', 'Successfully logged in with your Google Account.');
            
        } catch (AuthenticationException $e) {
            return redirect('/')->withErrors(['msg', 'You are not authorized to use Google Login!']);
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
    public function findOrCreateUser($user, $provider)
    {
        $authUser = User::where('provider_id', $user->id)->first();
        
        if ($authUser) {
            return $authUser;
        }
        
        
        if (isset($user->email)) {
            $emailArray = explode('@', $user->email);
            if ($emailArray[1] == env('AUTH_ROLE_DOMAIN')) {
                $user =  User::create([
                    'name'     => $user->name,
                    'email'    => $user->email,
                    'provider' => $provider,
                    'provider_id' => $user->id
                ]);
                
                $adminRole = Role::where('name', env('AUTH_ROLE_ADMIN'))->first();
                if (!$adminRole) {
                    $adminRole = Role::create(['name' => env('AUTH_ROLE_ADMIN')]);
                }
                $user->assignRole($adminRole);
                
                return $user;
            }
            throw new AuthenticationException();
        }
        
    }
    
}