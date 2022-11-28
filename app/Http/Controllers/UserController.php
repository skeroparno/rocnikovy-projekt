<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Facades\Socialite;
use Termwind\Components\Dd;

class UserController extends Controller
{
    function RedirectToLoginPage()
    {
        return view('auth/login');
    }

    function RedirectToRegisterPage()
    {
        return view('auth/register');
    }

    function Register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:users',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'avatar' => 'avatars/default.png',
            'google_id' => null,
        ]);

        Auth::login($user);

        return redirect()->route('home');
    }

    function Login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:8',
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();

            if ($request->has('remember')) {
                Cookie::queue('remember_email', $request->email, 60 * 24 * 30);
                Cookie::queue('remember_password', $request->password, 60 * 24 * 30);
            }

            return redirect()->route('home');
        }
    }

    function Logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    function RedirectToProfilePage()
    {
        return view('profile');
    }

    function UpdateProfile(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:users,name,' . Auth::user()->id,
            'avatar' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'bio' => 'string|max:500',
        ]);

        $user = User::find(Auth::user()->id);

        if ($request->hasFile('avatar')) {
            if (Auth::user()->avatar != 'avatars/default.png') {
                Storage::disk('public')->delete(Auth::user()->avatar);
            }
            $avatar = $request->file('avatar');
            $avatarName = time() . '.' . $avatar->getClientOriginalExtension();
            $avatarPath = $avatar->storeAs('avatars', $avatarName, 'public');
            $user->avatar = $avatarPath;
        }

        $user->name = $request->name;
        $user->bio = $request->bio;
        $user->save();

        sleep(1);
        return redirect()->route('profile');
    }

    function RedirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    function GoogleCallback()
    {
        $user = Socialite::driver('google')->stateless()->user();

        $existingUser = User::where('google_id', $user->id)->first();

        if ($existingUser) {
            auth()->login($existingUser, true);
        } else {
            $newUser = new User;
            $newUser->name = $user->name;
            $newUser->email = $user->email;
            $newUser->password = Hash::make('password');
            $newUser->google_id = $user->id;
            Storage::disk('public')->put('/avatars/.' . $user->id . '.jpg', file_get_contents($user->avatar));
            $newUser->avatar = 'avatars/.' . $user->id . '.jpg';
            $newUser->save();

            auth()->login($newUser, true);
        }

        return redirect()->route('home');
    }

    function DeleteProfile(Request $request)
    {
        $user = User::find(Auth::user()->id);

        $avatar = $user->avatar;
        if ($avatar != 'avatars/default.png') {
            Storage::disk('public')->delete($avatar);
        }

        $user->delete();

        Auth::logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        sleep(1);

        return redirect()->route('home');
    }
}
