<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shipping_campaigns', function (Blueprint $t) {
            $t->unsignedInteger('id')->primary();
            $t->unsignedInteger('branch_id')->default(0);
            $t->boolean('enabled')->default(false);
            $t->text('store_json')->nullable();
            $t->text('policy_json');
            $t->unsignedInteger('promo_limit')->default(300000);
            $t->unsignedInteger('fallback_limit')->default(300000);
            $t->dateTime('ends_at')->nullable();
        });
        require_once app_path('Support/NugrosirShipping.php');
        DB::table('shipping_campaigns')->insert(['id' => 1, 'policy_json' => json_encode(\Nugrosir\Shipping::policy())]);
        Schema::create('shipping_trips', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('branch_id');
            $t->string('status', 20);
            $t->unsignedInteger('courier_id')->nullable()->index();
            $t->unsignedInteger('agreed_pay');
            $t->unsignedInteger('fallback_reserve')->default(0);
            $t->unsignedInteger('fallback_spent')->default(0);
            $t->unsignedInteger('actual_pay')->nullable();
            $t->text('snapshot_json');
            $t->text('actual_json')->nullable();
            $t->dateTime('created_at');
            $t->dateTime('settled_at')->nullable();
        });
        Schema::create('shipping_quotes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->unsignedInteger('branch_id');
            $t->foreignId('trip_id')->nullable()->constrained('shipping_trips');
            $t->foreignId('order_id')->nullable()->unique()->constrained('orders');
            $t->string('fingerprint', 64);
            $t->string('status', 20)->index();
            $t->text('request_json');
            $t->text('snapshot_json')->nullable();
            $t->unsignedInteger('subsidy')->default(0);
            $t->unsignedInteger('actual_allocation')->nullable();
            $t->dateTime('created_at');
            $t->dateTime('expires_at')->nullable();
            $t->index(['user_id','fingerprint']);
        });
        Schema::create('shipping_events', function (Blueprint $t) {
            $t->id();
            $t->string('kind', 40);
            $t->unsignedBigInteger('entity_id');
            $t->string('actor', 100);
            $t->text('payload');
            $t->dateTime('created_at');
        });
        Schema::table('orders', function (Blueprint $t) {
            $t->foreignId('shipping_quote_id')->nullable()->unique()->constrained('shipping_quotes');
            $t->text('shipping_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->dropConstrainedForeignId('shipping_quote_id');
            $t->dropColumn('shipping_snapshot');
        });
        Schema::dropIfExists('shipping_events');
        Schema::dropIfExists('shipping_quotes');
        Schema::dropIfExists('shipping_trips');
        Schema::dropIfExists('shipping_campaigns');
    }
};
