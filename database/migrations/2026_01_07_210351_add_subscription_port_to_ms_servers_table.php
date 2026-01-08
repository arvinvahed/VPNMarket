<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('ms_servers', function (Blueprint $table) {
            $table->integer('subscription_port')->nullable()->after('port');
        });
    }

    public function down()
    {
        Schema::table('ms_servers', function (Blueprint $table) {
            $table->dropColumn('subscription_port');
        });
    }
};
