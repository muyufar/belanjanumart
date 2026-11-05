<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('marketplace_role', 30)->default('member')->after('price_tier');
            $table->string('partner_kind', 20)->nullable()->after('marketplace_role');
            $table->foreignId('affiliate_kader_id')->nullable()->after('partner_kind')->constrained('users')->nullOnDelete();
            $table->string('affiliate_code', 20)->nullable()->unique()->after('affiliate_kader_id');
            $table->boolean('affiliate_active')->default(true)->after('affiliate_code');
        });

        Schema::create('affiliate_settings', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('affiliate_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referred_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('numart_customer_id')->nullable();
            $table->string('member_card', 32);
            $table->string('partner_kind', 20);
            $table->string('customer_name', 200);
            $table->string('customer_phone', 30)->nullable();
            $table->string('warung_name')->nullable();
            $table->timestamps();

            $table->unique('member_card');
            $table->index(['affiliate_user_id', 'created_at']);
        });

        Schema::create('affiliate_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referred_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('partner_kind', 20);
            $table->unsignedInteger('order_base_amount')->default(0);
            $table->decimal('commission_percent', 5, 2)->default(0);
            $table->unsignedInteger('commission_amount')->default(0);
            $table->string('status', 20)->default('accrued');
            $table->timestamp('accrued_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('paid_note', 500)->nullable();
            $table->timestamps();

            $table->unique('order_id');
            $table->index(['affiliate_user_id', 'status']);
        });

        $now = now();
        DB::table('affiliate_settings')->insert([
            ['key' => 'commission_warung_percent', 'value' => '1.5', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'commission_prioritas_percent', 'value' => '2.5', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'commission_basis', 'value' => 'subtotal', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_commissions');
        Schema::dropIfExists('affiliate_referrals');
        Schema::dropIfExists('affiliate_settings');

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['affiliate_kader_id']);
            $table->dropColumn([
                'marketplace_role',
                'partner_kind',
                'affiliate_kader_id',
                'affiliate_code',
                'affiliate_active',
            ]);
        });
    }
};
