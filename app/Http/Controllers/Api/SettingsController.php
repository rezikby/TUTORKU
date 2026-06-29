<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserSettingResource;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    public function show(Request $request)
    {
        $settings = $request->user()->settings()->firstOrCreate([]);

        return new UserSettingResource($settings);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'language' => ['sometimes', Rule::in(['id', 'en'])],
            'dark_mode' => ['sometimes', 'boolean'],
            'notif_email' => ['sometimes', 'boolean'],
            'notif_whatsapp' => ['sometimes', 'boolean'],
            'notif_push' => ['sometimes', 'boolean'],
            'reminder_time' => ['sometimes', 'integer', 'in:15,30,60'],
        ]);

        $settings = $request->user()->settings()->firstOrCreate([]);
        $settings->update($validated);

        return new UserSettingResource($settings->fresh());
    }
}
