<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ms_servers', function (Blueprint $table) {
            $table->string('type')->default('xui')->after('location_id'); // xui, marzban
            $table->integer('inbound_id')->nullable()->change();
            $table->string('marzban_node_hostname')->nullable()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('ms_servers', function (Blueprint $table) {
            $table->dropColumn(['type', 'marzban_node_hostname']);
            $table->integer('inbound_id')->nullable(false)->change();
        });
    }
};
