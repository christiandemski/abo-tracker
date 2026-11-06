<?php

namespace App\Http\Controllers;

use App\Enums\IntervalUnit;
use App\Services\BillingCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Live conversion for the "Abo hinzufügen" footer, so the app never calculates itself.
 */
class BillingPreviewController extends Controller
{
    public function __invoke(Request $request, BillingCalculator $billing): JsonResponse
    {
        $data = $request->validate([
            'amount_cents' => ['required', 'integer', 'min:0', 'max:100000000'],
            'interval_unit' => ['required', Rule::enum(IntervalUnit::class)],
            'interval_count' => ['sometimes', 'integer', 'min:1', 'max:52'],
        ]);

        $unit = IntervalUnit::from($data['interval_unit']);
        $count = (int) ($data['interval_count'] ?? 1);

        return response()->json(['data' => [
            'monthly_cents' => $billing->monthly($data['amount_cents'], $unit, $count)->round(),
            'yearly_cents' => $billing->yearly($data['amount_cents'], $unit, $count)->round(),
            'daily_cents' => $billing->daily($data['amount_cents'], $unit, $count)->round(),
            'rhythm_label' => $billing->rhythmLabel($unit, $count),
            'is_converted' => $billing->isLongInterval($unit, $count),
        ]]);
    }
}
