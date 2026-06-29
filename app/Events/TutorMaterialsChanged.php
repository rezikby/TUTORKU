<?php

namespace App\Events;

use App\Models\TutorMaterial;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TutorMaterialsChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $action;
    public $material;

    public function __construct(string $action, TutorMaterial $material)
    {
        $this->action = $action;
        $this->material = $material;
    }

    public function broadcastWith()
    {
        return [
            'action' => $this->action,
            'material' => [
                'id' => $this->material->id,
                'title' => $this->material->title ?? null,
                'description' => $this->material->description ?? null,
                'file_url' => $this->material->file_url ?? null,
                'tutor_id' => $this->material->tutor_id ?? null,
            ],
        ];
    }

    public function broadcastOn()
    {
        return new Channel('materials');
    }
}
