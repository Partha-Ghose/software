<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Model\Setting\SocialCreadential;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\Request;
use App\User;
use Auth, File, Session;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Socialite;

class LoginController extends Controller
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

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    public function login(Request $request)
    {
      // Validate the form data
      $this->validate($request, [
        'phone'   => 'required',
        'password' => 'required'
      ]);
      
      // Attempt to log the user in
      if (Auth::attempt(['phone' => $request->phone, 'password' => $request->password,'status' => 1], $request->remember)) {
        // if successful, then redirect to their intended location
        return redirect()->intended(route('user.profile'));
      } 
      // if unsuccessful, then redirect back to the login with the form data
      return redirect()->back()
      ->with('error', 'Wrong Credentials or you are deactivated !')
      ->withInput($request->only('phone', 'remember'));
    }

    public function redirectToProvider($provider)
    {
        return Socialite::driver($provider)->redirect();
    }

    public function showLoginForm()
    {
        $social_provider = SocialCreadential::select('id', 'provider')
                           ->where('status', '=', 1)
                           ->get();
        return view('auth.login',['social_provider' => $social_provider]);
    }

   public function authenticated(Request $request, $user)
    {
        return redirect()->intended('profile');
    }

    public function handleProviderCallback($provider)
    {
        try {
            $userSocial = Socialite::driver($provider)->stateless()->user();
            $user = User::where('email', $userSocial->getEmail())->first();
            if ($user) {
                Auth::login($user);
                return redirect()->intended('profile');
            } else {
                
                $user = User::create([
                    'name' => $userSocial->getName(),
                    'email' => $userSocial->getEmail(),
                ]);
                Auth::login($user);
                // return redirect($this->redirectTo);
                return redirect()->intended('profile');
            }
            
        } catch (\Exception $e) {
            Session::flash('error','Something went wrong!');
            return redirect()->back();
        }
    }

}
