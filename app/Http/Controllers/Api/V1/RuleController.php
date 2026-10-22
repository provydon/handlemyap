<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * The per-user autonomy dial. GET returns the current rule (or sensible
 * defaults); PUT upserts it.
 */
class RuleController extends Controller
{
    public function show(Request $request)
    {
        return $request->user()->autoApprovalRule
            ?? $request->user()->autoApprovalRule()->make([
                'enabled' => false,
                'known_vendors_only' => true,
                'block_duplicates' => true,
            ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'enabled' => 'boolean',
            'max_amount' => 'nullable|numeric|min:0',
            'known_vendors_only' => 'boolean',
            'block_duplicates' => 'boolean',
            'allowed_currencies' => 'nullable|array',
            'allowed_currencies.*' => 'string|size:3',
            'daily_cap' => 'nullable|numeric|min:0',
        ]);

        $rule = $request->user()->autoApprovalRule()->updateOrCreate(
            ['user_id' => $request->user()->id],
            $data,
        );

        return response()->json($rule);
    }
}
