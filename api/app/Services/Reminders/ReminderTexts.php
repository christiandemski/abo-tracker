<?php

namespace App\Services\Reminders;

/**
 * Texts for push messages. No amounts: they show up on the lock screen.
 */
class ReminderTexts
{
    public static function when(int $days): string
    {
        return match (true) {
            $days <= 0 => 'heute',
            $days === 1 => 'morgen',
            default => "in {$days} Tagen",
        };
    }

    /**
     * @return array{title: string, body: string}
     */
    public static function push(DueReminder $reminder): array
    {
        $name = $reminder->subscription->name;

        return $reminder->isDeadline()
            ? ['title' => 'Kündigungsfrist', 'body' => "Frist für {$name} endet ".self::when($reminder->daysLeft).'.']
            : ['title' => 'Abbuchung morgen', 'body' => "Morgen wird {$name} abgebucht."];
    }

    /**
     * @param  list<DueReminder>  $reminders
     * @return array{title: string, body: string}
     */
    public static function pushSummary(array $reminders): array
    {
        $deadlines = count(array_filter($reminders, fn (DueReminder $r) => $r->isDeadline()));
        $names = implode(', ', array_map(fn (DueReminder $r) => $r->subscription->name, array_slice($reminders, 0, 3)));
        $more = count($reminders) > 3 ? ' und weitere' : '';

        return [
            'title' => $deadlines > 0 ? "{$deadlines} Kündigungsfristen stehen an" : count($reminders).' Abbuchungen morgen',
            'body' => $names.$more.'.',
        ];
    }
}
