<?php

use App\Http\Controllers\Admin\AffiliateCommissionAdminController;
use App\Http\Controllers\Admin\AffiliateKaderAdminController;
use App\Http\Controllers\Admin\MemberVerificationAdminController;
use App\Http\Controllers\Admin\OrderAdminController;
use App\Http\Controllers\Affiliate\AffiliateDashboardController;
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
    Route::post('/checkout/ongkir', [CheckoutController::class, 'requestShipping'])->middleware('throttle:10,1')->name('checkout.shipping');
    Route::post('/checkout/ongkir/{quote}/batal', [CheckoutController::class, 'cancelShipping'])->whereNumber('quote')->name('checkout.shipping.cancel');

    Route::get('/pesanan/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('/pesanan/{order}/bukti-transfer', [OrderController::class, 'uploadProof'])->name('orders.upload-proof');

    Route::get('/profil', [ProfileController::class, 'show'])->name('profile.show');

    Route::middleware('affiliate_kader')->prefix('mitra-kader')->name('affiliate.')->group(function () {
        Route::get('/', [AffiliateDashboardController::class, 'index'])->name('dashboard');
        Route::get('/mitra', [AffiliateDashboardController::class, 'referrals'])->name('referrals');
        Route::get('/mitra/daftar', [AffiliateDashboardController::class, 'createReferral'])->name('referrals.create');
        Route::post('/mitra/daftar', [AffiliateDashboardController::class, 'storeReferral'])->name('referrals.store');
        Route::get('/belanja', [AffiliateDashboardController::class, 'orders'])->name('orders');
    });
});

Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::get('/pesanan', [OrderAdminController::class, 'index'])->name('admin.orders');
    Route::get('/verifikasi-member', [MemberVerificationAdminController::class, 'index'])->name('admin.members.verify');
    Route::post('/verifikasi-member/{user}/setujui', [MemberVerificationAdminController::class, 'approve'])->name('admin.members.approve');
    Route::post('/verifikasi-member/{user}/tolak', [MemberVerificationAdminController::class, 'reject'])->name('admin.members.reject');

    Route::get('/affiliate/komisi', [AffiliateCommissionAdminController::class, 'commissions'])->name('admin.affiliate.commissions');
    Route::post('/affiliate/komisi/{commission}/bayar', [AffiliateCommissionAdminController::class, 'markPaid'])->name('admin.affiliate.commissions.pay');
    Route::get('/affiliate/pengaturan', [AffiliateCommissionAdminController::class, 'settings'])->name('admin.affiliate.settings');
    Route::post('/affiliate/pengaturan', [AffiliateCommissionAdminController::class, 'updateSettings'])->name('admin.affiliate.settings.update');
});

Route::middleware(['auth', 'super_admin'])->prefix('admin')->group(function () {
    Route::get('/affiliate/kader', [AffiliateKaderAdminController::class, 'index'])->name('admin.affiliate.kaders');
    Route::post('/affiliate/kader', [AffiliateKaderAdminController::class, 'store'])->name('admin.affiliate.kaders.store');
    Route::post('/affiliate/kader/{user}/toggle', [AffiliateKaderAdminController::class, 'toggleActive'])->name('admin.affiliate.kaders.toggle');
});
