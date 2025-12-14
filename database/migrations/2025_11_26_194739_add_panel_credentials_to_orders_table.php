<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {

            if (!Schema::hasColumn('orders', 'panel_client_id')) {
                $table->string('panel_client_id', 100)->nullable()->after('panel_username');
            }

            if (!Schema::hasColumn('orders', 'panel_sub_id')) {
                $table->string('panel_sub_id', 100)->nullable()->after('panel_client_id');
            }
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'panel_sub_id')) {
                $table->dropColumn('panel_sub_id');
            }
            if (Schema::hasColumn('orders', 'panel_client_id')) {
                $table->dropColumn('panel_client_id');
            }
        });
    }
};
