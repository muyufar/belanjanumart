<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('member_card', 32)->nullable()->unique()->after('numart_customer_id');
            $table->unsignedInteger('member_cabang')->nullable()->after('price_tier');
            $table->string('member_verification_status', 20)->default('none')->after('warung_verification_status');
            $table->string('ktp_path')->nullable()->after('member_verification_status');
            $table->string('business_photo_path')->nullable()->after('ktp_path');
            $table->timestamp('verification_submitted_at')->nullable()->after('business_photo_path');
            $table->timestamp('verification_reviewed_at')->nullable()->after('verification_submitted_at');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_method', 20)->default('transfer')->after('grand_total');
            $table->string('payment_proof_path')->nullable()->after('payment_method');
            $table->timestamp('payment_proof_at')->nullable()->after('payment_proof_path');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'payment_proof_path', 'payment_proof_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'member_card',
                'member_cabang',
                'member_verification_status',
                'ktp_path',
                'business_photo_path',
                'verification_submitted_at',
                'verification_reviewed_at',
            ]);
        });
    }
};
