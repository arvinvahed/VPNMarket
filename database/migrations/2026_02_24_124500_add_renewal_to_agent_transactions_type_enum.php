<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For MySQL, we can use DB::statement to modify the ENUM column
        DB::statement("ALTER TABLE agent_transactions MODIFY COLUMN type ENUM('deposit', 'server_purchase', 'account_sale', 'withdraw', 'manual', 'renewal') DEFAULT 'deposit'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: If there are 'renewal' records, this might fail or truncate data.
        // Ideally we should handle that, but for now reverting simply removes the option.
        DB::statement("ALTER TABLE agent_transactions MODIFY COLUMN type ENUM('deposit', 'server_purchase', 'account_sale', 'withdraw', 'manual') DEFAULT 'deposit'");
    }
};
