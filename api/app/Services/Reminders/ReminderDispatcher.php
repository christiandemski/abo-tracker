<?php

namespace App\Services\Reminders;

use App\Jobs\SendPushReminders;
use App\Models\PushToken;
use App\Models\ReminderSetting;
use App\Models\Scopes\OwnedByCurrentUserScope;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\ReminderDigestNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;

/**
 * Finds due reminders for every user and queues mail + push.
 *
 * Idempotent: before anything is queued, a row per (subscription, type, due date, offset, channel)
 * is inserted into sent_reminders. Only rows that were newly inserted lead to a message,
 * so a second or delayed cron run sends nothing twice.
 */
class ReminderDispatcher
{
    public const CHANNEL_MAIL = 'mail';

    public const CHANNEL_PUSH = 'push';

    /** More push messages than this are combined into one summary push. */
    public const MAX_SINGLE_PUSHES = 3;

    public function __construct(private readonly ReminderPlanner $planner) {}

    /**
     * @return array{users: int, mails: int, pushes: int}
     */
    public function dispatch(?CarbonImmutable $today = null): array
    {
        $today = ($today ?? CarbonImmutable::today())->startOfDay();
        $stats = ['users' => 0, 'mails' => 0, 'pushes' => 0];

        User::query()->chunkById(100, function ($users) use ($today, &$stats) {
            foreach ($users as $user) {
                $result = $this->dispatchForUser($user, $today);
                $stats['users'] += $result['mail'] || $result['push'] ? 1 : 0;
                $stats['mails'] += $result['mail'] ? 1 : 0;
                $stats['pushes'] += $result['push'];
            }
        });

        return $stats;
    }

    /**
     * @return array{mail: bool, push: int}
     */
    public function dispatchForUser(User $user, CarbonImmutable $today): array
    {
        $settings = ReminderSetting::forUser($user);

        $subscriptions = Subscription::query()
            ->withoutGlobalScope(OwnedByCurrentUserScope::class)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->get();

        $due = $this->planner->plan($subscriptions, $settings, $today);
        if ($due === []) {
            return ['mail' => false, 'push' => 0];
        }

        $mailOn = $settings->email && $user->hasVerifiedEmail();
        $pushOn = $settings->push && PushToken::query()
            ->withoutGlobalScope(OwnedByCurrentUserScope::class)
            ->where('user_id', $user->id)
            ->exists();

        $result = ['mail' => false, 'push' => 0];

        if ($mailOn) {
            [$fresh, $ids] = $this->reserve($user, $due, self::CHANNEL_MAIL);
            if ($fresh !== []) {
                $user->notify(new ReminderDigestNotification(array_map(fn (DueReminder $r) => $this->mailItem($r), $fresh), $ids));
                $result['mail'] = true;
            }
        }

        if ($pushOn) {
            [$fresh, $ids] = $this->reserve($user, $due, self::CHANNEL_PUSH);
            if ($fresh !== []) {
                $messages = count($fresh) > self::MAX_SINGLE_PUSHES
                    ? [ReminderTexts::pushSummary($fresh) + ['data' => ['url' => '/']]]
                    : array_map(fn (DueReminder $r) => ReminderTexts::push($r) + [
                        'data' => ['url' => '/subscription/'.$r->subscription->id, 'subscription_id' => $r->subscription->id],
                    ], $fresh);

                SendPushReminders::dispatch($user->id, $messages, $ids);
                $result['push'] = count($messages);
            }
        }

        return $result;
    }

    /**
     * Inserts log rows; returns the reminders whose row was new (not sent before) and their ids.
     *
     * @param  list<DueReminder>  $due
     * @return array{0: list<DueReminder>, 1: list<int>}
     */
    private function reserve(User $user, array $due, string $channel): array
    {
        $fresh = [];
        $ids = [];

        foreach ($due as $reminder) {
            $row = [
                'user_id' => $user->id,
                'subscription_id' => $reminder->subscription->id,
                'type' => $reminder->type,
                'due_date' => $reminder->dueDate->toDateString(),
                'offset_days' => $reminder->offsetDays,
                'channel' => $channel,
                'created_at' => now(),
            ];

            $inserted = DB::table('sent_reminders')->insertOrIgnore($row);

            // Offsets covered by this reminder (catch-up) are logged, so they are not sent later.
            foreach ($reminder->alsoCovers as $offset) {
                DB::table('sent_reminders')->insertOrIgnore(['offset_days' => $offset] + $row);
            }

            if ($inserted === 1) {
                $fresh[] = $reminder;
                $ids[] = (int) DB::table('sent_reminders')
                    ->where('subscription_id', $row['subscription_id'])
                    ->where('type', $row['type'])
                    ->where('due_date', $row['due_date'])
                    ->where('offset_days', $row['offset_days'])
                    ->where('channel', $channel)
                    ->value('id');
            }
        }

        return [$fresh, $ids];
    }

    /**
     * @return array{type: string, subscription_id: int, name: string, due_date: string, days_left: int, amount: string, term_end_date: ?string}
     */
    private function mailItem(DueReminder $r): array
    {
        $s = $r->subscription;

        return [
            'type' => $r->type,
            'subscription_id' => $s->id,
            'name' => $s->name,
            'due_date' => $r->dueDate->toDateString(),
            'days_left' => $r->daysLeft,
            // Mail may show amounts (push may not).
            'amount' => Number::currency($s->amount_cents / 100, 'EUR', 'de'),
            'term_end_date' => $r->isDeadline() ? $s->term_end_date?->toDateString() : null,
        ];
    }
}
