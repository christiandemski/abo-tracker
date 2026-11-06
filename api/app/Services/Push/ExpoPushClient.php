<?php

namespace App\Services\Push;

use App\Models\PushToken;
use App\Models\Scopes\OwnedByCurrentUserScope;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Sends push messages via the Expo push service (forwards to FCM on Android).
 * https://docs.expo.dev/push-notifications/sending-notifications/
 */
class ExpoPushClient
{
    /** Expo accepts up to 100 messages per request. */
    private const CHUNK = 100;

    /**
     * @param  list<array{to: string, title: string, body: string, data?: array<string, mixed>}>  $messages
     * @return int number of messages accepted by Expo
     */
    public function send(array $messages): int
    {
        $accepted = 0;

        foreach (array_chunk($messages, self::CHUNK) as $chunk) {
            $payload = array_map(fn (array $m) => $m + ['sound' => 'default', 'priority' => 'high', 'channelId' => 'reminders'], $chunk);

            $request = Http::acceptJson()->asJson()->timeout(15)->retry(2, 500, throw: false);
            if ($token = config('services.expo.access_token')) {
                $request = $request->withToken($token);
            }

            $response = $request->post(config('services.expo.push_url'), $payload);

            if (! $response->successful()) {
                throw new RuntimeException('Expo push request failed with HTTP '.$response->status());
            }

            foreach ($response->json('data', []) as $index => $ticket) {
                if (($ticket['status'] ?? null) === 'ok') {
                    $accepted++;

                    continue;
                }

                $error = $ticket['details']['error'] ?? null;
                if ($error === 'DeviceNotRegistered') {
                    // App uninstalled or token revoked: forget the device.
                    PushToken::query()
                        ->withoutGlobalScope(OwnedByCurrentUserScope::class)
                        ->where('expo_token', $chunk[$index]['to'])
                        ->delete();
                } else {
                    Log::warning('Expo push ticket error', ['error' => $error, 'message' => $ticket['message'] ?? null]);
                }
            }
        }

        return $accepted;
    }

    public static function isValidToken(string $token): bool
    {
        return (bool) preg_match('/^(ExponentPushToken|ExpoPushToken)\[[A-Za-z0-9_-]+\]$/', $token);
    }
}
