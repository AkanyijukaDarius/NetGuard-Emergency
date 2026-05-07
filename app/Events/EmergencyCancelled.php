<?php
// app/Events/EmergencyCancelled.php

namespace App\Events;

use App\Models\EmergencyAlert;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmergencyCancelled implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $alert;
    public $cancelledBy;

    public function __construct(EmergencyAlert $alert, $cancelledBy = 'victim')
    {
        $this->alert = $alert;
        $this->cancelledBy = $cancelledBy;
    }

    public function broadcastOn(): array
    {
        $channels = [
            new Channel('emergency.' . $this->alert->id)
        ];

        // If a responder was assigned, notify them
        if ($this->alert->responder_id) {
            $channels[] = new Channel('responder.' . $this->alert->responder_id);
        }

        // Notify all responders to update their active list
        $channels[] = new Channel('responder.alerts');

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'emergency.cancelled';
    }

    public function broadcastWith(): array
    {
        return [
            'alert_id' => $this->alert->id,
            'status' => 'cancelled',
            'cancelled_by' => $this->cancelledBy,
            'responder_id' => $this->alert->responder_id,
            'message' => 'The emergency request has been cancelled',
            'cancelled_at' => now()->toISOString()
        ];
    }
}
