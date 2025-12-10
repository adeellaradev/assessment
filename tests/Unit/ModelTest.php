<?php

namespace Tests\Unit;

use App\Models\Asset;
use App\Models\Order;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_assets_relationship(): void
    {
        $user = User::factory()->create();

        Asset::create([
            'user_id' => $user->id,
            'symbol' => 'BTC',
            'amount' => '1.00000000',
            'locked_amount' => '0.00000000',
        ]);

        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $user->assets);
        $this->assertEquals(1, $user->assets->count());
    }

    public function test_user_has_orders_relationship(): void
    {
        $user = User::factory()->create();

        Order::create([
            'user_id' => $user->id,
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
            'status' => Order::STATUS_OPEN,
        ]);

        $this->assertEquals(1, $user->orders->count());
    }

    public function test_asset_available_amount_accessor(): void
    {
        $user = User::factory()->create();

        $asset = Asset::create([
            'user_id' => $user->id,
            'symbol' => 'BTC',
            'amount' => '5.00000000',
            'locked_amount' => '2.50000000',
        ]);

        $this->assertEquals('2.50000000', $asset->available_amount);
    }

    public function test_order_remaining_amount_accessor(): void
    {
        $user = User::factory()->create();

        $order = Order::create([
            'user_id' => $user->id,
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
            'filled_amount' => '0.30000000',
            'status' => Order::STATUS_OPEN,
        ]);

        $this->assertEquals('0.70000000', $order->remaining_amount);
    }

    public function test_order_is_buy_method(): void
    {
        $user = User::factory()->create();

        $buyOrder = Order::create([
            'user_id' => $user->id,
            'symbol' => 'BTC',
            'side' => Order::SIDE_BUY,
            'price' => '50000.00000000',
            'amount' => '1.00000000',
            'status' => Order::STATUS_OPEN,
        ]);

        $sellOrder = Order::create([
            'user_id' => $user->id,
            'symbol' => 'BTC',
            'side' => Order::SIDE_SELL,
            'price' => '50000.00000000',
            'amount' => '1.00000000',
            'status' => Order::STATUS_OPEN,
        ]);

        $this->assertTrue($buyOrder->isBuy());
        $this->assertFalse($buyOrder->isSell());

        $this->assertTrue($sellOrder->isSell());
        $this->assertFalse($sellOrder->isBuy());
    }

    public function test_order_status_methods(): void
    {
        $user = User::factory()->create();

        $openOrder = Order::create([
            'user_id' => $user->id,
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
            'status' => Order::STATUS_OPEN,
        ]);

        $filledOrder = Order::create([
            'user_id' => $user->id,
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
            'status' => Order::STATUS_FILLED,
        ]);

        $cancelledOrder = Order::create([
            'user_id' => $user->id,
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
            'status' => Order::STATUS_CANCELLED,
        ]);

        $this->assertTrue($openOrder->isOpen());
        $this->assertFalse($openOrder->isFilled());
        $this->assertFalse($openOrder->isCancelled());

        $this->assertFalse($filledOrder->isOpen());
        $this->assertTrue($filledOrder->isFilled());
        $this->assertFalse($filledOrder->isCancelled());

        $this->assertFalse($cancelledOrder->isOpen());
        $this->assertFalse($cancelledOrder->isFilled());
        $this->assertTrue($cancelledOrder->isCancelled());
    }

    public function test_trade_total_accessor(): void
    {
        $buyer = User::factory()->create();
        $seller = User::factory()->create();

        $buyOrder = Order::create([
            'user_id' => $buyer->id,
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
            'status' => Order::STATUS_FILLED,
        ]);

        $sellOrder = Order::create([
            'user_id' => $seller->id,
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
            'status' => Order::STATUS_FILLED,
        ]);

        $trade = Trade::create([
            'buy_order_id' => $buyOrder->id,
            'sell_order_id' => $sellOrder->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'symbol' => 'BTC',
            'price' => '50000.00000000',
            'amount' => '0.50000000',
            'executed_at' => now(),
        ]);

        $this->assertEquals('25000.00000000', $trade->total);
    }

    public function test_order_constants_are_defined(): void
    {
        $this->assertEquals(1, Order::STATUS_OPEN);
        $this->assertEquals(2, Order::STATUS_FILLED);
        $this->assertEquals(3, Order::STATUS_CANCELLED);

        $this->assertEquals('buy', Order::SIDE_BUY);
        $this->assertEquals('sell', Order::SIDE_SELL);
    }

    public function test_trade_has_relationships(): void
    {
        $buyer = User::factory()->create();
        $seller = User::factory()->create();

        $buyOrder = Order::create([
            'user_id' => $buyer->id,
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
            'status' => Order::STATUS_FILLED,
        ]);

        $sellOrder = Order::create([
            'user_id' => $seller->id,
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
            'status' => Order::STATUS_FILLED,
        ]);

        $trade = Trade::create([
            'buy_order_id' => $buyOrder->id,
            'sell_order_id' => $sellOrder->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'symbol' => 'BTC',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
            'executed_at' => now(),
        ]);

        $this->assertEquals($buyer->id, $trade->buyer->id);
        $this->assertEquals($seller->id, $trade->seller->id);
        $this->assertEquals($buyOrder->id, $trade->buyOrder->id);
        $this->assertEquals($sellOrder->id, $trade->sellOrder->id);
    }
}
