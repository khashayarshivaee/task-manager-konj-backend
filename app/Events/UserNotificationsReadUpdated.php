<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class UserNotificationsReadUpdated implements
    ShouldBroadcast,
    ShouldDispatchAfterCommit
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly int $userId,
        public readonly int $unreadCount,
        public readonly ?int $notificationId = null,
        public readonly bool $readAll = false,
    ) {
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel(
                'users.'.$this->userId,
            ),
        ];
    }

    public function broadcastAs(): string
    {
        return 'notifications.read-updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'user_id' =>
                $this->userId,

            'notification_id' =>
                $this->notificationId,

            'unread_count' =>
                $this->unreadCount,

            'read_all' =>
                $this->readAll,
        ];
    }
}
