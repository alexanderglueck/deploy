<?php

namespace App\Events;

use App\Models\ContainerAction;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired on every status transition of a container action (queued → running →
 * ok/failed). Broadcast immediately (not queued): actions run after the HTTP
 * response in the web container, so there is no worker in the loop to pick a
 * queued broadcast up quickly.
 */
class ContainerActionUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ContainerAction $action,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('team.'.$this->action->server->team->ulid),
        ];
    }

    public function broadcastAs(): string
    {
        return 'container-action.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'server' => $this->action->server->ulid,
            'action' => $this->action->toArray(),
        ];
    }
}
