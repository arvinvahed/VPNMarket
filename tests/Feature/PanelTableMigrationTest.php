<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PanelTableMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_adds_panel_type_column_to_existing_table(): void
    {
        // Create a minimal panels table without panel_type column
        // This simulates the scenario where an older version of the table exists
        Schema::dropIfExists('panels');
        Schema::create('panels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url');
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->timestamps();
        });

        // Verify panel_type column doesn't exist
        $this->assertFalse(Schema::hasColumn('panels', 'panel_type'));

        // Manually run the migration's up() method
        $migration = require database_path('migrations/2025_10_18_201441_create_panels_table.php');
        $migration->up();

        // Verify panel_type column was added
        $this->assertTrue(Schema::hasColumn('panels', 'panel_type'));
        $this->assertTrue(Schema::hasColumn('panels', 'api_token'));
        $this->assertTrue(Schema::hasColumn('panels', 'extra'));
        $this->assertTrue(Schema::hasColumn('panels', 'is_active'));
    }

    public function test_migration_creates_table_if_not_exists(): void
    {
        // Drop the table if it exists
        Schema::dropIfExists('panels');

        // Verify table doesn't exist
        $this->assertFalse(Schema::hasTable('panels'));

        // Manually run the migration's up() method
        $migration = require database_path('migrations/2025_10_18_201441_create_panels_table.php');
        $migration->up();

        // Verify table and all columns were created
        $this->assertTrue(Schema::hasTable('panels'));
        $this->assertTrue(Schema::hasColumn('panels', 'panel_type'));
        $this->assertTrue(Schema::hasColumn('panels', 'name'));
        $this->assertTrue(Schema::hasColumn('panels', 'url'));
        $this->assertTrue(Schema::hasColumn('panels', 'username'));
        $this->assertTrue(Schema::hasColumn('panels', 'password'));
        $this->assertTrue(Schema::hasColumn('panels', 'api_token'));
        $this->assertTrue(Schema::hasColumn('panels', 'extra'));
        $this->assertTrue(Schema::hasColumn('panels', 'is_active'));
    }
}
