<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Wallet extends Model
{
    protected $fillable = ['user_id', 'balance', 'bonus_balance', 'admin_total_profit', 'admin_net_worth'];

    /**
     * অ্যাডমিন প্রফিট এবং নেট ওয়ার্থ রিয়েল-টাইম আপডেট করার সংশোধিত মেথড
     */
    public static function updateAdminProfitLedger()
    {
        // ১. ডিপোজিট এবং উইথড্র থেকে নেট ক্যাশফ্লো (ক্যাশবক্সে কত রিয়েল টাকা আছে)
        $totalApprovedDeposits = DB::table('transactions')
            ->where('type', 'deposit')
            ->where('status', 'approved')
            ->sum('amount');

        $totalApprovedWithdrawals = DB::table('transactions')
            ->where('type', 'withdraw')
            ->where('status', 'approved')
            ->sum('amount');

        $adminNetWorth = $totalApprovedDeposits - $totalApprovedWithdrawals;

        // ২. ক্যাসিনো পিওর প্রফিট হিসাব (GGR)
        // গেমপ্লেতে লস বেটগুলো নেগেটিভ (যেমন: -১০০) এবং উইনগুলো পজিটিভ (যেমন: +৫০০) হিসেবে থাকে
        $gameBalance = DB::table('transactions')
            ->whereIn('type', ['game_play', 'free_spin_play'])
            ->where('status', 'approved')
            ->sum('amount'); 
        
        // ইউজাররা যত টাকা হারবে, অ্যাডমিনের তত লাভ। 
        // আর বোনাস দিয়ে খেলে ইউজার জিতলে তা ট্রানজেকশনে প্লাস হবে, যা মাইনাস ১ দিয়ে গুণ হয়ে অটোমেটিক অ্যাডমিন প্রফিট থেকে মাইনাস হয়ে যাবে।
        $adminTotalProfit = $gameBalance * -1;

        // ৩. ইউজার আইডি ১ (অ্যাডমিন) এর ওয়ালেটে ডাটা আপডেট/তৈরি করা
        self::updateOrCreate(
            ['user_id' => 1],
            [
                'admin_total_profit' => $adminTotalProfit,
                'admin_net_worth'    => $adminNetWorth
            ]
        );
    }
}