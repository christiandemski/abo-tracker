<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /** Name and postal address (sender of cancellation letters). */
    public function update(Request $request): UserResource
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'address_street' => ['sometimes', 'nullable', 'string', 'max:150'],
            'address_zip' => ['sometimes', 'nullable', 'string', 'max:10'],
            'address_city' => ['sometimes', 'nullable', 'string', 'max:100'],
        ], [], [
            'address_street' => 'Straße',
            'address_zip' => 'PLZ',
            'address_city' => 'Ort',
        ]);

        $request->user()->update($data);

        return UserResource::make($request->user());
    }
}
