<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class OrderStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Order $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->order->user_id),
        ];
    }

    public function broadcastWith(): array
    {
        // Map status codes to readable strings
        $statusMap = [
            Order::STATUS_OPEN => 'open',
            Order::STATUS_FILLED => 'filled',
            Order::STATUS_CANCELLED => 'cancelled',
        ];

        $data = [
            'order' => [
                'id' => $this->order->id,
                'user_id' => $this->order->user_id,
                'symbol' => $this->order->symbol,
                'side' => $this->order->side,
                'price' => $this->order->price,
                'amount' => $this->order->amount,
                'filled_amount' => $this->order->filled_amount,
                'remaining_amount' => $this->order->remaining_amount,
                'status' => $this->order->status,
                'status_text' => $statusMap[$this->order->status] ?? 'unknown',
                'created_at' => $this->order->created_at->toIso8601String(),
                'updated_at' => $this->order->updated_at->toIso8601String(),
            ],
        ];

        Log::info('✅ OrderStatusUpdated event broadcasting', [
            'event' => 'order.status.updated',
            'channel' => 'private-user.' . $this->order->user_id,
            'user_id' => $this->order->user_id,
            'order_id' => $this->order->id,
            'status' => $this->order->status,
            'status_text' => $statusMap[$this->order->status] ?? 'unknown',
            'data' => $data
        ]);

        return $data;
    }

    public function broadcastAs(): string
    {
        return 'order.status.updated';
    }
}
