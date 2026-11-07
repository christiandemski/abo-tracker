<?php

namespace App\Notifications;

use App\Support\FrontendUrl;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;

/**
 * One mail per user and run with all due reminders.
 *
 * Items are passed as plain arrays (not models), so the queued job does not depend on later changes.
 */
class ReminderDigestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  list<array{type: string, subscription_id: int, name: string, due_date: string, days_left: int, amount: string, term_end_date: ?string}>  $items
     * @param  list<int>  $sentReminderIds
     */
    public function __construct(
        public readonly array $items,
        public readonly array $sentReminderIds,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $deadlines = array_values(array_filter($this->items, fn ($i) => $i['type'] === 'deadline'));
        $charges = array_values(array_filter($this->items, fn ($i) => $i['type'] === 'charge'));

        $subject = match (true) {
            count($deadlines) === 1 && $charges === [] => "Kündigungsfrist: {$deadlines[0]['name']} bis ".$this->date($deadlines[0]['due_date']),
            count($deadlines) > 1 => count($deadlines).' Kündigungsfristen stehen an',
            count($charges) === 1 => "Morgen wird {$charges[0]['name']} abgebucht",
            default => count($charges).' Abbuchungen morgen',
        };

        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting("Hallo {$notifiable->name}!");

        if ($deadlines !== []) {
            $mail->line(count($deadlines) === 1
                ? 'Eine Kündigungsfrist endet bald. Wenn du das Abo nicht mehr brauchst, kündige rechtzeitig:'
                : 'Diese Kündigungsfristen enden bald. Wenn du ein Abo nicht mehr brauchst, kündige rechtzeitig:');

            foreach ($deadlines as $item) {
                $renewal = $item['term_end_date'] ? ' Sonst verlängert sich der Vertrag am '.$this->date($item['term_end_date']).'.' : '';
                $mail->line("**{$item['name']}** ({$item['amount']}): kündbar bis {$this->date($item['due_date'])}, also {$this->when($item['days_left'])}.{$renewal}");
            }
        }

        if ($charges !== []) {
            $mail->line(count($charges) === 1 ? 'Morgen wird abgebucht:' : 'Morgen werden abgebucht:');
            foreach ($charges as $item) {
                $mail->line("**{$item['name']}**: {$item['amount']}");
            }
        }

        $first = $this->items[0];

        return $mail
            ->action(count($this->items) === 1 ? 'Abo ansehen' : 'Cost-Tracker öffnen', count($this->items) === 1
                ? FrontendUrl::to('subscription/'.$first['subscription_id'])
                : FrontendUrl::to(''))
            ->line('Die Abstände der Erinnerungen stellst du in der App unter Einstellungen ein.');
    }

    /**
     * Mark the log entries as sent once the mail went out.
     */
    public function afterSending(): void
    {
        DB::table('sent_reminders')->whereIn('id', $this->sentReminderIds)->update(['sent_at' => now()]);
    }

    private function date(string $date): string
    {
        return CarbonImmutable::parse($date)->format('d.m.Y');
    }

    private function when(int $days): string
    {
        return match (true) {
            $days <= 0 => 'heute',
            $days === 1 => 'morgen',
            default => "in {$days} Tagen",
        };
    }
}
