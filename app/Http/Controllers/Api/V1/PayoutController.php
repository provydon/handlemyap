<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PayoutController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()->payouts()->with('vendor')->latest()->limit(200)->get();
    }

    public function show(Request $request, string $payout)
    {
        return $request->user()->payouts()->with(['vendor', 'invoice'])->findOrFail($payout);
    }
}
