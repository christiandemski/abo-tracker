<?php

namespace App\Http\Controllers;

use App\Services\DashboardBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardBuilder $builder): JsonResponse
    {
        $data = $request->validate([
            'period' => ['sometimes', Rule::in(['month', 'year'])],
        ]);

        return response()->json(['data' => $builder->build($data['period'] ?? 'month')]);
    }
}
