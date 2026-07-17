<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\OrderTrackingController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('catalog.index'))->name('home');
Route::get('/dashboard', fn () => redirect()->route('catalog.index'))->name('dashboard');
Route::get('/menu', [ProductController::class, 'index'])->name('catalog.index');
Route::get('/produk/{product}', [ProductController::class, 'show'])->name('catalog.show');
Route::get('/keranjang', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/add', [CartController::class, 'store'])->name('cart.store');
Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
Route::get('/checkout/sukses/{order}', [CheckoutController::class, 'success'])->name('checkout.success');

// Static pages
Route::view('/kebijakan-cookie', 'pages.kebijakan-cookie')->name('pages.cookie');
Route::view('/kebijakan-privasi', 'pages.kebijakan-privasi')->name('pages.privacy');
Route::view('/syarat-ketentuan', 'pages.syarat-ketentuan')->name('pages.terms');

// Order tracking (public — guest & registered)
Route::get('/cek-status', [OrderTrackingController::class, 'show'])
    ->middleware('throttle:5,1')
    ->name('orders.track');
Route::get('/cek-status/cari', [OrderTrackingController::class, 'lookupSigned'])
    ->middleware('signed')
    ->name('orders.track.lookup');
Route::post('/cek-status', [OrderTrackingController::class, 'lookup'])
    ->middleware('throttle:5,1')
    ->name('orders.lookup');
Route::post('/orders/{order}/cancel', [OrderTrackingController::class, 'cancel'])
    ->middleware('throttle:5,1')
    ->name('orders.cancel');

// Auth (guest)
Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

// Auth (authenticated)
Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)->name('verification.notice');
    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:3,1')
        ->name('verification.send');
    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])->name('password.confirm');
    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);
    Route::put('password', [PasswordController::class, 'update'])->name('password.update');
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});
