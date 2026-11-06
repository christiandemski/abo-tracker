<?php

namespace App\Http\Controllers;

use App\Models\PushToken;
use App\Models\Scopes\OwnedByCurrentUserScope;
use App\Services\Push\ExpoPushClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PushTokenController extends Controller
{
    /**
     * Registers the device of the logged-in user. A token that belonged to someone else
     * (same phone, other account) moves to the current user.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:255', function (string $attribute, mixed $value, \Closure $fail) {
                if (! ExpoPushClient::isValidToken((string) $value)) {
                    $fail('Das ist kein gültiges Expo-Push-Token.');
                }
            }],
            'device_name' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $token = PushToken::query()
            ->withoutGlobalScope(OwnedByCurrentUserScope::class)
            ->firstOrNew(['expo_token' => $data['token']]);

        $created = ! $token->exists;
        $token->user_id = $request->user()->id;
        $token->device_name = $data['device_name'] ?? $token->device_name;
        $token->last_seen_at = now();
        $token->save();

        return response()->json(['data' => [
            'token' => $token->expo_token,
            'device_name' => $token->device_name,
        ]], $created ? 201 : 200);
    }

    public function destroy(string $token): Response
    {
        // Scoped to the current user: other users' tokens are never touched.
        PushToken::query()->where('expo_token', $token)->delete();

        return response()->noContent();
    }
}
