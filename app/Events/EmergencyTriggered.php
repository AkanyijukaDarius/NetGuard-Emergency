<?php

namespace App\Events;

use App\Models\EmergencyAlert;
use App\Models\Incident;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmergencyTriggered implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $alert;
    public $incident;
    public $triageResult;

    public function __construct(EmergencyAlert $alert, Incident $incident, array $triageResult)
    {
        $this->alert = $alert->load('user');
        $this->incident = $incident;
        $this->triageResult = $triageResult;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('emergency-channel'),
        ];
    }

    /**
     * By removing broadcastAs or changing it to 'EmergencyTriggered',
     * it matches your .listen('.EmergencyTriggered') in Pinia.
     */
    public function broadcastAs(): string
    {
        return 'EmergencyTriggered';
    }
}
