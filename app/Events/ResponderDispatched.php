<?php
namespace App\Events;

use App\Models\EmergencyAlert;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;

class ResponderDispatched implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $alert;
    public $responder;

    public function __construct(EmergencyAlert $alert, User $responder)
    {
        $this->alert = $alert;
        $this->responder = $responder;
    }

  public function broadcastOn(): array
    {
        return [
            new PrivateChannel('emergency.' . $this->alert->id),
        ];
    }

    public function broadcastAs(): string
    {

        return 'ResponderDispatched';
    }

    public function broadcastWith(): array
    {
        return [
            'alert' => [
                'id' => $this->alert->id,
                'status' => 'dispatched',
            ],
            'responder' => [
                'given_name' => $this->responder->given_name,
                'phone' => $this->responder->phone,
            ],
            'message' => 'Help is on the way!'
        ];
    }
}
