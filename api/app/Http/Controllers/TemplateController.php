<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Template;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:64'],
        ]);
        $q = trim((string) ($data['q'] ?? ''));

        $query = Template::query()->orderBy('sort');

        if ($q === '') {
            $query->where('popular', true);
        } else {
            $term = '%'.addcslashes(mb_strtolower($q), '%_\\').'%';
            $query->where(fn ($w) => $w->whereRaw('lower(name) like ?', [$term])->orWhereRaw('lower(aliases) like ?', [$term]))
                ->limit(20);
        }

        // Map the template's category key to the default category id for the form.
        $categoryIds = Category::query()->whereNull('user_id')->pluck('id', 'key');

        return response()->json([
            'data' => $query->get()->map(fn (Template $t) => [
                'key' => $t->key,
                'name' => $t->name,
                'category_key' => $t->category_key,
                'category_id' => $categoryIds[$t->category_key] ?? null,
                'interval_unit' => $t->interval_unit->value,
                'interval_count' => $t->interval_count,
                'popular' => $t->popular,
            ])->values(),
        ]);
    }
}
