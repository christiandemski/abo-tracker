<?php

namespace App\Services;

use App\Models\CalendarFeed;
use App\Models\Scopes\OwnedByCurrentUserScope;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * Events of the private calendar feed and their iCalendar (RFC 5545) rendering.
 *
 * The public feed has no logged-in user, so every query here is filtered by the
 * feed owner explicitly instead of relying on the tenant scope.
 */
class IcalFeed
{
    /** Window of the feed: a month back, a year ahead */
    public const PAST_DAYS = 31;

    public const FUTURE_DAYS = 366;

    public const PREVIEW_LIMIT = 4;

    public function __construct(private readonly ChargeSchedule $schedule) {}

    /**
     * @param  array{charges: bool, deadlines: bool, reminders: bool, amounts: bool}  $options
     * @return list<array{type: string, uid: string, date: string, title: string, description: string, color: ?string}>
     */
    public function events(User $user, array $options, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $from = $today->subDays(self::PAST_DAYS);
        $to = $today->addDays(self::FUTURE_DAYS);

        $subscriptions = Subscription::query()
            ->withoutGlobalScope(OwnedByCurrentUserScope::class)
            ->where('user_id', $user->id)
            ->with([
                'category',
                'priceChanges' => fn ($q) => $q->withoutGlobalScope(OwnedByCurrentUserScope::class)->where('user_id', $user->id),
            ])
            ->orderBy('name')
            ->get();

        $events = [];
        foreach ($subscriptions as $s) {
            if ($options['charges']) {
                foreach ($this->schedule->between($s, $from, $to) as $charge) {
                    $title = $options['amounts'] ? $s->name.' · '.Money::format($charge['amount_cents']) : $s->name;
                    $events[] = $this->event('charge', "charge-{$s->id}-{$charge['date']->format('Ymd')}", $charge['date'], $title,
                        'Abbuchung '.$s->name, $s->category?->color);
                }
            }

            $deadline = $s->isActive() && $s->hasTerm() && ! $s->isKeptForCurrentTerm() ? $s->cancel_by_date : null;
            if ($deadline === null) {
                continue;
            }
            if ($options['deadlines'] && $deadline->betweenIncluded($from, $to)) {
                $events[] = $this->event('deadline', "deadline-{$s->id}-{$deadline->format('Ymd')}", $deadline,
                    'Frist: '.$s->name.' kündigen', 'Letzter Tag, an dem du '.$s->name.' kündigen kannst.', null);
            }
            $reminder = $deadline->subDays(CalendarFeed::REMINDER_DAYS);
            if ($options['reminders'] && $reminder->betweenIncluded($from, $to)) {
                $events[] = $this->event('reminder', "reminder-{$s->id}-{$deadline->format('Ymd')}", $reminder,
                    'Erinnerung: Frist für '.$s->name.' in '.CalendarFeed::REMINDER_DAYS.' Tagen',
                    'Kündigungsfrist am '.$deadline->format('d.m.Y').'.', null);
            }
        }

        // Deadlines before charges on the same day
        $order = ['deadline' => 0, 'reminder' => 1, 'charge' => 2];
        usort($events, fn (array $a, array $b) => [$a['date'], $order[$a['type']], $a['title']] <=> [$b['date'], $order[$b['type']], $b['title']]);

        return $events;
    }

    /**
     * The next few events from today, for the live preview in the settings.
     *
     * @param  array{charges: bool, deadlines: bool, reminders: bool, amounts: bool}  $options
     * @return list<array{type: string, date: string, title: string, color: ?string}>
     */
    public function preview(User $user, array $options, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $upcoming = array_filter($this->events($user, $options, $today), fn (array $e) => $e['date'] >= $today->toDateString());

        return array_map(
            fn (array $e) => ['type' => $e['type'], 'date' => $e['date'], 'title' => $e['title'], 'color' => $e['color']],
            array_slice(array_values($upcoming), 0, self::PREVIEW_LIMIT),
        );
    }

    /**
     * @param  list<array{type: string, uid: string, date: string, title: string, description: string, color: ?string}>  $events
     */
    public function render(array $events, string $host, ?CarbonImmutable $now = null): string
    {
        $stamp = ($now ?? CarbonImmutable::now())->utc()->format('Ymd\THis\Z');

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//codekorb//Cost-Tracker//DE',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:Cost-Tracker',
            'X-WR-TIMEZONE:Europe/Berlin',
            'REFRESH-INTERVAL;VALUE=DURATION:PT6H',
            'X-PUBLISHED-TTL:PT6H',
        ];

        foreach ($events as $e) {
            $date = CarbonImmutable::parse($e['date']);
            array_push($lines,
                'BEGIN:VEVENT',
                'UID:'.$e['uid'].'@'.$host,
                'DTSTAMP:'.$stamp,
                'DTSTART;VALUE=DATE:'.$date->format('Ymd'),
                'DTEND;VALUE=DATE:'.$date->addDay()->format('Ymd'),
                'SUMMARY:'.$this->escape($e['title']),
                'DESCRIPTION:'.$this->escape($e['description']),
                'TRANSP:TRANSPARENT',
                'END:VEVENT',
            );
        }
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map($this->fold(...), $lines))."\r\n";
    }

    /**
     * @return array{type: string, uid: string, date: string, title: string, description: string, color: ?string}
     */
    private function event(string $type, string $uid, CarbonImmutable $date, string $title, string $description, ?string $color): array
    {
        return ['type' => $type, 'uid' => $uid, 'date' => $date->toDateString(), 'title' => $title, 'description' => $description, 'color' => $color];
    }

    private function escape(string $text): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], $text);
    }

    /** Lines longer than 75 octets are folded without splitting UTF-8 characters. */
    private function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $parts = [];
        $current = '';
        $limit = 75;
        foreach (mb_str_split($line) as $char) {
            if (strlen($current) + strlen($char) > $limit) {
                $parts[] = $current;
                $current = '';
                $limit = 74; // continuation lines start with a space
            }
            $current .= $char;
        }
        $parts[] = $current;

        return implode("\r\n ", $parts);
    }
}
