<?php

use App\Http\Controllers\AuthController; // অথবা আপনার কাস্টম কন্ট্রোলার নাম
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\GameController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// 📱 পাবলিক এপিআই রাউটস (মোবাইল অ্যাপের লগইন ও রেজিস্ট্রেশন)
Route::post('/v1/register', [AuthController::class, 'apiRegister']);
Route::post('/v1/login', [AuthController::class, 'apiLogin']);

// 🔒 প্রোটেক্টেಡ್ এপিআই রাউটস (টোকেন ছাড়া এক্সেস করা যাবে না)
Route::middleware('auth:sanctum')->group(function () {
    
    // ইউজারের নিজের প্রোফাইল ডাটা দেখা
    Route::get('/user', function (Request $request) {
        return response()->json([
            'status' => true,
            'user' => $request->user()
        ]);
    });

    // মোবাইল অ্যাপ থেকে লগআউট
    Route::post('/v1/logout', [AuthController::class, 'apiLogout']);
});



Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    // ওয়ালেট ও ট্রানজেকশন
    Route::get('/adj/details', [WalletController::class, 'getDepositDetails']);
    Route::post('/execute-add', [WalletController::class, 'submitDeposit']);
    Route::post('/execute-sub', [GameController::class, 'submitWithdraw']);
    Route::post('/referral/daily-bonus', [WalletController::class, 'claimDailyBonus']);
    Route::post('/referral/claim-bonus', [WalletController::class, 'claimReferralBonus']); 

    // ওয়ালেট ব্যালেন্স চেক
    Route::get('/wallet/balance', [WalletController::class, 'getWalletBalance']);

    // ট্রানজেকশন হিস্ট্রি (ফিল্টারিং সহ: ?type=deposit/withdraw/bonus & status=pending/approved)
    Route::get('/wallet/transactions', [WalletController::class, 'getTransactionHistory']);

    // ডেইলি বোনাস ট্র্যাকিং ও স্ট্যাটাস
    Route::get('/referral/daily-bonus-status', [WalletController::class, 'getDailyBonusStatus']);

    // ইউজার কাকে কাকে রেফার করেছে (রেফারেল ইউজার লিস্ট)
    Route::get('/referral/my-referrals', [WalletController::class, 'getReferralOverview']);

    // বোনাস ক্লেইমের সমস্ত হিস্ট্রি (Daily + Milestone)
    Route::get('/referral/bonus-history', [WalletController::class, 'getBonusClaimHistory']);


    

    // গেম এপিআই
    Route::get('/names', [GameController::class, 'getActiveGames']);
    Route::post('/event-process', [GameController::class, 'playGame']);


     Route::get('/profile', [AuthController::class, 'apiProfile']); // 👈 Profile API
    Route::post('/logout', [AuthController::class, 'apiLogout']);

    Route::post('/rote', [GameController::class, 'spinWheel']);

    Route::get('/calculateCurrentTurnover', [GameController::class, 'calculateCurrentTurnover']);
});



