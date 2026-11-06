<?php

namespace App\Http\Controllers;

use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CategoryController extends Controller
{
    /** Category colors from docs/DESIGN.md – never orange (reserved for action required). */
    public const PALETTE = ['#8EA8FF', '#B5E36B', '#4FD1C5', '#C9A7FF', '#F48FB1', '#6B7385', '#9AA3B5'];

    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Category::class);

        $categories = Category::query()
            ->orderByRaw('user_id is not null')
            ->orderBy('sort')
            ->orderBy('name')
            ->get();

        return CategoryResource::collection($categories);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Category::class);

        $category = Category::create($this->validated($request));

        return CategoryResource::make($category)->response()->setStatusCode(201);
    }

    public function update(Request $request, Category $category): CategoryResource
    {
        Gate::authorize('update', $category);

        $category->update($this->validated($request, partial: true));

        return CategoryResource::make($category);
    }

    /** Subscriptions of a deleted category keep existing without a category. */
    public function destroy(Category $category): Response
    {
        Gate::authorize('delete', $category);

        $category->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$required, 'string', 'max:64'],
            'color' => [$required, 'string', 'in:'.implode(',', self::PALETTE)],
            'sort' => ['sometimes', 'integer', 'min:0', 'max:1000'],
        ], [
            'color.in' => 'Bitte wähl eine der vorgegebenen Farben.',
        ], [
            'color' => 'Farbe',
        ]);
    }
}
