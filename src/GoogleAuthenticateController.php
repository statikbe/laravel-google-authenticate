<?php

namespace Statikbe\GoogleAuthenticate;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Statikbe\GoogleAuthenticate\Exceptions\GoogleAuthenticationException;
use Statikbe\GoogleAuthenticate\Services\GoogleAuthService;

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
        request()->session()->flash('googleLoginUrl', $this->sanitiseLoginUrl(url()->previous()));

        return Socialite::driver('google')->scopes(['openid', 'profile', 'email'])->redirect();
    }

    public function handleProviderCallback(GoogleAuthService $service): RedirectResponse
    {
        $sessionUrl = session('googleLoginUrl');
        $loginUrl = $this->sanitiseLoginUrl(is_string($sessionUrl) ? $sessionUrl : null);

        if (request()->has('error')) {
            return Redirect::to($loginUrl)->with(['danger' => __('google-authenticate::messages.error')]);
        }

        try {
            $googleUser = Socialite::driver('google')->user();
            $authUser = $service->authenticate($googleUser);

            Auth::login($authUser, (bool) config('google-authenticate.remember', false));
            request()->session()->regenerate();

            return Redirect::to($this->redirectTo)->with('success', __('google-authenticate::messages.success'));
        } catch (GoogleAuthenticationException) {
            return Redirect::to($loginUrl)->with(['danger' => __('google-authenticate::messages.unauthenticated')]);
        } catch (InvalidStateException|\Throwable) {
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
}
