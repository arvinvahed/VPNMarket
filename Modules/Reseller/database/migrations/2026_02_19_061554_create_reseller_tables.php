<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. VPN Servers
        Schema::create('vpn_servers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['sanaei', 'marzban', 'other'])->default('sanaei');
            $table->string('ip_address');
            $table->integer('port')->default(2053);
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->string('api_path')->default('/panel/api/inbounds'); // Default for Sanaei
            $table->boolean('is_active')->default(true);
            $table->integer('capacity')->default(0); // 0 means unlimited or unknown
            $table->json('config')->nullable(); // Extra config like tls, sni, etc.
            $table->timestamps();
        });

        // 2. VPN Products (Plans on the server)
        Schema::create('vpn_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained('vpn_servers')->onDelete('cascade');
            $table->string('name');
            $table->string('remote_id')->nullable(); // inboundId for Sanaei, plan_id/template_id for Marzban
            $table->enum('protocol', ['vless', 'vmess', 'trojan', 'shadowsocks', 'other'])->default('vless');
            $table->bigInteger('traffic_limit')->default(0); // In Bytes. 0 = Unlimited
            $table->integer('period_days')->default(30);
            $table->decimal('base_price', 15, 0)->default(0); // Base cost for admin calculation
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 3. Reseller Plans (The business logic plans)
        Schema::create('reseller_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['quota', 'pay_as_you_go']); // Mode 1 vs Mode 2
            $table->integer('account_limit')->default(0); // For quota mode
            $table->decimal('price', 15, 0)->default(0); // Price of the plan itself (e.g. monthly subscription or one-time fee)
            $table->decimal('price_per_account', 15, 0)->default(0); // For pay_as_you_go mode
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        // 4. Resellers (Profile linked to User)
        Schema::create('resellers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('plan_id')->nullable()->constrained('reseller_plans')->onDelete('set null');
            $table->enum('status', ['active', 'inactive', 'banned'])->default('active');
            $table->string('telegram_username')->nullable();
            $table->string('phone')->nullable();
            $table->text('description')->nullable();
            $table->integer('max_accounts')->default(0); // Override or cache from plan
            $table->softDeletes();
            $table->timestamps();
        });

        // 5. Reseller Wallets
        Schema::create('reseller_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reseller_id')->constrained('resellers')->onDelete('cascade');
            $table->decimal('balance', 15, 0)->default(0);
            $table->timestamps();
        });

        // 6. Reseller Requests (Application form)
        Schema::create('reseller_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('name');
            $table->string('phone');
            $table->string('telegram_username')->nullable();
            $table->text('description')->nullable();
            $table->decimal('payment_amount', 15, 0)->nullable();
            $table->string('payment_receipt_path')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });

        // 7. Reseller Transactions
        Schema::create('reseller_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('reseller_wallets')->onDelete('cascade');
            $table->enum('type', ['deposit', 'withdrawal', 'purchase', 'refund', 'commission']);
            $table->decimal('amount', 15, 0);
            $table->string('description')->nullable();
            $table->string('reference_type')->nullable(); // e.g., 'App\Modules\Reseller\Models\ResellerAccount'
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->timestamps();
            
            $table->index(['wallet_id', 'created_at']);
        });

        // 8. Reseller Accounts (Created VPN accounts)
        Schema::create('reseller_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reseller_id')->constrained('resellers')->onDelete('cascade');
            $table->foreignId('server_id')->constrained('vpn_servers')->onDelete('cascade'); // Or set null if server deleted
            $table->foreignId('product_id')->nullable()->constrained('vpn_products')->onDelete('set null');
            
            $table->string('username'); // The VPN username/email
            $table->string('uuid')->nullable(); // VLESS UUID or password
            $table->text('subscription_url')->nullable();
            
            $table->enum('status', ['active', 'expired', 'disabled', 'creating', 'failed'])->default('creating');
            $table->timestamp('expired_at')->nullable();
            $table->decimal('price_deducted', 15, 0)->default(0);
            
            $table->json('server_response')->nullable(); // Store raw response for debug
            
            $table->softDeletes();
            $table->timestamps();

            $table->index(['reseller_id', 'status']);
        });

        // 9. Logs
        Schema::create('reseller_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reseller_id')->nullable()->constrained('resellers')->onDelete('cascade');
            $table->string('action');
            $table->json('payload')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reseller_logs');
        Schema::dropIfExists('reseller_accounts');
        Schema::dropIfExists('reseller_transactions');
        Schema::dropIfExists('reseller_requests');
        Schema::dropIfExists('reseller_wallets');
        Schema::dropIfExists('resellers');
        Schema::dropIfExists('reseller_plans');
        Schema::dropIfExists('vpn_products');
        Schema::dropIfExists('vpn_servers');
    }
};
