<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GameSetting;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function index(Request $request)
    {
        // ১. ড্যাশবোর্ড সামারি অ্যানালিটিক্স
        $totalDeposits = Transaction::where('type', 'deposit')->where('status', 'approved')->sum('amount');
        $totalWithdraws = Transaction::where('type', 'withdraw')->where('status', 'approved')->sum('amount');
        
        // অ্যাডমিনের নিট প্রফিট (Net Profit = Total Approved Deposits - Total Approved Withdraws)
        $netProfit = $totalDeposits - $totalWithdraws;

        $pendingDeposits = Transaction::where('type', 'deposit')->where('status', 'pending')->latest()->get();
        $pendingWithdraws = Transaction::where('type', 'withdraw')->where('status', 'pending')->latest()->get();
        
        // ২. ট্রানজেকশন ফিল্টারিং ও সার্চিং (Frontend/Backend Pagination)
        $search = $request->input('search');
        $transactions = Transaction::with('user')
            ->when($search, function ($query, $search) {
                return $query->where('sender_number', 'like', "%{$search}%")
                    ->orWhere('trx_id', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate(10);

        $gameSettings = GameSetting::all();

        return view('admin.dashboard', compact(
            'totalDeposits', 
            'totalWithdraws', 
            'netProfit', 
            'pendingDeposits', 
            'pendingWithdraws', 
            'transactions', 
            'gameSettings'
        ));
    }

    public function updateGameAlgorithm(Request $request, $id)
    {
        $request->validate([
            'algorithm_mode' => 'required|in:promo,normal,admin_profit',
            'normal_admin_percent' => 'required|numeric|min:0|max:100',
            'normal_user_percent' => 'required|numeric|min:0|max:100',
        ]);

        $game = GameSetting::findOrFail($id);
        $game->update($request->only(['algorithm_mode', 'normal_admin_percent', 'normal_user_percent']));

        return redirect()->back()->with('success', 'Game algorithm updated successfully!');
    }

    public function approveTransaction($id, $status)
    {
        $transaction = Transaction::findOrFail($id);
        if ($transaction->status !== 'pending') {
            return redirect()->back()->with('error', 'Transaction already processed.');
        }

        DB::transaction(function () use ($transaction, $status) {
            if ($status === 'approved') {
                $transaction->status = 'approved';
                $transaction->save();

                if ($transaction->type === 'deposit') {
                    $wallet = Wallet::firstOrCreate(['user_id' => $transaction->user_id]);
                    $wallet->increment('balance', $transaction->amount);
                }
            } else {
                $transaction->status = 'rejected';
                $transaction->save();
                
                if ($transaction->type === 'withdraw') {
                    $wallet = Wallet::where('user_id', $transaction->user_id)->first();
                    if ($wallet) {
                        $wallet->increment('balance', $transaction->amount);
                    }
                }
            }
        });

        return redirect()->back()->with('success', 'Transaction updated to ' . $status);
    }

    public function claimReferralBonus()
    {
        $user = auth()->user();
        
        $qualifiedCount = $user->qualifiedReferralsCount();
        if ($qualifiedCount < 2) {
            return response()->json([
                'success' => false,
                'message' => "দুঃখিত, বোনাস ক্লেইম করতে অন্তত ২ জন ভেরিফাইড রেফারেল লাগবে। আপনার বর্তমান ভেরিফাইড রেফারেল: {$qualifiedCount} জন।"
            ], 400);
        }

        $alreadyClaimed = Transaction::where('user_id', $user->id)
            ->where('type', 'bonus')
            ->where('sender_number', '10_referrals_bonus')
            ->exists();

        if ($alreadyClaimed) {
            return response()->json([
                'success' => false,
                'message' => 'আপনি অলরেডি আপনার রেফারেল বোনাস ৫০০ টাকা ক্লেইম করে ফেলেছেন!'
            ], 400);
        }

        DB::transaction(function () use ($user) {
            $wallet = Wallet::firstOrCreate(['user_id' => $user->id]);
            $wallet->increment('bonus_balance', 500.00);

            Transaction::create([
                'user_id' => $user->id,
                'type' => 'bonus',
                'amount' => 500.00,
                'sender_number' => '10_referrals_bonus',
                'status' => 'approved'
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'অভিনন্দন! ৫০০ টাকা রেফারেল বোনাস আপনার বোনাস ওয়ালেটে সফলভাবে যোগ হয়েছে।'
        ], 200);
    }
}