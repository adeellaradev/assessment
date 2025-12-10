<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateOrderRequest;
use App\Models\Asset;
use App\Models\Order;
use App\Services\OrderMatchingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    protected OrderMatchingService $matchingService;

    public function __construct(OrderMatchingService $matchingService)
    {
        $this->matchingService = $matchingService;
    }

    public function index(Request $request): JsonResponse
    {
        $orders = Order::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'symbol' => $order->symbol,
                    'side' => $order->side,
                    'price' => $order->price,
                    'amount' => $order->amount,
                    'filled_amount' => $order->filled_amount,
                    'remaining_amount' => $order->remaining_amount,
                    'status' => $order->status,
                    'created_at' => $order->created_at->toIso8601String(),
                ];
            });

        return response()->json([
            'orders' => $orders,
        ]);
    }

    public function orderbook(Request $request): JsonResponse
    {
        $request->validate([
            'symbol' => ['required', 'string', 'max:10'],
        ]);

        $symbol = $request->input('symbol');

        $buyOrders = Order::where('symbol', $symbol)
            ->where('status', Order::STATUS_OPEN)
            ->where('side', Order::SIDE_BUY)
            ->orderBy('price', 'desc')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'user_id' => $order->user_id,
                    'side' => $order->side,
                    'price' => $order->price,
                    'amount' => $order->amount,
                    'filled_amount' => $order->filled_amount,
                    'remaining_amount' => $order->remaining_amount,
                    'status' => $order->status,
                    'created_at' => $order->created_at->toIso8601String(),
                ];
            });

        $sellOrders = Order::where('symbol', $symbol)
            ->where('status', Order::STATUS_OPEN)
            ->where('side', Order::SIDE_SELL)
            ->orderBy('price', 'asc')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'user_id' => $order->user_id,
                    'side' => $order->side,
                    'price' => $order->price,
                    'amount' => $order->amount,
                    'filled_amount' => $order->filled_amount,
                    'remaining_amount' => $order->remaining_amount,
                    'status' => $order->status,
                    'created_at' => $order->created_at->toIso8601String(),
                ];
            });

        return response()->json([
            'symbol' => $symbol,
            'buy_orders' => $buyOrders,
            'sell_orders' => $sellOrders,
        ]);
    }

    public function store(CreateOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $order = DB::transaction(function () use ($validated, $request) {
                $user = $request->user();

                if ($validated['side'] === Order::SIDE_BUY) {
                    $orderTotal = bcmul($validated['price'], $validated['amount'], 8);
                    $commission = bcmul($orderTotal, '0.015', 8);
                    $requiredBalance = bcadd($orderTotal, $commission, 8);

                    if (bccomp($user->balance, $requiredBalance, 8) < 0) {
                        throw new \Exception('Insufficient balance');
                    }

                    $user->balance = bcsub($user->balance, $requiredBalance, 8);
                    $user->save();
                } else {
                    $asset = Asset::where('user_id', $user->id)
                        ->where('symbol', $validated['symbol'])
                        ->lockForUpdate()
                        ->first();

                    if (!$asset) {
                        throw new \Exception('Asset not found');
                    }

                    $availableAmount = bcsub($asset->amount, $asset->locked_amount, 8);

                    if (bccomp($availableAmount, $validated['amount'], 8) < 0) {
                        throw new \Exception('Insufficient asset balance');
                    }

                    $asset->locked_amount = bcadd($asset->locked_amount, $validated['amount'], 8);
                    $asset->save();
                }

                $order = Order::create([
                    'user_id' => $user->id,
                    'symbol' => $validated['symbol'],
                    'side' => $validated['side'],
                    'price' => $validated['price'],
                    'amount' => $validated['amount'],
                    'status' => Order::STATUS_OPEN,
                ]);

                return $order;
            });

            $this->matchingService->matchOrder($order);

            $order->refresh();

            return response()->json([
                'message' => 'Order created successfully',
                'order' => [
                    'id' => $order->id,
                    'symbol' => $order->symbol,
                    'side' => $order->side,
                    'price' => $order->price,
                    'amount' => $order->amount,
                    'filled_amount' => $order->filled_amount,
                    'remaining_amount' => $order->remaining_amount,
                    'status' => $order->status,
                    'created_at' => $order->created_at,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create order',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        try {
            $order = Order::where('id', $id)
                ->where('user_id', $request->user()->id)
                ->firstOrFail();

            if (!$order->isOpen()) {
                return response()->json([
                    'message' => 'Order cannot be cancelled',
                ], 400);
            }

            DB::transaction(function () use ($order) {
                $remainingAmount = $order->remaining_amount;

                if ($order->isBuy()) {
                    $refundTotal = bcmul($order->price, $remainingAmount, 8);
                    $refundCommission = bcmul($refundTotal, '0.015', 8);
                    $refundAmount = bcadd($refundTotal, $refundCommission, 8);
                    $order->user->balance = bcadd($order->user->balance, $refundAmount, 8);
                    $order->user->save();
                } else {
                    $asset = Asset::where('user_id', $order->user_id)
                        ->where('symbol', $order->symbol)
                        ->lockForUpdate()
                        ->first();

                    if ($asset) {
                        $asset->locked_amount = bcsub($asset->locked_amount, $remainingAmount, 8);
                        $asset->save();
                    }
                }

                $order->status = Order::STATUS_CANCELLED;
                $order->save();
            });

            return response()->json([
                'message' => 'Order cancelled successfully',
                'order' => [
                    'id' => $order->id,
                    'status' => $order->status,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to cancel order',
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
