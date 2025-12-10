<?php

namespace App\Events;

use App\Models\Trade;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class OrderMatched implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Trade $trade;
    public int $buyerId;
    public int $sellerId;

    public function __construct(Trade $trade, int $buyerId, int $sellerId)
    {
        $this->trade = $trade;
        $this->buyerId = $buyerId;
        $this->sellerId = $sellerId;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->buyerId),
            new PrivateChannel('user.' . $this->sellerId),
        ];
    }

    public function broadcastWith(): array
    {
        $data = [
            'trade' => [
                'id' => $this->trade->id,
                'buy_order_id' => $this->trade->buy_order_id,
                'sell_order_id' => $this->trade->sell_order_id,
                'buyer_id' => $this->trade->buyer_id,
                'seller_id' => $this->trade->seller_id,
                'symbol' => $this->trade->symbol,
                'price' => $this->trade->price,
                'amount' => $this->trade->amount,
                'total' => $this->trade->total,
                'executed_at' => $this->trade->executed_at->toIso8601String(),
            ],
        ];

        Log::info('🔥 OrderMatched event broadcasting', [
            'event' => 'order.matched',
            'channels' => ['private-user.' . $this->buyerId, 'private-user.' . $this->sellerId],
            'buyer_id' => $this->buyerId,
            'seller_id' => $this->sellerId,
            'data' => $data
        ]);

        return $data;
    }

    public function broadcastAs(): string
    {
        return 'order.matched';
    }
}
