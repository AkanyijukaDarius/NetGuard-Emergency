<?php
namespace App\Events;

use App\Models\EmergencyAlert;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow; // Use Now for immediate delivery
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ResponderDispatched implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public $alert;
    public $responderName;

    public function __construct(EmergencyAlert $alert, User $responder)
    {
        $this->alert = $alert;
        // Use given_name because your User model uses it
        $this->responderName = $responder->given_name;
    }

    public function broadcastOn(): array
    {
        // Must match the Pinia: window.Echo.private(`emergency.${alertId}`)
        return [
            new PrivateChannel('emergency.' . $this->alert->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'responder.coming';
    }

    public function broadcastWith(): array
    {
        return [
            'responderName' => $this->responderName,
            'alertId' => $this->alert->id,
            'status' => 'Help is on the way'
        ];
    }
}
