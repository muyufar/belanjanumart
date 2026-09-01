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
            $table->string('tracking_status', 40)->default('awaiting_payment')->after('status');
            $table->timestamp('tracking_updated_at')->nullable()->after('tracking_status');
            $table->string('tracking_note', 500)->nullable()->after('tracking_updated_at');
        });

        DB::table('orders')->whereIn('status', ['pending_transfer', 'pending_cod', 'proof_submitted', 'pending_payment'])
            ->update(['tracking_status' => 'awaiting_payment']);

        DB::table('orders')->where('status', 'processing')
            ->update(['tracking_status' => 'preparing']);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['tracking_status', 'tracking_updated_at', 'tracking_note']);
        });
    }
};
