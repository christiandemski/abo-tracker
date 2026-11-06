<?php

namespace App\Http\Controllers;

use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;
use App\Services\CancellationLetter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CancellationController extends Controller
{
    public function __construct(private readonly CancellationLetter $letters) {}

    /** GET …/cancellation-letter?format=text|pdf – generated letter with placeholders */
    public function letter(Request $request, Subscription $subscription): Response
    {
        Gate::authorize('view', $subscription);
        $format = $request->validate(['format' => ['sometimes', 'in:text,pdf']])['format'] ?? 'text';

        $letter = $this->letters->build($subscription->loadMissing('category'), $request->user());

        return $format === 'pdf'
            ? $this->pdfResponse($letter['text'], $subscription)
            : response()->json(['data' => $letter]);
    }

    /** POST …/cancellation-letter – PDF of the text as edited in the app */
    public function pdf(Request $request, Subscription $subscription): Response
    {
        Gate::authorize('view', $subscription);
        $data = $request->validate(['text' => ['required', 'string', 'max:8000']], [], ['text' => 'Schreiben']);

        return $this->pdfResponse($data['text'], $subscription);
    }

    /** "Behalten": hide deadline banner, orange card and reminders until the next renewal. */
    public function keep(Subscription $subscription): JsonResponse
    {
        Gate::authorize('update', $subscription);
        abort_unless($subscription->hasTerm() && $subscription->term_end_date, 422, 'Dieses Abo hat keine Laufzeit.');

        $subscription->forceFill(['kept_until' => $subscription->term_end_date])->save();

        return SubscriptionResource::make($subscription->load('category', 'priceChanges'))->response();
    }

    public function unkeep(Subscription $subscription): JsonResponse
    {
        Gate::authorize('update', $subscription);
        $subscription->forceFill(['kept_until' => null])->save();

        return SubscriptionResource::make($subscription->load('category', 'priceChanges'))->response();
    }

    private function pdfResponse(string $text, Subscription $subscription): Response
    {
        $title = 'Kündigung '.$subscription->name;
        $file = 'Kuendigung-'.Str::slug($subscription->name).'.pdf';

        return response($this->letters->pdf($text, $title), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$file.'"',
            'Cache-Control' => 'no-store',
        ]);
    }
}
