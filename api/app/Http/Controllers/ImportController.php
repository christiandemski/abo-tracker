<?php

namespace App\Http\Controllers;

use App\Enums\IntervalUnit;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\ImportBatch;
use App\Models\ImportCandidate;
use App\Services\BillingCalculator;
use App\Services\Import\BankFormats;
use App\Services\Import\InvalidStatementException;
use App\Services\Import\StatementImporter;
use App\Support\Fraction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ImportController extends Controller
{
    public function __construct(
        private readonly StatementImporter $importer,
        private readonly BankFormats $formats,
        private readonly BillingCalculator $billing,
    ) {}

    public function formats(): JsonResponse
    {
        return response()->json(['data' => array_map(fn ($p) => [
            'key' => $p->key(),
            'label' => $p->label(),
            'hint' => $p->hint(),
        ], $this->formats->all())]);
    }

    /**
     * The uploaded file is read into memory, parsed and deleted right away – it is never stored.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:2048', 'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel,application/octet-stream'],
            'bank_format' => ['required', Rule::in($this->formats->keys())],
        ], [
            'file.max' => 'Die Datei ist zu groß (höchstens 2 MB).',
            'file.mimetypes' => 'Bitte lade eine CSV-Datei hoch.',
        ], [
            'file' => 'Datei',
            'bank_format' => 'Bankformat',
        ]);

        $file = $data['file'];
        $contents = (string) file_get_contents($file->getRealPath());
        $name = $file->getClientOriginalName();
        @unlink($file->getRealPath());

        try {
            $batch = $this->importer->import($request->user(), $this->formats->find($data['bank_format']), $contents, $name);
        } catch (InvalidStatementException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        } finally {
            unset($contents);
        }

        return response()->json(['data' => $this->presentBatch($batch)], 201);
    }

    public function candidates(ImportBatch $import): JsonResponse
    {
        Gate::authorize('view', $import);

        $candidates = $import->candidates()->with(['category', 'subscription'])->orderByRaw("case status when 'new' then 0 when 'price_change' then 1 else 2 end")
            ->orderByRaw("case confidence when 'high' then 0 else 1 end")
            ->orderBy('display_name')
            ->get();

        return response()->json([
            'data' => $candidates->map(fn (ImportCandidate $c) => $this->presentCandidate($c))->values(),
            'meta' => ['batch' => $this->presentBatch($import)],
        ]);
    }

    /** Exact monthly sum of the current selection (footer "Neue Fixkosten"). */
    public function summary(Request $request, ImportBatch $import): JsonResponse
    {
        Gate::authorize('view', $import);

        $data = $request->validate([
            'candidate_ids' => ['present', 'array'],
            'candidate_ids.*' => ['integer'],
        ]);

        $total = Fraction::zero();
        $import->candidates()->whereIn('id', $data['candidate_ids'])->where('status', ImportCandidate::STATUS_NEW)->get()
            ->each(function (ImportCandidate $c) use (&$total) {
                $total = $total->add($this->billing->monthly($c->amount_cents, $c->interval_unit ?? IntervalUnit::Month, $c->interval_count ?? 1));
            });

        return response()->json(['data' => ['monthly_added_cents' => $total->round()]]);
    }

    public function apply(Request $request, ImportBatch $import): JsonResponse
    {
        Gate::authorize('update', $import);
        abort_if($import->status !== 'review', 422, 'Dieser Import wurde schon übernommen.');

        $data = $request->validate([
            'candidate_ids' => ['present', 'array'],
            'candidate_ids.*' => ['integer'],
        ]);

        $created = $this->importer->apply($import, $data['candidate_ids']);
        $monthly = $this->billing->monthlyTotal($created);

        return response()->json(['data' => [
            'created' => SubscriptionResource::collection(collect($created)->each->load('category')),
            'created_count' => count($created),
            'monthly_added_cents' => $monthly->round(),
        ]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentBatch(ImportBatch $b): array
    {
        $counts = $b->candidates()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $months = $b->period_from && $b->period_to
            ? max(1, (int) round($b->period_from->diffInDays($b->period_to) / 30.44))
            : 0;

        return [
            'id' => $b->id,
            'bank_format' => $b->bank_format,
            'file_name' => $b->file_name,
            'period_from' => $b->period_from?->toDateString(),
            'period_to' => $b->period_to?->toDateString(),
            'months' => $months,
            'rows_total' => $b->rows_total,
            'status' => $b->status,
            'counts' => [
                'new' => (int) ($counts['new'] ?? 0),
                'duplicate' => (int) ($counts['duplicate'] ?? 0),
                'price_change' => (int) ($counts['price_change'] ?? 0),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentCandidate(ImportCandidate $c): array
    {
        $unit = $c->interval_unit;
        $count = $c->interval_count ?? 1;

        return [
            'id' => $c->id,
            'name' => $c->display_name,
            'amount_cents' => $c->amount_cents,
            'monthly_cents' => $unit ? $this->billing->monthly($c->amount_cents, $unit, $count)->round() : null,
            'interval_unit' => $unit?->value,
            'interval_count' => $c->interval_count,
            'rhythm_label' => $unit ? $this->billing->rhythmLabel($unit, $count) : 'unklar',
            'occurrences' => $c->occurrences,
            'last_date' => $c->last_date->toDateString(),
            'confidence' => $c->confidence,
            'status' => $c->status,
            'category' => $c->category ? CategoryResource::make($c->category)->resolve() : null,
            'subscription' => $c->subscription ? ['id' => $c->subscription->id, 'name' => $c->subscription->name, 'amount_cents' => $c->subscription->amount_cents] : null,
        ];
    }
}
