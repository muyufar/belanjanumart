<?php

use App\Http\Controllers\Admin\OrderAdminController;
use App\Http\Controllers\Auth\AccountActivationController;
use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\MarketplaceLoginController;
use App\Http\Controllers\Auth\MarketplaceRegisterController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ShopController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ShopController::class, 'index'])->name('shop.index');
Route::get('/produk/{barangId}', [ShopController::class, 'show'])->name('shop.show')->whereNumber('barangId');

Route::get('/keranjang', [CartController::class, 'index'])->name('cart.index');
Route::post('/keranjang', [CartController::class, 'store'])->name('cart.store');
Route::patch('/keranjang/{barangId}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/keranjang/{barangId}', [CartController::class, 'destroy'])->name('cart.destroy');

Route::get('/checkout', [CheckoutController::class, 'create'])->name('checkout.create');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');

Route::get('/pesanan/{order}', [OrderController::class, 'show'])->name('orders.show');
Route::post('/pesanan/{order}/cek-bayar', [OrderController::class, 'checkPayment'])->name('orders.check-payment');

Route::get('/daftar', [MarketplaceRegisterController::class, 'create'])->name('register');
Route::post('/daftar', [MarketplaceRegisterController::class, 'store']);

Route::get('/aktivasi', [AccountActivationController::class, 'create'])->name('activate');
Route::post('/aktivasi', [AccountActivationController::class, 'lookup'])->name('activate.lookup');
Route::post('/aktivasi/pilih', [AccountActivationController::class, 'choose'])->name('activate.choose');
Route::get('/lupa-password', [ForgotPasswordController::class, 'create'])->name('password.forgot');
Route::post('/lupa-password', [ForgotPasswordController::class, 'send'])->name('password.forgot.send');
Route::post('/lupa-password/pilih', [ForgotPasswordController::class, 'choose'])->name('password.forgot.choose');

Route::get('/masuk', [MarketplaceLoginController::class, 'create'])->name('login');
Route::post('/masuk', [MarketplaceLoginController::class, 'store']);
Route::post('/keluar', [MarketplaceLoginController::class, 'destroy'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/ganti-password', [ChangePasswordController::class, 'create'])->name('password.change');
    Route::post('/ganti-password', [ChangePasswordController::class, 'store'])->name('password.change.store');
    Route::get('/profil', [ProfileController::class, 'show'])->name('profile.show');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::get('/pesanan', [OrderAdminController::class, 'index'])->name('admin.orders');
});
