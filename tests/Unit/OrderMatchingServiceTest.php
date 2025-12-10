<?php

namespace Tests\Unit;

use App\Models\Asset;
use App\Models\Order;
use App\Models\Trade;
use App\Models\User;
use App\Services\OrderMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderMatchingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected OrderMatchingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new OrderMatchingService();
    }

    public function test_matching_buy_order_finds_sell_order_at_same_price(): void
    {
        $buyer = User::factory()->create(['balance' => '100000.00000000']);
        $seller = User::factory()->create();

        Asset::create([
            'user_id' => $seller->id,
            'symbol' => 'BTC',
            'amount' => '2.00000000',
            'locked_amount' => '1.00000000',
        ]);

        $sellOrder = Order::create([
            'user_id' => $seller->id,
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
            'status' => Order::STATUS_OPEN,
        ]);

        $buyOrder = Order::create([
            'user_id' => $buyer->id,
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
            'status' => Order::STATUS_OPEN,
        ]);

        $this->service->matchOrder($buyOrder);

        $buyOrder->refresh();
        $sellOrder->refresh();

        $this->assertEquals('1.00000000', $buyOrder->filled_amount);
        $this->assertEquals(Order::STATUS_FILLED, $buyOrder->status);

        $this->assertEquals('1.00000000', $sellOrder->filled_amount);
        $this->assertEquals(Order::STATUS_FILLED, $sellOrder->status);

        $this->assertEquals(1, Trade::count());
    }

    public function test_matching_buy_order_finds_cheaper_sell_order(): void
    {
        $buyer = User::factory()->create(['balance' => '100000.00000000']);
        $seller = User::factory()->create();

        Asset::create([
            'user_id' => $seller->id,
            'symbol' => 'BTC',
            'amount' => '2.00000000',
            'locked_amount' => '1.00000000',
        ]);

        $sellOrder = Order::create([
            'user_id' => $seller->id,
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => '48000.00000000',
            'amount' => '1.00000000',
            'status' => Order::STATUS_OPEN,
        ]);

        $buyOrder = Order::create([
            'user_id' => $buyer->id,
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
            'status' => Order::STATUS_OPEN,
        ]);

        $this->service->matchOrder($buyOrder);

        $trade = Trade::first();
        $this->assertEquals('48000.00000000', $trade->price);
        $this->assertEquals('1.00000000', $trade->amount);
    }

    public function test_partial_match_when_sell_order_is_smaller(): void
    {
        $buyer = User::factory()->create(['balance' => '100000.00000000']);
        $seller = User::factory()->create();

        Asset::create([
            'user_id' => $seller->id,
            'symbol' => 'BTC',
            'amount' => '2.00000000',
            'locked_amount' => '0.50000000',
        ]);

        $sellOrder = Order::create([
            'user_id' => $seller->id,
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => '50000.00000000',
            'amount' => '0.50000000',
            'status' => Order::STATUS_OPEN,
        ]);

        $buyOrder = Order::create([
            'user_id' => $buyer->id,
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
            'status' => Order::STATUS_OPEN,
        ]);

        $this->service->matchOrder($buyOrder);

        $buyOrder->refresh();
        $sellOrder->refresh();

        $this->assertEquals('0.50000000', $buyOrder->filled_amount);
        $this->assertEquals('0.50000000', $buyOrder->remaining_amount);
        $this->assertEquals(Order::STATUS_OPEN, $buyOrder->status);

        $this->assertEquals('0.50000000', $sellOrder->filled_amount);
        $this->assertEquals(Order::STATUS_FILLED, $sellOrder->status);
    }

    public function test_multiple_sell_orders_matched_with_single_buy_order(): void
    {
        $buyer = User::factory()->create(['balance' => '100000.00000000']);
        $seller1 = User::factory()->create();
        $seller2 = User::factory()->create();

        Asset::create([
            'user_id' => $seller1->id,
            'symbol' => 'BTC',
            'amount' => '2.00000000',
            'locked_amount' => '0.40000000',
        ]);

        Asset::create([
            'user_id' => $seller2->id,
            'symbol' => 'BTC',
            'amount' => '2.00000000',
            'locked_amount' => '0.60000000',
        ]);

        $sellOrder1 = Order::create([
            'user_id' => $seller1->id,
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => '50000.00000000',
            'amount' => '0.40000000',
            'status' => Order::STATUS_OPEN,
        ]);

        $sellOrder2 = Order::create([
            'user_id' => $seller2->id,
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => '50000.00000000',
            'amount' => '0.60000000',
            'status' => Order::STATUS_OPEN,
        ]);

        $buyOrder = Order::create([
            'user_id' => $buyer->id,
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
            'status' => Order::STATUS_OPEN,
        ]);

        $this->service->matchOrder($buyOrder);

        $buyOrder->refresh();

        $this->assertEquals('1.00000000', $buyOrder->filled_amount);
        $this->assertEquals(Order::STATUS_FILLED, $buyOrder->status);
        $this->assertEquals(2, Trade::count());
    }

    public function test_sell_order_does_not_match_with_lower_buy_order(): void
    {
        $buyer = User::factory()->create(['balance' => '100000.00000000']);
        $seller = User::factory()->create();

        Asset::create([
            'user_id' => $seller->id,
            'symbol' => 'BTC',
            'amount' => '2.00000000',
            'locked_amount' => '1.00000000',
        ]);

        Order::create([
            'user_id' => $buyer->id,
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => '48000.00000000',
            'amount' => '1.00000000',
            'status' => Order::STATUS_OPEN,
        ]);

        $sellOrder = Order::create([
            'user_id' => $seller->id,
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
            'status' => Order::STATUS_OPEN,
        ]);

        $this->service->matchOrder($sellOrder);

        $sellOrder->refresh();

        $this->assertEquals('0.00000000', $sellOrder->filled_amount);
        $this->assertEquals(Order::STATUS_OPEN, $sellOrder->status);
        $this->assertEquals(0, Trade::count());
    }

    public function test_balance_and_asset_updates_after_match(): void
    {
        $buyer = User::factory()->create(['balance' => '100000.00000000']);
        $seller = User::factory()->create(['balance' => '0.00000000']);

        Asset::create([
            'user_id' => $seller->id,
            'symbol' => 'BTC',
            'amount' => '2.00000000',
            'locked_amount' => '1.00000000',
        ]);

        $sellOrder = Order::create([
            'user_id' => $seller->id,
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
            'status' => Order::STATUS_OPEN,
        ]);

        $buyOrder = Order::create([
            'user_id' => $buyer->id,
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
            'status' => Order::STATUS_OPEN,
        ]);

        $this->service->matchOrder($buyOrder);

        $buyer->refresh();
        $seller->refresh();

        $this->assertEquals('100000.00000000', $buyer->balance);
        $this->assertEquals('50000.00000000', $seller->balance);

        $buyerAsset = Asset::where('user_id', $buyer->id)->where('symbol', 'BTC')->first();
        $this->assertEquals('1.00000000', $buyerAsset->amount);

        $sellerAsset = Asset::where('user_id', $seller->id)->where('symbol', 'BTC')->first();
        $this->assertEquals('1.00000000', $sellerAsset->amount);
        $this->assertEquals('0.00000000', $sellerAsset->locked_amount);
    }

    public function test_price_priority_cheapest_sell_order_matched_first(): void
    {
        $buyer = User::factory()->create(['balance' => '100000.00000000']);
        $seller1 = User::factory()->create();
        $seller2 = User::factory()->create();

        Asset::create([
            'user_id' => $seller1->id,
            'symbol' => 'BTC',
            'amount' => '2.00000000',
            'locked_amount' => '1.00000000',
        ]);

        Asset::create([
            'user_id' => $seller2->id,
            'symbol' => 'BTC',
            'amount' => '2.00000000',
            'locked_amount' => '1.00000000',
        ]);

        Order::create([
            'user_id' => $seller1->id,
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => '51000.00000000',
            'amount' => '1.00000000',
            'status' => Order::STATUS_OPEN,
        ]);

        Order::create([
            'user_id' => $seller2->id,
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => '49000.00000000',
            'amount' => '1.00000000',
            'status' => Order::STATUS_OPEN,
        ]);

        $buyOrder = Order::create([
            'user_id' => $buyer->id,
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => '52000.00000000',
            'amount' => '1.00000000',
            'status' => Order::STATUS_OPEN,
        ]);

        $this->service->matchOrder($buyOrder);

        $trade = Trade::first();
        $this->assertEquals('49000.00000000', $trade->price);
        $this->assertEquals($seller2->id, $trade->seller_id);
    }
}
