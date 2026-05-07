<?php

namespace App\Events;

use App\Models\EmergencyAlert;
use App\Models\Incident;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmergencyTriggered implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $alert;
    public $incident;
    public $triageResult;

    public function __construct(EmergencyAlert $alert, Incident $incident, array $triageResult)
    {
        $this->alert = $alert;
        $this->incident = $incident;
        $this->triageResult = $triageResult;
    }

    public function broadcastOn(): array
    {
        // This is the channel your dashboard will listen to
        return [
            new Channel('emergency-channel'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'emergency.triggered';
    }
}
