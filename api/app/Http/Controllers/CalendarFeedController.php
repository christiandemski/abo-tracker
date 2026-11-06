<?php

namespace App\Http\Controllers;

use App\Models\CalendarFeed;
use App\Models\Scopes\OwnedByCurrentUserScope;
use App\Models\User;
use App\Services\IcalFeed;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CalendarFeedController extends Controller
{
    public function __construct(private readonly IcalFeed $feed) {}

    /** GET /settings/calendar-feed — the link itself is only returned once, when it is created. */
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->present($request->user(), $this->feedFor($request->user()))]);
    }

    /** PUT /settings/calendar-feed — what goes into the calendar */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'options' => ['required', 'array'],
            'options.charges' => ['required', 'boolean'],
            'options.deadlines' => ['required', 'boolean'],
            'options.reminders' => ['required', 'boolean'],
            'options.amounts' => ['required', 'boolean'],
        ]);

        $feed = $this->feedFor($request->user());
        $feed->options = array_map('boolval', array_intersect_key($data['options'], CalendarFeed::DEFAULT_OPTIONS));
        $feed->save();

        return response()->json(['data' => $this->present($request->user(), $feed)]);
    }

    /** POST /settings/calendar-feed/rotate — new secret link, the old one stops working */
    public function rotate(Request $request): JsonResponse
    {
        $feed = $this->feedFor($request->user());
        $token = bin2hex(random_bytes(32));
        $feed->forceFill([
            'token_hash' => CalendarFeed::hashToken($token),
            'token_created_at' => now(),
            'last_fetched_at' => null,
        ])->save();

        return response()->json(['data' => $this->present($request->user(), $feed, $token)]);
    }

    /** DELETE /settings/calendar-feed — switch the link off */
    public function destroy(Request $request): JsonResponse
    {
        $feed = $this->feedFor($request->user());
        $feed->forceFill(['token_hash' => null, 'token_created_at' => null, 'last_fetched_at' => null])->save();

        return response()->json(['data' => $this->present($request->user(), $feed)]);
    }

    /** GET /ical/{token}.ics — public, only reachable with the secret token */
    public function ics(Request $request, string $token): Response
    {
        $feed = CalendarFeed::query()
            ->withoutGlobalScope(OwnedByCurrentUserScope::class)
            ->where('token_hash', CalendarFeed::hashToken($token))
            ->first();

        abort_if($feed === null, 404);

        $user = User::query()->findOrFail($feed->user_id);
        $feed->forceFill(['last_fetched_at' => now()])->saveQuietly();

        $body = $this->feed->render($this->feed->events($user, $feed->resolvedOptions()), $request->getHost());

        return response($body, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="abo-tracker.ics"',
            'Cache-Control' => 'private, max-age=900',
            'X-Robots-Tag' => 'noindex',
        ]);
    }

    private function feedFor(User $user): CalendarFeed
    {
        $feed = CalendarFeed::query()->where('user_id', $user->id)->first();
        if ($feed === null) {
            $feed = new CalendarFeed;
            $feed->user_id = $user->id;
        }

        return $feed;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(User $user, CalendarFeed $feed, ?string $token = null): array
    {
        $options = $feed->resolvedOptions();

        return [
            'active' => $feed->isActive(),
            // Only right after creating it; afterwards we cannot show it again (stored as hash)
            'url' => $token !== null ? route('ical.feed', ['token' => $token]) : null,
            'created_at' => $feed->token_created_at?->toIso8601String(),
            'last_fetched_at' => $feed->last_fetched_at?->toIso8601String(),
            'options' => $options,
            'preview' => $this->feed->preview($user, $options, CarbonImmutable::today()),
        ];
    }
}
