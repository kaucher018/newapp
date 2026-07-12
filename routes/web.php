<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\GameController;
// অ্যাডমিন ড্যাশবোর্ড কন্ট্রোলারটি ইমপোর্ট করুন
use App\Http\Controllers\Admin\AdminDashboardController; 

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// ১. ল্যান্ডিং পেজ
Route::get('/', function () {
    return view('welcome');
});

// ২. সাধারণ প্লেয়ার/ইউজার ড্যাশবোর্ড (Breeze-এর ডিফল্ট)
Route::get('/dashboard', function () {
    if (Auth::user()->role === 'admin') {
        return redirect()->route('admin.dashboard');
    }
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// ৩. 🔒 ক্যাসিনো অ্যাডমিন প্যানেল রাউট (Name Prefix সহ ফিক্স করা হয়েছে)
// ৩. 🔒 ক্যাসিনো অ্যাডমিন প্যানেল রাউট
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    
    // ম্যানুয়াল অ্যানোনিমাস ফাংশনের বদলে সরাসরি কন্ট্রোলার ক্লাস ও মেথড পাস করুন
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    
    // গেম অ্যালগরিদম আপডেট রাউট
    Route::post('/game-settings/{id}/update', [AdminDashboardController::class, 'updateGameAlgorithm'])->name('game.update');
    
    // ট্রানজেকশন অ্যাপ্রুভ/রিজেক্ট রাউট
    Route::post('/transactions/{id}/{status}', [AdminDashboardController::class, 'approveTransaction'])->name('transaction.status');
});

// ৪. ইউজার প্রোফাইল ম্যানেজমেন্ট (Breeze-এর ডিফল্ট)
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// এপিআই রাউটস
Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    // ওয়ালেট ও ট্রানজেকশন
    Route::get('/deposit/details', [WalletController::class, 'getDepositDetails']);
    Route::post('/deposit/submit', [WalletController::class, 'submitDeposit']);
    Route::post('/withdraw/submit', [WalletController::class, 'submitWithdraw']);
    Route::post('/getWalletBalance', [WalletController::class, 'getWalletBalance']);
    Route::post('/referral/daily-bonus', [WalletController::class, 'claimDailyBonus']);

    // গেম এপিআই
    Route::get('/games', [GameController::class, 'getActiveGames']);
    Route::post('/game/play', [GameController::class, 'playGame']);
});

Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    // আগের ওয়ালেট রাউটস...
    Route::post('/referral/claim-bonus', [AdminDashboardController::class, 'claimReferralBonus']); 
});

// ৫. ব্রিজের অথেনটিকেশন রাউটস
require __DIR__.'/auth.php';