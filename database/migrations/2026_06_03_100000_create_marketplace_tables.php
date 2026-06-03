<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('numart_customer_id')->nullable()->after('id');
            $table->unsignedTinyInteger('price_tier')->default(0)->comment('0=umum,1=retail,2=grosir');
            $table->string('phone', 30)->nullable();
            $table->text('address')->nullable();
            $table->string('warung_name')->nullable();
            $table->string('warung_verification_status', 20)->default('none');
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 32)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('price_tier')->default(0);
            $table->unsignedInteger('fulfillment_cabang')->default(0);
            $table->string('fulfillment_label', 100)->nullable();
            $table->decimal('customer_lat', 10, 7)->nullable();
            $table->decimal('customer_lng', 10, 7)->nullable();
            $table->string('customer_name', 200);
            $table->string('customer_phone', 30);
            $table->text('customer_address');
            $table->unsignedInteger('subtotal')->default(0);
            $table->unsignedInteger('shipping_fee')->default(0);
            $table->unsignedInteger('discount')->default(0);
            $table->unsignedInteger('grand_total')->default(0);
            $table->string('status', 30)->default('pending_payment');
            $table->string('numart_invoice', 64)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('barang_id');
            $table->string('barang_kode', 100);
            $table->string('barang_nama', 255);
            $table->unsignedInteger('qty')->default(1);
            $table->unsignedInteger('unit_price')->default(0);
            $table->unsignedInteger('line_total')->default(0);
            $table->unsignedInteger('harga_beli')->default(0);
            $table->unsignedInteger('satuan_id')->default(0);
            $table->unsignedInteger('konversi_isi')->default(1);
        });

        Schema::create('stock_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('barang_id');
            $table->unsignedInteger('cabang_id');
            $table->unsignedInteger('qty_pcs')->default(0);
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20)->default('bri');
            $table->string('cust_code', 20)->nullable();
            $table->string('virtual_account', 30)->nullable();
            $table->unsignedInteger('amount')->default(0);
            $table->string('status', 30)->default('pending');
            $table->json('bri_response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('stock_holds');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'numart_customer_id',
                'price_tier',
                'phone',
                'address',
                'warung_name',
                'warung_verification_status',
            ]);
        });
    }
};
