<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('placed_by_user_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->string('affiliate_referral_code', 20)->nullable()->after('placed_by_user_id');
        });

        Schema::table('affiliate_commissions', function (Blueprint $table) {
            $table->unsignedInteger('order_net_margin')->default(0)->after('order_base_amount');
            $table->decimal('margin_share_percent', 5, 2)->default(0)->after('commission_percent');
        });

        $now = now();
        foreach ([
            ['commission_margin_share_min', '0.5'],
            ['commission_margin_share_max', '1.5'],
        ] as [$key, $value]) {
            if (! DB::table('affiliate_settings')->where('key', $key)->exists()) {
                DB::table('affiliate_settings')->insert([
                    'key' => $key,
                    'value' => $value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('affiliate_commissions', function (Blueprint $table) {
            $table->dropColumn(['order_net_margin', 'margin_share_percent']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['placed_by_user_id']);
            $table->dropColumn(['placed_by_user_id', 'affiliate_referral_code']);
        });
    }
};
