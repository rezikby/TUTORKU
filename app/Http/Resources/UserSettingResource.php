<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (! $this->resource) {
            return [];
        }

        return [
            'language' => $this->language,
            'dark_mode' => $this->dark_mode,
            'notif_email' => $this->notif_email,
            'notif_whatsapp' => $this->notif_whatsapp,
            'notif_push' => $this->notif_push,
            'reminder_time' => $this->reminder_time,
        ];
    }
}
