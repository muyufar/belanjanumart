<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D-31 — pindah dari DB POS lama ke API NUMART ERP.
 * - users/affiliate_referrals.erp_customer_id: id pelanggan ERP. Sengaja kolom TERPISAH dari numart_customer_id (id POS lama):
 *   kedua deret id tumpang-tindih secara angka, jadi menimpa kolom lama berisiko menautkan akun ke orang lain dan
 *   mematahkan rollback NUMART_SOURCE=pos.
 * - orders.numart_source: sistem tempat pesanan direservasi/dipenuhi (pos|erp) — status kirim & nota dibaca dari sana.
 * - numart_erp_webhook_events: idempotensi webhook ERP (X-Event-Id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('erp_customer_id')->nullable()->index()->after('numart_customer_id');
        });

        Schema::table('affiliate_referrals', function (Blueprint $table) {
            $table->unsignedBigInteger('erp_customer_id')->nullable()->after('numart_customer_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('numart_source', 8)->nullable()->after('numart_invoice');
        });

        Schema::create('numart_erp_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id', 64)->unique();
            $table->string('event', 64);
            $table->string('order_number', 64)->nullable()->index();
            $table->json('payload');
            $table->string('result', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('numart_erp_webhook_events');
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('numart_source'));
        Schema::table('affiliate_referrals', fn (Blueprint $table) => $table->dropColumn('erp_customer_id'));
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['erp_customer_id']);
            $table->dropColumn('erp_customer_id');
        });
    }
};
