<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_get_profile_with_balance_and_assets(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'balance' => '10000.00000000',
        ]);

        Asset::create([
            'user_id' => $user->id,
            'symbol' => 'BTC',
            'amount' => '1.50000000',
            'locked_amount' => '0.50000000',
        ]);

        Asset::create([
            'user_id' => $user->id,
            'symbol' => 'ETH',
            'amount' => '10.00000000',
            'locked_amount' => '0.00000000',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/profile');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'balance'],
                'assets' => [
                    '*' => ['symbol', 'amount', 'locked_amount', 'available_amount']
                ]
            ])
            ->assertJson([
                'user' => [
                    'id' => $user->id,
                    'name' => 'Test User',
                    'email' => 'test@example.com',
                    'balance' => '10000.00000000',
                ],
            ]);

        $this->assertCount(2, $response->json('assets'));

        $btcAsset = collect($response->json('assets'))->firstWhere('symbol', 'BTC');
        $this->assertEquals('1.50000000', $btcAsset['amount']);
        $this->assertEquals('0.50000000', $btcAsset['locked_amount']);
        $this->assertEquals('1.00000000', $btcAsset['available_amount']);
    }

    public function test_unauthenticated_user_cannot_access_profile(): void
    {
        $response = $this->getJson('/api/profile');

        $response->assertStatus(401);
    }

    public function test_profile_shows_empty_assets_for_new_user(): void
    {
        $user = User::factory()->create([
            'balance' => '5000.00000000',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/profile');

        $response->assertStatus(200)
            ->assertJson([
                'user' => [
                    'balance' => '5000.00000000',
                ],
                'assets' => [],
            ]);
    }
}
