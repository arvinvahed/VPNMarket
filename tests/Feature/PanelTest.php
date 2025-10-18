<?php

namespace Tests\Feature;

use App\Models\Panel;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_panel(): void
    {
        $panel = Panel::create([
            'name' => 'Test Panel',
            'url' => 'https://panel.test.com',
            'panel_type' => 'marzban',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('panels', [
            'name' => 'Test Panel',
            'url' => 'https://panel.test.com',
            'panel_type' => 'marzban',
        ]);
    }

    public function test_panel_has_plans_relationship(): void
    {
        $panel = Panel::create([
            'name' => 'Test Panel',
            'url' => 'https://panel.test.com',
            'panel_type' => 'marzban',
            'is_active' => true,
        ]);

        $plan = Plan::create([
            'name' => 'Test Plan',
            'price' => 100,
            'currency' => 'تومان',
            'features' => 'Feature 1',
            'volume_gb' => 30,
            'duration_days' => 30,
            'panel_id' => $panel->id,
            'is_active' => true,
        ]);

        $this->assertEquals(1, $panel->plans()->count());
        $this->assertEquals($panel->id, $plan->panel->id);
    }

    public function test_panel_encrypts_password(): void
    {
        $panel = Panel::create([
            'name' => 'Test Panel',
            'url' => 'https://panel.test.com',
            'panel_type' => 'marzban',
            'username' => 'admin',
            'password' => 'secret123',
            'is_active' => true,
        ]);

        // Retrieve fresh instance
        $panel->refresh();

        // Password should be encrypted in database
        $this->assertNotEquals('secret123', $panel->getRawOriginal('password'));
        
        // But accessible as plain text via accessor
        $this->assertEquals('secret123', $panel->password);
    }

    public function test_panel_encrypts_api_token(): void
    {
        $panel = Panel::create([
            'name' => 'Test Panel',
            'url' => 'https://panel.test.com',
            'panel_type' => 'marzban',
            'api_token' => 'token123',
            'is_active' => true,
        ]);

        $panel->refresh();

        // Token should be encrypted in database
        $this->assertNotEquals('token123', $panel->getRawOriginal('api_token'));
        
        // But accessible as plain text via accessor
        $this->assertEquals('token123', $panel->api_token);
    }

    public function test_admin_can_list_panels(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        
        Panel::create([
            'name' => 'Panel 1',
            'url' => 'https://panel1.test.com',
            'panel_type' => 'marzban',
        ]);

        Panel::create([
            'name' => 'Panel 2',
            'url' => 'https://panel2.test.com',
            'panel_type' => 'marzneshin',
        ]);

        $response = $this->actingAs($admin)->getJson('/api/admin/panels');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'url',
                        'panel_type',
                        'username',
                        'has_password',
                        'has_api_token',
                        'is_active',
                    ]
                ]
            ])
            ->assertJsonCount(2, 'data');
    }

    public function test_non_admin_cannot_list_panels(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($user)->getJson('/api/admin/panels');

        $response->assertStatus(403);
    }

    public function test_admin_can_create_panel_via_api(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->postJson('/api/admin/panels', [
            'name' => 'New Panel',
            'url' => 'https://newpanel.test.com',
            'panel_type' => 'marzban',
            'username' => 'admin',
            'password' => 'secret',
            'is_active' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'name',
                    'url',
                    'panel_type',
                    'has_password',
                ]
            ]);

        $this->assertDatabaseHas('panels', [
            'name' => 'New Panel',
            'url' => 'https://newpanel.test.com',
        ]);
    }

    public function test_admin_can_update_panel_via_api(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        
        $panel = Panel::create([
            'name' => 'Old Panel',
            'url' => 'https://old.test.com',
            'panel_type' => 'marzban',
        ]);

        $response = $this->actingAs($admin)->putJson("/api/admin/panels/{$panel->id}", [
            'name' => 'Updated Panel',
            'url' => 'https://updated.test.com',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('panels', [
            'id' => $panel->id,
            'name' => 'Updated Panel',
            'url' => 'https://updated.test.com',
        ]);
    }

    public function test_admin_can_delete_panel_without_plans(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        
        $panel = Panel::create([
            'name' => 'Panel to Delete',
            'url' => 'https://delete.test.com',
            'panel_type' => 'marzban',
        ]);

        $response = $this->actingAs($admin)->deleteJson("/api/admin/panels/{$panel->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('panels', [
            'id' => $panel->id,
        ]);
    }

    public function test_cannot_delete_panel_with_associated_plans(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        
        $panel = Panel::create([
            'name' => 'Panel with Plans',
            'url' => 'https://panel.test.com',
            'panel_type' => 'marzban',
        ]);

        Plan::create([
            'name' => 'Test Plan',
            'price' => 100,
            'currency' => 'تومان',
            'features' => 'Feature 1',
            'volume_gb' => 30,
            'duration_days' => 30,
            'panel_id' => $panel->id,
        ]);

        $response = $this->actingAs($admin)->deleteJson("/api/admin/panels/{$panel->id}");

        $response->assertStatus(422)
            ->assertJsonFragment(['success' => false]);

        $this->assertDatabaseHas('panels', [
            'id' => $panel->id,
        ]);
    }

    public function test_panel_credentials_hidden_in_json(): void
    {
        $panel = Panel::create([
            'name' => 'Test Panel',
            'url' => 'https://panel.test.com',
            'panel_type' => 'marzban',
            'password' => 'secret123',
            'api_token' => 'token123',
        ]);

        $json = $panel->toArray();

        $this->assertArrayNotHasKey('password', $json);
        $this->assertArrayNotHasKey('api_token', $json);
    }
}
