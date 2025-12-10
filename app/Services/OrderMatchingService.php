<?php

namespace App\Services;

use App\Events\OrderMatched;
use App\Events\OrderStatusUpdated;
use App\Models\Asset;
use App\Models\Order;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class OrderMatchingService
{
    const COMMISSION_RATE = '0.015';

    public function matchOrder(Order $newOrder): void
    {
        DB::transaction(function () use ($newOrder) {
            $newOrder->refresh();

            if (!$newOrder->isOpen()) {
                return;
            }

            $counterOrders = $this->getMatchingOrders($newOrder);

            foreach ($counterOrders as $counterOrder) {
                if (bccomp($newOrder->remaining_amount, '0', 8) <= 0) {
                    break;
                }

                $this->executeMatch($newOrder, $counterOrder);
            }
        });
    }

    protected function getMatchingOrders(Order $newOrder)
    {
        $query = Order::where('symbol', $newOrder->symbol)
            ->where('status', Order::STATUS_OPEN)
            ->where('id', '!=', $newOrder->id)
            ->where('user_id', '!=', $newOrder->user_id);

        if ($newOrder->isBuy()) {
            $query->where('side', Order::SIDE_SELL)
                ->where('price', '<=', $newOrder->price)
                ->orderBy('price', 'asc');
        } else {
            $query->where('side', Order::SIDE_BUY)
                ->where('price', '>=', $newOrder->price)
                ->orderBy('price', 'desc');
        }

        return $query->orderBy('created_at', 'asc')->lockForUpdate()->get();
    }

    protected function executeMatch(Order $newOrder, Order $counterOrder): void
    {
        $newOrder->refresh();
        $counterOrder->refresh();

        $matchAmount = bcmin($newOrder->remaining_amount, $counterOrder->remaining_amount, 8);
        $matchPrice = $counterOrder->price;

        Log::info('💰 Match found and executing', [
            'new_order' => [
                'id' => $newOrder->id,
                'user_id' => $newOrder->user_id,
                'side' => $newOrder->side,
                'price' => $newOrder->price,
                'remaining' => $newOrder->remaining_amount,
            ],
            'counter_order' => [
                'id' => $counterOrder->id,
                'user_id' => $counterOrder->user_id,
                'side' => $counterOrder->side,
                'price' => $counterOrder->price,
                'remaining' => $counterOrder->remaining_amount,
            ],
            'match_amount' => $matchAmount,
            'match_price' => $matchPrice,
        ]);

        if ($newOrder->isBuy()) {
            $this->executeBuyMatch($newOrder, $counterOrder, $matchAmount, $matchPrice);
        } else {
            $this->executeSellMatch($newOrder, $counterOrder, $matchAmount, $matchPrice);
        }

        $this->updateOrderStatus($newOrder);
        $this->updateOrderStatus($counterOrder);
    }

    protected function executeBuyMatch(Order $buyOrder, Order $sellOrder, string $amount, string $price): void
    {
        $total = bcmul($amount, $price, 8);
        $commission = bcmul($total, self::COMMISSION_RATE, 8);

        $buyer = $buyOrder->user;
        $seller = $sellOrder->user;

        $buyerAsset = Asset::where('user_id', $buyer->id)
            ->where('symbol', $buyOrder->symbol)
            ->lockForUpdate()
            ->first();

        $sellerAsset = Asset::where('user_id', $seller->id)
            ->where('symbol', $sellOrder->symbol)
            ->lockForUpdate()
            ->first();

        if (!$buyerAsset) {
            $buyerAsset = Asset::create([
                'user_id' => $buyer->id,
                'symbol' => $buyOrder->symbol,
                'amount' => '0',
                'locked_amount' => '0',
            ]);
        }

        $buyerAsset->amount = bcadd($buyerAsset->amount, $amount, 8);
        $buyerAsset->save();

        $totalWithCommission = bcadd($total, $commission, 8);
        $buyer->balance = bcsub($buyer->balance, $totalWithCommission, 8);
        $buyer->save();

        $sellerAsset->locked_amount = bcsub($sellerAsset->locked_amount, $amount, 8);
        $sellerAsset->amount = bcsub($sellerAsset->amount, $amount, 8);
        $sellerAsset->save();

        $seller->balance = bcadd($seller->balance, $total, 8);
        $seller->save();

        $buyOrder->filled_amount = bcadd($buyOrder->filled_amount, $amount, 8);
        $buyOrder->save();

        $sellOrder->filled_amount = bcadd($sellOrder->filled_amount, $amount, 8);
        $sellOrder->save();

        $trade = Trade::create([
            'buy_order_id' => $buyOrder->id,
            'sell_order_id' => $sellOrder->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'symbol' => $buyOrder->symbol,
            'price' => $price,
            'amount' => $amount,
            'executed_at' => now(),
        ]);

        Log::info('📊 Trade created, firing OrderMatched event', [
            'trade_id' => $trade->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'symbol' => $buyOrder->symbol,
            'price' => $price,
            'amount' => $amount,
        ]);

        event(new OrderMatched($trade, $buyer->id, $seller->id));
    }

    protected function executeSellMatch(Order $sellOrder, Order $buyOrder, string $amount, string $price): void
    {
        $this->executeBuyMatch($buyOrder, $sellOrder, $amount, $price);
    }

    protected function updateOrderStatus(Order $order): void
    {
        $oldStatus = $order->status;

        if (bccomp($order->filled_amount, $order->amount, 8) >= 0) {
            $order->status = Order::STATUS_FILLED;
            $order->save();

            // Broadcast status update to the order owner
            if ($oldStatus !== Order::STATUS_FILLED) {
                event(new OrderStatusUpdated($order));
            }
        }
    }
}

function bcmin(string $a, string $b, int $scale): string
{
    return bccomp($a, $b, $scale) <= 0 ? $a : $b;
}
