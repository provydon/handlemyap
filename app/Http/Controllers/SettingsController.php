<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    public function updateHandle(Request $request)
    {
        $data = $request->validate([
            'handle' => [
                'required', 'string', 'max:40', 'regex:/^[a-z0-9][a-z0-9-]*$/',
                Rule::unique('users', 'handle')->ignore($request->user()->id),
            ],
        ]);

        $request->user()->update(['handle' => $data['handle']]);

        return back()->with('status', 'Your intake link was updated.');
    }

    public function updateCurrency(Request $request)
    {
        $data = $request->validate([
            'default_currency' => 'required|string|size:3',
        ]);

        $request->user()->update(['default_currency' => strtoupper($data['default_currency'])]);

        return back()->with('status', 'Default currency updated.');
    }

    public function index(Request $request)
    {
        return view('settings', [
            'tokens' => $request->user()->tokens()->latest()->get(),
            'plainToken' => session('plainToken'),
        ]);
    }

    public function createToken(Request $request)
    {
        $request->validate(['name' => 'required|string|max:64']);
        $token = $request->user()->createToken($request->string('name'));

        // Show the plaintext token exactly once.
        return back()->with('plainToken', $token->plainTextToken);
    }

    public function deleteToken(Request $request, string $id)
    {
        $request->user()->tokens()->where('id', $id)->delete();

        return back()->with('status', 'Token revoked.');
    }
}
