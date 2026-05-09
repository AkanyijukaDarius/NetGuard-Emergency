<?php

namespace App\Events;

use App\Models\EmergencyAlert;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
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
        return [
            new Channel('responder.alerts'),

            new PrivateChannel('App.Models.User.' . $this->alert->user_id),

            $this->alert->responder_id
                ? new PrivateChannel('App.Models.User.' . $this->alert->responder_id)
                : new Channel('emergency-channel'),
        ];
    }


    public function broadcastAs(): string
    {
        return 'emergency.cancelled';
    }


    public function broadcastWith(): array
    {
        return [
            'alert_id'     => $this->alert->id,
            'status'       => 'cancelled',
            'cancelled_by' => $this->cancelledBy,
            'message'      => 'The emergency request has been cancelled.',
            'cancelled_at' => now()->toIso8601String(),
        ];
    }
}
