<?php

namespace App\Http\Controllers;

use App\Services\CalendarBuilder;
use App\Services\HistoryBuilder;
use App\Services\ReserveCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatsController extends Controller
{
    /** GET /calendar?month=2026-11 */
    public function calendar(Request $request, CalendarBuilder $builder): JsonResponse
    {
        $data = $request->validate([
            'month' => ['sometimes', 'string', 'regex:/^(20[0-9]{2})-(0[1-9]|1[0-2])$/'],
        ], [
            'month.regex' => 'Bitte einen Monat im Format JJJJ-MM angeben.',
        ]);

        $month = isset($data['month'])
            ? CarbonImmutable::createFromFormat('!Y-m', $data['month'])
            : CarbonImmutable::today()->startOfMonth();

        return response()->json(['data' => $builder->build($month)]);
    }

    /** GET /stats/history?months=12 */
    public function history(Request $request, HistoryBuilder $builder): JsonResponse
    {
        $data = $request->validate([
            'months' => ['sometimes', 'integer', 'min:2', 'max:'.HistoryBuilder::MAX_MONTHS],
        ]);

        return response()->json(['data' => $builder->build((int) ($data['months'] ?? 12))]);
    }

    /** GET /stats/reserve */
    public function reserve(ReserveCalculator $calculator): JsonResponse
    {
        return response()->json(['data' => $calculator->build()]);
    }
}
