<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Order;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_get_order_book(): void
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

        Order::create([
            'user_id' => $user->id,
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => '51000.00000000',
            'amount' => '0.50000000',
            'status' => Order::STATUS_OPEN,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/orders?symbol=BTC');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'symbol',
                'buy_orders' => [
                    '*' => ['id', 'user_id', 'side', 'price', 'amount', 'filled_amount', 'remaining_amount', 'status', 'created_at']
                ],
                'sell_orders' => [
                    '*' => ['id', 'user_id', 'side', 'price', 'amount', 'filled_amount', 'remaining_amount', 'status', 'created_at']
                ]
            ])
            ->assertJson([
                'symbol' => 'BTC',
            ]);

        $this->assertCount(1, $response->json('buy_orders'));
        $this->assertCount(1, $response->json('sell_orders'));
    }

    public function test_user_can_create_buy_order_with_sufficient_balance(): void
    {
        $user = User::factory()->create([
            'balance' => '100000.00000000',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Order created successfully',
                'order' => [
                    'symbol' => 'BTC',
                    'side' => 'buy',
                    'price' => '50000.00000000',
                    'amount' => '1.00000000',
                    'status' => Order::STATUS_OPEN,
                ],
            ]);

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
            'status' => Order::STATUS_OPEN,
        ]);

        $user->refresh();
        $this->assertEquals('50000.00000000', $user->balance);
    }

    public function test_user_cannot_create_buy_order_with_insufficient_balance(): void
    {
        $user = User::factory()->create([
            'balance' => '1000.00000000',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Failed to create order',
                'error' => 'Insufficient balance',
            ]);

        $this->assertDatabaseMissing('orders', [
            'user_id' => $user->id,
            'symbol' => 'BTC',
        ]);

        $user->refresh();
        $this->assertEquals('1000.00000000', $user->balance);
    }

    public function test_user_can_create_sell_order_with_sufficient_assets(): void
    {
        $user = User::factory()->create();

        Asset::create([
            'user_id' => $user->id,
            'symbol' => 'BTC',
            'amount' => '2.00000000',
            'locked_amount' => '0.00000000',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => '50000.00000000',
            'amount' => '1.50000000',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Order created successfully',
                'order' => [
                    'symbol' => 'BTC',
                    'side' => 'sell',
                    'price' => '50000.00000000',
                    'amount' => '1.50000000',
                    'status' => Order::STATUS_OPEN,
                ],
            ]);

        $this->assertDatabaseHas('assets', [
            'user_id' => $user->id,
            'symbol' => 'BTC',
            'locked_amount' => '1.50000000',
        ]);
    }

    public function test_user_cannot_create_sell_order_with_insufficient_assets(): void
    {
        $user = User::factory()->create();

        Asset::create([
            'user_id' => $user->id,
            'symbol' => 'BTC',
            'amount' => '0.50000000',
            'locked_amount' => '0.00000000',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Failed to create order',
                'error' => 'Insufficient asset balance',
            ]);
    }

    public function test_user_can_cancel_open_order(): void
    {
        $user = User::factory()->create([
            'balance' => '10000.00000000',
        ]);

        $order = Order::create([
            'user_id' => $user->id,
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
            'status' => Order::STATUS_OPEN,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/orders/{$order->id}/cancel");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Order cancelled successfully',
                'order' => [
                    'id' => $order->id,
                    'status' => Order::STATUS_CANCELLED,
                ],
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => Order::STATUS_CANCELLED,
        ]);

        $user->refresh();
        $this->assertEquals('60000.00000000', $user->balance);
    }

    public function test_user_cannot_cancel_another_users_order(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $order = Order::create([
            'user_id' => $user1->id,
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
            'status' => Order::STATUS_OPEN,
        ]);

        Sanctum::actingAs($user2);

        $response = $this->postJson("/api/orders/{$order->id}/cancel");

        $response->assertStatus(400);
    }

    public function test_buy_and_sell_orders_match_automatically(): void
    {
        $buyer = User::factory()->create([
            'balance' => '100000.00000000',
        ]);

        $seller = User::factory()->create();

        Asset::create([
            'user_id' => $seller->id,
            'symbol' => 'BTC',
            'amount' => '2.00000000',
            'locked_amount' => '0.00000000',
        ]);

        Sanctum::actingAs($seller);
        $sellResponse = $this->postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
        ]);

        $sellResponse->assertStatus(201);

        Sanctum::actingAs($buyer);
        $buyResponse = $this->postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
        ]);

        $buyResponse->assertStatus(201)
            ->assertJson([
                'order' => [
                    'filled_amount' => '1.00000000',
                    'status' => Order::STATUS_FILLED,
                ],
            ]);

        $this->assertDatabaseHas('trades', [
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'symbol' => 'BTC',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
        ]);

        $buyer->refresh();
        $this->assertEquals('50000.00000000', $buyer->balance);

        $seller->refresh();
        $this->assertEquals('50000.00000000', $seller->balance);

        $buyerAsset = Asset::where('user_id', $buyer->id)->where('symbol', 'BTC')->first();
        $this->assertEquals('1.00000000', $buyerAsset->amount);

        $sellerAsset = Asset::where('user_id', $seller->id)->where('symbol', 'BTC')->first();
        $this->assertEquals('1.00000000', $sellerAsset->amount);
        $this->assertEquals('0.00000000', $sellerAsset->locked_amount);
    }

    public function test_partial_order_matching(): void
    {
        $buyer = User::factory()->create([
            'balance' => '100000.00000000',
        ]);

        $seller = User::factory()->create();

        Asset::create([
            'user_id' => $seller->id,
            'symbol' => 'BTC',
            'amount' => '2.00000000',
            'locked_amount' => '0.00000000',
        ]);

        Sanctum::actingAs($seller);
        $this->postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => '50000.00000000',
            'amount' => '0.50000000',
        ]);

        Sanctum::actingAs($buyer);
        $buyResponse = $this->postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => '50000.00000000',
            'amount' => '1.00000000',
        ]);

        $buyResponse->assertStatus(201)
            ->assertJson([
                'order' => [
                    'filled_amount' => '0.50000000',
                    'remaining_amount' => '0.50000000',
                    'status' => Order::STATUS_OPEN,
                ],
            ]);

        $this->assertEquals(1, Trade::count());
    }

    public function test_order_validation_fails_with_invalid_data(): void
    {
        $user = User::factory()->create([
            'balance' => '100000.00000000',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'invalid',
            'price' => '-100',
            'amount' => '0',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['side', 'price', 'amount']);
    }
}
