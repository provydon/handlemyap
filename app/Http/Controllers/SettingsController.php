<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        return view('settings', [
            'rule' => $request->user()->autoApprovalRule,
            'tokens' => $request->user()->tokens()->latest()->get(),
            'plainToken' => session('plainToken'),
        ]);
    }

    public function updateRule(Request $request)
    {
        $data = $request->validate([
            'enabled' => 'nullable|boolean',
            'max_amount' => 'nullable|numeric|min:0',
            'known_vendors_only' => 'nullable|boolean',
            'block_duplicates' => 'nullable|boolean',
            'daily_cap' => 'nullable|numeric|min:0',
        ]);

        $request->user()->autoApprovalRule()->updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'enabled' => $request->boolean('enabled'),
                'max_amount' => $data['max_amount'] ?? null,
                'known_vendors_only' => $request->boolean('known_vendors_only'),
                'block_duplicates' => $request->boolean('block_duplicates'),
                'daily_cap' => $data['daily_cap'] ?? null,
            ],
        );

        return back()->with('status', 'Auto-approval rules saved.');
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
