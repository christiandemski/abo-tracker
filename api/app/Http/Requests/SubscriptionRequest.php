<?php

namespace App\Http\Requests;

use App\Enums\IntervalUnit;
use App\Enums\NoticeUnit;
use App\Enums\SubscriptionStatus;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create (POST) and update (PATCH) share the rules; on PATCH every field is optional.
 */
class SubscriptionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:100'],
            'amount_cents' => [$required, 'integer', 'min:1', 'max:100000000'],
            'interval_unit' => [$required, Rule::enum(IntervalUnit::class)],
            'interval_count' => ['sometimes', 'integer', 'min:1', 'max:52'],
            'anchor_date' => [$required, 'date_format:Y-m-d', 'after:1970-01-01', 'before:2100-01-01'],
            'category_id' => [
                'sometimes', 'nullable', 'integer',
                // Only default categories or the user's own
                Rule::exists('categories', 'id')->where(fn (Builder $q) => $q
                    ->whereNull('user_id')->orWhere('user_id', $this->user()->id)),
            ],
            'start_date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after:1970-01-01', 'before:2100-01-01'],
            'min_term_months' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:120'],
            'renewal_months' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:120'],
            'notice_value' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:365'],
            'notice_unit' => ['nullable', 'required_with:notice_value', Rule::enum(NoticeUnit::class)],
            'status' => ['sometimes', Rule::enum(SubscriptionStatus::class)],
            'remind' => ['sometimes', 'boolean'],
            'payment_method' => ['sometimes', 'nullable', 'string', 'max:100'],
            'provider_url' => ['sometimes', 'nullable', 'url:http,https', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'template_key' => ['sometimes', 'nullable', 'string', Rule::exists('templates', 'key')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'amount_cents' => 'Betrag',
            'interval_unit' => 'Rhythmus',
            'interval_count' => 'Rhythmus',
            'anchor_date' => 'nächste Abbuchung',
            'category_id' => 'Kategorie',
            'start_date' => 'Vertragsbeginn',
            'min_term_months' => 'Mindestlaufzeit',
            'renewal_months' => 'Verlängerung',
            'notice_value' => 'Kündigungsfrist',
            'notice_unit' => 'Kündigungsfrist',
            'payment_method' => 'Zahlungsweg',
            'provider_url' => 'Link zum Anbieter',
            'notes' => 'Notizen',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge(['name' => trim((string) $this->input('name'))]);
        }
    }
}
