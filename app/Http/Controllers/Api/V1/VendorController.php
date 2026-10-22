<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()->vendors()->latest()->limit(500)->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'country' => 'nullable|string|size:2',
            'currency' => 'nullable|string|size:3',
            'external_ref' => 'nullable|string|max:255',
            'metadata' => 'nullable|array',
        ]);

        return response()->json($request->user()->vendors()->create($data), 201);
    }
}
