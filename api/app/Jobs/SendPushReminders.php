<?php

namespace App\Jobs;

use App\Models\PushToken;
use App\Models\Scopes\OwnedByCurrentUserScope;
use App\Services\Push\ExpoPushClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Sends prepared push messages to all devices of one user.
 */
class SendPushReminders implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    /**
     * @param  list<array{title: string, body: string, data: array<string, mixed>}>  $messages
     * @param  list<int>  $sentReminderIds
     */
    public function __construct(
        public readonly int $userId,
        public readonly array $messages,
        public readonly array $sentReminderIds,
    ) {}

    public function handle(ExpoPushClient $client): void
    {
        $tokens = PushToken::query()
            ->withoutGlobalScope(OwnedByCurrentUserScope::class)
            ->where('user_id', $this->userId)
            ->pluck('expo_token');

        if ($tokens->isEmpty()) {
            return;
        }

        $payload = [];
        foreach ($tokens as $token) {
            foreach ($this->messages as $message) {
                $payload[] = ['to' => $token] + $message;
            }
        }

        if ($client->send($payload) > 0) {
            DB::table('sent_reminders')->whereIn('id', $this->sentReminderIds)->update(['sent_at' => now()]);
        }
    }
}
