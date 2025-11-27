<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('panel_client_id', 100)->nullable()->after('panel_username');
            $table->string('panel_sub_id', 100)->nullable()->after('panel_client_id');
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['panel_client_id', 'panel_sub_id']);
        });
    }
};
