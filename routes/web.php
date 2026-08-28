<?php

use App\Http\Controllers\Admin\MemberVerificationAdminController;
use App\Http\Controllers\Admin\OrderAdminController;
use App\Http\Controllers\Auth\MarketplaceLoginController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\MemberVerificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ShopController;
use Illuminate\Support\Facades\Route;

Route::get('/masuk', [MarketplaceLoginController::class, 'create'])->name('login');
Route::post('/masuk', [MarketplaceLoginController::class, 'store']);
Route::post('/keluar', [MarketplaceLoginController::class, 'destroy'])->name('logout');

Route::redirect('/daftar', '/masuk')->name('register');
Route::redirect('/aktivasi', '/masuk');
Route::redirect('/lupa-password', '/masuk');
Route::redirect('/ganti-password', '/masuk');

Route::middleware('auth')->group(function () {
    Route::get('/', [ShopController::class, 'index'])->name('shop.index');
    Route::get('/kategori', [ShopController::class, 'categories'])->name('shop.categories');
    Route::get('/kategori/{kategoriId}', [ShopController::class, 'category'])->name('shop.category')->whereNumber('kategoriId');
    Route::get('/produk/{barangId}', [ShopController::class, 'show'])->name('shop.show')->whereNumber('barangId');

    Route::get('/verifikasi-akun', [MemberVerificationController::class, 'create'])->name('member.verification.create');
    Route::post('/verifikasi-akun', [MemberVerificationController::class, 'store'])->name('member.verification.store');

    Route::get('/keranjang', [CartController::class, 'index'])->name('cart.index');
    Route::post('/keranjang', [CartController::class, 'store'])->name('cart.store');
    Route::patch('/keranjang/{barangId}', [CartController::class, 'update'])->name('cart.update');
    Route::delete('/keranjang/{barangId}', [CartController::class, 'destroy'])->name('cart.destroy');

    Route::get('/checkout', [CheckoutController::class, 'create'])->name('checkout.create');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');

    Route::get('/pesanan/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('/pesanan/{order}/bukti-transfer', [OrderController::class, 'uploadProof'])->name('orders.upload-proof');

    Route::get('/profil', [ProfileController::class, 'show'])->name('profile.show');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::get('/pesanan', [OrderAdminController::class, 'index'])->name('admin.orders');
    Route::get('/verifikasi-member', [MemberVerificationAdminController::class, 'index'])->name('admin.members.verify');
    Route::post('/verifikasi-member/{user}/setujui', [MemberVerificationAdminController::class, 'approve'])->name('admin.members.approve');
    Route::post('/verifikasi-member/{user}/tolak', [MemberVerificationAdminController::class, 'reject'])->name('admin.members.reject');
});
