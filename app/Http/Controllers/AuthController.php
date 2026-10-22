<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'Those credentials do not match.']);
        }

        $request->session()->regenerate();

        return redirect()->intended('/app');
    }

    /** One-click sign-in to the seeded demo account (for the landing page). */
    public function demoLogin(Request $request)
    {
        $user = \App\Models\User::where('email', 'demo@handlemyap.test')->first();

        if (! $user) {
            return redirect('/login');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/app');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
