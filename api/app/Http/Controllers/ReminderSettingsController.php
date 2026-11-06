<?php

namespace App\Http\Controllers;

use App\Models\PushToken;
use App\Models\ReminderSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReminderSettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->present(ReminderSetting::forUser($request->user()))]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'offsets_days' => ['required', 'array', 'min:1', 'max:'.count(ReminderSetting::AVAILABLE_OFFSETS)],
            'offsets_days.*' => ['integer', 'distinct', Rule::in(ReminderSetting::AVAILABLE_OFFSETS)],
            'push' => ['required', 'boolean'],
            'email' => ['required', 'boolean'],
            'charge_reminders' => ['required', 'boolean'],
        ], [
            'offsets_days.min' => 'Wähl mindestens einen Zeitpunkt für die Erinnerung.',
            'offsets_days.required' => 'Wähl mindestens einen Zeitpunkt für die Erinnerung.',
        ]);

        $setting = ReminderSetting::forUser($request->user());
        $setting->update($data);

        return response()->json(['data' => $this->present($setting)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(ReminderSetting $setting): array
    {
        return [
            'offsets_days' => $setting->offsets(),
            'available_offsets' => ReminderSetting::AVAILABLE_OFFSETS,
            'push' => $setting->push,
            'email' => $setting->email,
            'charge_reminders' => $setting->charge_reminders,
            'push_devices' => PushToken::query()->count(),
        ];
    }
}
