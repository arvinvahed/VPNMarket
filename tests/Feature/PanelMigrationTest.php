<?php

namespace Tests\Feature;

use App\Models\Panel;
use App\Models\Plan;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_creates_default_panel_from_marzban_settings(): void
    {
        // First, rollback the specific migration
        $this->artisan('migrate:rollback', ['--path' => 'database/migrations/2025_10_18_210027_migrate_legacy_panel_settings_to_default_panel.php']);

        // Create legacy Marzban settings
        Setting::create(['key' => 'panel_type', 'value' => 'marzban']);
        Setting::create(['key' => 'marzban_host', 'value' => 'https://marzban.test.com']);
        Setting::create(['key' => 'marzban_sudo_username', 'value' => 'admin']);
        Setting::create(['key' => 'marzban_sudo_password', 'value' => 'password123']);
        Setting::create(['key' => 'marzban_node_hostname', 'value' => 'https://node.test.com']);

        // Create some plans without panel_id
        $plan1 = Plan::create([
            'name' => 'Plan 1',
            'price' => 100,
            'currency' => 'تومان',
            'features' => 'Feature 1',
            'volume_gb' => 30,
            'duration_days' => 30,
        ]);

        $plan2 = Plan::create([
            'name' => 'Plan 2',
            'price' => 200,
            'currency' => 'تومان',
            'features' => 'Feature 2',
            'volume_gb' => 60,
            'duration_days' => 30,
        ]);

        // Run the migration
        $this->artisan('migrate', ['--path' => 'database/migrations/2025_10_18_210027_migrate_legacy_panel_settings_to_default_panel.php']);

        // Assert default panel was created
        $defaultPanel = Panel::where('name', 'Default Panel (Migrated)')->first();
        $this->assertNotNull($defaultPanel);
        $this->assertEquals('marzban', $defaultPanel->panel_type);
        $this->assertEquals('https://marzban.test.com', $defaultPanel->url);
        $this->assertEquals('admin', $defaultPanel->username);
        $this->assertEquals('password123', $defaultPanel->password);
        $this->assertEquals('https://node.test.com', $defaultPanel->extra['node_hostname']);

        // Assert plans were associated with the default panel
        $plan1->refresh();
        $plan2->refresh();
        $this->assertEquals($defaultPanel->id, $plan1->panel_id);
        $this->assertEquals($defaultPanel->id, $plan2->panel_id);
    }

    public function test_panel_get_credentials_returns_correct_data(): void
    {
        $panel = Panel::create([
            'name' => 'Test Panel',
            'url' => 'https://panel.test.com',
            'panel_type' => 'marzban',
            'username' => 'admin',
            'password' => 'secret123',
            'api_token' => 'token456',
            'extra' => ['node_hostname' => 'https://node.test.com'],
        ]);

        $credentials = $panel->getCredentials();

        $this->assertEquals('https://panel.test.com', $credentials['url']);
        $this->assertEquals('admin', $credentials['username']);
        $this->assertEquals('secret123', $credentials['password']);
        $this->assertEquals('token456', $credentials['api_token']);
        $this->assertEquals('https://node.test.com', $credentials['extra']['node_hostname']);
    }
}
