<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use app\Http\Controllers\Api\GameController;

class WalletController extends Controller
{
    // ==========================================
    // আপনার পূর্বের লাইভ মেথডসমূহ (Unchanged)
    // ==========================================

    public function getDepositDetails()
    {
        return response()->json([
            'success' => true,
            'admin_bkash_number' => '017XXXXXXXX',
            'instructions' => 'Send money or Cashout manually, then submit form.'
        ]);
    }

    public function submitDeposit(Request $request)
    {
        // ডিপোজিট মিনিমাম লিমিট ১০০ টাকা করা হলো
        $request->validate([
            'sender_number' => 'required|numeric|digits:11',
            'amount' => 'required|numeric|min:100', 
            'admin_bkash_number' => 'required'
        ]);

        $trx = Transaction::create([
            'user_id' => auth()->id(), 
            'type' => 'deposit', 
            'amount' => $request->amount,
            'sender_number' => $request->sender_number, 
            'admin_bkash_number' => $request->admin_bkash_number, 
            'status' => 'pending'
        ]);

        return response()->json(['success' => true, 'message' => 'Deposit request submitted!', 'data' => $trx], 201);
    }

    // public function submitWithdraw(Request $request)
    // {
    //     // উইথড্র মিনিমাম লিমিট ১০০ টাকা করা হলো
    //     $request->validate([
    //         'receiver_number' => 'required|numeric|digits:11',
    //         'amount' => 'required|numeric|min:100',
    //     ]);

    //     $user = auth()->user();
    //     $wallet = $user->wallet;
    //     $withdrawAmount = (float) $request->amount;

    //     // ১. মেইন ব্যালেন্স চেক
    //     if (!$wallet || $wallet->balance < $withdrawAmount) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'মেইন ওয়ালেটে পর্যাপ্ত ব্যালেন্স নেই। বোনাস ব্যালেন্স উইথড্রযোগ্য নয়।'
    //         ], 400);
    //     }

    //     // ২. টার্নওভার (Wagering) স্ট্যাটাস চেক
    //     $turnoverStatus = $this->calculateCurrentTurnover($user->id);
        
    //     // প্লেয়ারের যদি কোনো সফল ডিপোজিটই না থাকে অথবা টার্নওভার প্রোগ্রেস যদি ১০০% (১.০) এর কম হয়
    //     if ($turnoverStatus['deposit_amount'] > 0 && $turnoverStatus['progress_ratio'] < 1.0) {
    //         $remainingWagering = max(0, $turnoverStatus['target_turnover'] - $turnoverStatus['current_wagering']);
            
    //         return response()->json([
    //             'success' => false,
    //             'message' => "আপনার টার্নওভার এখনো অসম্পূর্ণ! উইথড্র করতে আরও " . round($remainingWagering, 2) . " টাকার গেম খেলতে হবে।"
    //         ], 400);
    //     }

    //     // ৩. সব ঠিক থাকলে উইথড্র রিকোয়েস্ট প্রসেস ও ব্যালেন্স লক
    //     DB::transaction(function () use ($wallet, $withdrawAmount, $request, $user) {
    //         $wallet->decrement('balance', $withdrawAmount);

    //         Transaction::create([
    //             'user_id'         => $user->id,
    //             'type'            => 'withdraw',
    //             'amount'          => $withdrawAmount,
    //             'receiver_number' => $request->receiver_number,
    //             'status'          => 'pending',
    //         ]);
    //     });

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Withdraw request submitted successfully.'
    //     ], 201);
    // }

    public function claimDailyBonus()
    {
        $user = auth()->user();
        $qualifiedCount = $user->qualifiedReferralsCount();

        if ($qualifiedCount < 10) {
            return response()->json(['success' => false, 'message' => 'ডেইলি বোনাসের জন্য অন্তত ১০ জন রেফারেল লাগবে।'], 400);
        }

        $todayBonusClaimed = Transaction::where('user_id', $user->id)
            ->where('sender_number', 'daily_referral_bonus')
            ->whereDate('created_at', today())
            ->exists();

        if ($todayBonusClaimed) {
            return response()->json(['success' => false, 'message' => 'আজকের DAILY বোনাস অলরেডি নেওয়া হয়েছে।'], 400);
        }

        $bonusAmount = floor($qualifiedCount / 10) * 10.00; // ১০ জনের জন্য ১০ টাকা, ২০ জনের জন্য ২০ টাকা

        DB::transaction(function () use ($user, $bonusAmount) {
            $wallet = $user->wallet;
            $wallet->increment('bonus_balance', $bonusAmount);
            Transaction::create([
                'user_id' => $user->id, 
                'type' => 'bonus', 
                'amount' => $bonusAmount, 
                'sender_number' => 'daily_referral_bonus', 
                'status' => 'approved'
            ]);
        });

        return response()->json(['success' => true, 'message' => "{$bonusAmount} টাকা বোনাস ব্যালেন্সে যুক্ত হয়েছে।"]);
    }

    public function claimReferralBonus()
    {
        $user = auth()->user();
        $qualifiedCount = $user->qualifiedReferralsCount();

        // ১. বোনাস টায়ার/স্ল্যাব নির্ধারণ (মেম্বার সংখ্যা => বোনাসের পরিমাণ)
        $bonusTiers = [
            50 => 3000.00, // সর্বোচ্চ ৩০০০ টাকা
            40 => 2200.00,
            30 => 1500.00,
            20 => 1000.00,
            10 => 500.00,
        ];

        $eligibleMilestone = 0;
        $bonusAmount = 0;

        // ইউজারের বর্তমান মেম্বার সংখ্যা কোন ধাপে পড়ে তা বের করা
        foreach ($bonusTiers as $milestone => $amount) {
            if ($qualifiedCount >= $milestone) {
                $eligibleMilestone = $milestone;
                $bonusAmount = $amount;
                break; // সবচেয়ে বড় ম্যাচিং মাইলস্টোনটি পাওয়ার পর লুপ থামবে
            }
        }

        // ইউজার যদি সর্বনিম্ন ১০ জনের টার্গেটও পূরণ না করতে পারে
        if ($eligibleMilestone === 0) {
            return response()->json([
                'success' => false,
                'message' => "দুঃখিত, বোনাস ক্লেইম করতে অন্তত ১০ জন ভেরিফাইড রেফারেল লাগবে। আপনার বর্তমান ভেরিফাইড রেফারেল: {$qualifiedCount} জন।"
            ], 400);
        }

        // ২. চেক করা: এই নির্দিষ্ট মাইলস্টোনের বোনাস ইউজার আগে কখনো নিয়েছে কিনা
        $senderNumberIdentifier = "{$eligibleMilestone}_referrals_bonus";
        
        $alreadyClaimed = Transaction::where('user_id', $user->id)
            ->where('type', 'bonus')
            ->where('sender_number', $senderNumberIdentifier)
            ->exists();

        if ($alreadyClaimed) {
            return response()->json([
                'success' => false,
                'message' => "আপনি অলরেডি আপনার {$eligibleMilestone} জন রেফারেলের বোনাস ({$bonusAmount} টাকা) ক্লেইম করে ফেলেছেন!"
            ], 400);
        }

        // ৩. ডাটাবেজ ট্রানজেকশনে বোনাস ক্রেডিট করা
        DB::transaction(function () use ($user, $bonusAmount, $senderNumberIdentifier) {
            $wallet = Wallet::firstOrCreate(['user_id' => $user->id]);
            $wallet->increment('bonus_balance', $bonusAmount);

            // ট্রানজেকশন হিস্ট্রিতে রেকর্ড রাখা
            Transaction::create([
                'user_id' => $user->id,
                'type' => 'bonus',
                'amount' => $bonusAmount,
                'sender_number' => $senderNumberIdentifier,
                'status' => 'approved'
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => "অভিনন্দন! {$eligibleMilestone} জন রেফারেল পূরণের জন্য {$bonusAmount} টাকা বোনাস আপনার ওয়ালেটে সফলভাবে যোগ হয়েছে।"
        ], 200);
    }

    // ==========================================
    // নতুন যুক্ত করা পেশাদার API মেথডসমূহ
    // ==========================================

    /**
     * ওয়ালেট ব্যালেন্স এবং সারসংক্ষেপ (Main Balance & Bonus Balance)
     */
    public function getWalletBalance()
    {
        $user = auth()->user();
        $wallet = $user->wallet;

        return response()->json([
            'success' => true,
            'data' => [
                'main_balance' => (float) ($wallet->balance ?? 0.00),
                'bonus_balance' => (float) ($wallet->bonus_balance ?? 0.00),
                'total_balance' => (float) (($wallet->balance ?? 0) + ($wallet->bonus_balance ?? 0)),
                'currency' => 'BDT'
            ]
        ], 200);
    }

    /**
     * সকল ট্রানজেকশন হিস্ট্রি (ফিল্টারিং এবং পেজিনেশনসহ)
     */
    public function getTransactionHistory(Request $request)
    {
        $type = $request->query('type'); // deposit, withdraw, bonus
        $status = $request->query('status'); // pending, approved, rejected

        $query = Transaction::where('user_id', auth()->id());

        if ($type) {
            $query->where('type', $type);
        }

        if ($status) {
            $query->where('status', $status);
        }

        $transactions = $query->latest()->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $transactions
        ], 200);
    }

    /**
     * ডেইলি বোনাস ট্র্যাকিং তথ্য (আজকের ক্লেইম স্ট্যাটাস এবং এলিজিবিলিটি)
     */
    public function getDailyBonusStatus()
    {
        $user = auth()->user();
        $qualifiedCount = $user->qualifiedReferralsCount();

        $todayClaimed = Transaction::where('user_id', $user->id)
            ->where('sender_number', 'daily_referral_bonus')
            ->whereDate('created_at', today())
            ->exists();

        $eligibleAmount = floor($qualifiedCount / 10) * 10.00;

        return response()->json([
            'success' => true,
            'data' => [
                'qualified_referrals' => $qualifiedCount,
                'min_referrals_required' => 10,
                'is_eligible' => ($qualifiedCount >= 10 && !$todayClaimed),
                'already_claimed_today' => $todayClaimed,
                'estimated_daily_bonus' => $eligibleAmount
            ]
        ], 200);
    }

    /**
     * রেফারেল ওভারভিউ ও লিস্ট (কাদের রেফার করা হয়েছে)
     */
public function getReferralOverview()
{
    $user = auth()->user();

    // সরাসরি মডেলে থাকা referrals() রিলেশনশিপ ব্যবহার করে ডেটা নেওয়া হচ্ছে
   $referredUsers = $user->referrals()
    ->select('id', 'name', 'mobile', 'created_at', 'referred_by')
    ->withExists(['transactions as is_qualified' => function ($query) {
        $query->where('type', 'deposit')
              ->where('status', 'approved')
              ->where('amount', '>=', 180.00);
    }])
    ->latest()
    ->paginate(15);

    return response()->json([
        'success' => true,
        'data' => [
            'total_qualified_referrals' => $user->qualifiedReferralsCount(),
            'referred_users' => $referredUsers
        ]
    ], 200);
}

    /**
     * রেফারেল বোনাস ক্লেইম হিস্ট্রি (কোয়েস্ট/মাইলস্টোন ও ডেইলি বোনাস)
     */
    public function getBonusClaimHistory()
    {
        $history = Transaction::where('user_id', auth()->id())
            ->where('type', 'bonus')
            ->latest()
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $history
        ], 200);
    }
}