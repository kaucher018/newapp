<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GameSetting;
use App\Models\Wallet;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GameController extends Controller
{
    private $symbols = [
        'SCATTER'  => ['id' => 1, 'weight' => 10, 'multiplier' => 5], 
        'WILD'     => ['id' => 2, 'weight' => 5,   'multiplier' => 3],
        'COWBOY'   => ['id' => 3, 'weight' => 10,  'multiplier' => 2.5],
        'HAT'      => ['id' => 4, 'weight' => 15,  'multiplier' => 2],
        'GUN'      => ['id' => 5, 'weight' => 18,  'multiplier' => 1.5],
        'A'        => ['id' => 6, 'weight' => 25,  'multiplier' => 1.2],
        'K'        => ['id' => 7, 'weight' => 30,  'multiplier' => 1.0],
        'Q'        => ['id' => 8, 'weight' => 35,  'multiplier' => 0.8],
        'J'        => ['id' => 9, 'weight' => 40,  'multiplier' => 0.5],
    ];

    private $rows = 4;
    private $reelsCount = 5;

    public function getActiveGames()
    {
        return response()->json(['success' => true, 'games' => GameSetting::all()]);
    }

    public function getTurnoverStatus(Request $request)
    {
        $user = auth()->user();
        $status = $this->calculateCurrentTurnover($user->id);
        
        return response()->json([
            'success' => true,
            'data' => [
                'last_deposit_amount'   => $status['deposit_amount'],
                'target_turnover'       => $status['target_turnover'],
                'current_wagering'      => $status['current_wagering'],
                'progress_percentage'   => round($status['progress_ratio'] * 100, 2),
                'turnover_completed'    => $status['progress_ratio'] >= 1.0,
                'is_in_trap_zone'       => $status['progress_ratio'] >= 0.85 && $status['progress_ratio'] < 1.0
            ]
        ]);
    }

public function playGame(Request $request)
    {
        $isFreeSpin = filter_var($request->input('is_free_spin'), FILTER_VALIDATE_BOOLEAN);

        $request->validate([
            'game_slug'    => 'required|in:wild_west,aviator,ace_super,slots',
            'bet_amount'   => 'required|numeric|min:' . ($isFreeSpin ? 0 : 1),
            'feature_buy'  => 'nullable|boolean',
            'is_free_spin' => 'nullable|boolean'
        ]);

        $user = auth()->user();
        $betAmount = (float)$request->bet_amount;
        $gameSlug = $request->game_slug;
        $featureBuy = $request->boolean('feature_buy', false);
        $isFreeSpin = $request->boolean('is_free_spin', false);

        $effectiveBetForPayout = ($isFreeSpin && $betAmount <= 0) ? 1.0 : $betAmount;

        if ($gameSlug === 'slots' && $featureBuy && !$isFreeSpin) {
            $betAmount = $betAmount * 100;
            $effectiveBetForPayout = $betAmount;
        }

        $wallet = Wallet::where('user_id', $user->id)->first();
        $totalAvailable = $wallet ? ($wallet->balance + $wallet->bonus_balance) : 0;

        if (!$isFreeSpin && $totalAvailable < $betAmount) {
            $message = app()->getLocale() === 'bn' ? 'পর্যাপ্ত ব্যালেন্স নেই!' : 'Insufficient balance!';
            return response()->json(['success' => false, 'message' => $message], 400);
        }

        $forceLoss = false;
        $isNearMiss = false;
        $showCashbackPopup = false;
        $cashbackAmount = 0;
        $gameMessage = "Better luck next time!";
        $grid = null;
        $winAmount = 0.00;
        $isWin = false;
        $hitMultiplier = 0;
        $isBonusTrigger = false;
        $waysCount = 0;

        if ($gameSlug === 'slots') {
            
            // ১. টার্নওভার স্ট্যাটাস ও ইউজার আইডি এনালাইসিস
            $turnoverStatus = $this->calculateCurrentTurnover($user->id);
            $isTurnoverCompleted = $turnoverStatus['progress_ratio'] >= 1.0;
            
            $lastDepositAmount = $turnoverStatus['deposit_amount'] > 0 ? (float)$turnoverStatus['deposit_amount'] : 180.00;
            $currentWalletTotal = $wallet ? ($wallet->balance + $wallet->bonus_balance) : 0;
            $isUserIdEven = ($user->id % 2 === 0);

            // 📊 টোটাল এপ্রুভড ডিপোজিট কাউন্ট চেক
            $depositCount = Transaction::where('user_id', $user->id)
                ->where('type', 'deposit')
                ->where('status', 'approved')
                ->count();

            // ⏱️ ২৪ ঘণ্টা (প্রথম দিন) সময়সীমা চেক লজিক
            $isFirstDay = $user->created_at->diffInHours(now()) < 24;

            // =========================================================
            // 🎲 আপনার নিয়মানুযায়ী ডাইনামিক ডিপোজিট ও আইডি অ্যালগরিদম
            // =========================================================
            
            if ($isTurnoverCompleted) {
                // 🛑 টার্নওভার কমপ্লিট হওয়ার পর সরাসরি ৯৫% লস মোড
                $winChance = 5;
                if (rand(1, 100) > $winChance) {
                    $forceLoss = true;
                }
            } else {
                // 🔄 টার্নওভার বাকি থাকা অবস্থায় কন্ডিশনসমূহ:

                // ⏳ শুধুমাত্র প্রথম দিন (২৪ ঘণ্টার মধ্যে) স্পেশাল ট্র্যাপগুলো কাজ করবে
                if ($isFirstDay) {
                    
                    if ($depositCount <= 1) {
                        // 1️⃣ ১ম ডিপোজিট: জোড় আইডি ১৮০ সহ সর্বোচ্চ ২৭০ টাকা পর্যন্ত প্রফিট পুশ পাবে
                        if ($isUserIdEven) {
                            // সেফটি লক: অলরেডি ২৭০ বা তার বেশি ব্যালেন্স থাকলে আর জিতবে না
                            if (($currentWalletTotal - $betAmount) >= 270.00 || $currentWalletTotal >= 270.00) {
                                $forceLoss = true;
                            } else {
                                // ডাইনামিক উইন বুস্ট: টাকা যেন হুট করে শেষ না হয়, তাই ব্যালেন্স কম থাকলে উইন চান্স বাড়িয়ে (৭০%) ধরে রাখা হবে
                                $winChance = ($currentWalletTotal < ($lastDepositAmount * 0.6)) ? 70 : 45;
                                if (rand(1, 100) > $winChance) {
                                    $forceLoss = true;
                                }
                            }
                        } else {
                            // বেজোড় আইডি ১ম ডিপোজিটে সরাসরি হারবে (১৫% উইন চান্স)
                            $winChance = 15; 
                            if (rand(1, 100) > $winChance) {
                                $forceLoss = true;
                            }
                        }

                    } elseif ($depositCount === 2) {
                        // 2️⃣ ২য় ডিপোজিট: বেজোড় (Odd) আইডিগুলো টাকা দ্বিগুণ করবে
                        if (!$isUserIdEven) {
                            $maxProfitCeiling = $lastDepositAmount * 2.0; 

                            if (($currentWalletTotal - $betAmount) >= $maxProfitCeiling) {
                                $forceLoss = true;
                            } else {
                                $winChance = ($currentWalletTotal < ($lastDepositAmount * 0.6)) ? 75 : 55; 
                                if (rand(1, 100) > $winChance) {
                                    $forceLoss = true;
                                }
                            }
                        } else {
                            // ২য় ডিপোজিটে জোড় আইডি হলে লস ফেস করবে
                            $winChance = 5;
                            if (rand(1, 100) > $winChance) {
                                $forceLoss = true;
                            }
                        }

                    } elseif ($depositCount === 3) {
                        // 3️⃣ ৩য় ডিপোজিট: জোড় (Even) আইডিগুলো টাকা দ্বিগুণ করবে
                        if ($isUserIdEven) {
                            $maxProfitCeiling = $lastDepositAmount * 2.0; 

                            if (($currentWalletTotal - $betAmount) >= $maxProfitCeiling) {
                                $forceLoss = true;
                            } else {
                                $winChance = ($currentWalletTotal < ($lastDepositAmount * 0.6)) ? 75 : 55;
                                if (rand(1, 100) > $winChance) {
                                    $forceLoss = true;
                                }
                            }
                        } else {
                            // ৩য় ডিপোজিটে বেজোড় আইডি হলে লস ফেস করবে
                            $winChance = 5;
                            if (rand(1, 100) > $winChance) {
                                $forceLoss = true;
                            }
                        }

                    } else {
                        // ৪র্থ ডিপোজিট বা তার বেশি: সবার জন্য ৩০% উইন এবং ৭০% লস
                        $winChance = 30;
                        if (rand(1, 100) > $winChance) {
                            $forceLoss = true;
                        }
                    }

                } else {
                    // 📅 ২য় দিন থেকে (২৪ ঘণ্টা পার হলে) সব ইউজার সরাসরি ৭০% লস ও ৩০% উইন সিস্টেমে পড়বে
                    $winChance = 30;
                    if (rand(1, 100) > $winChance) {
                        $forceLoss = true;
                    }
                }
            }

            // লস হলে ৪০% ক্ষেত্রে Near Miss ফিল দেওয়া
            if ($forceLoss) {
                $isNearMiss = (rand(1, 100) <= 40);
            }

            // =========================================================
            // 🎰 গ্রিড জেনারেট ও পে-আউট সেফটি ফিল্টার
            // =========================================================
            if ($forceLoss) {
                $grid = $this->generateGrid(false, true, $isFreeSpin);
                $winAmount = 0.00;
                $isWin = false;
            } else {
                $attempts = 0;
                do {
                    $grid = $this->generateGrid($featureBuy, false, $isFreeSpin);
                    $payoutData = $this->calculatePayout($grid, $effectiveBetForPayout);
                    $winAmount = $payoutData['total_win'];
                    $attempts++;
                } while ($winAmount <= 0 && $attempts < 10);

                // উইন ট্রিপল সেফটি ক্যাপ (বেট সাইজ অনুযায়ী ডাইনামিক করা হয়েছে যেন ইমব্যালেন্স না হয়)
                $maxWinAllowedPerSpin = $effectiveBetForPayout * 5; 
                if ($winAmount > $maxWinAllowedPerSpin) {
                    $winAmount = $maxWinAllowedPerSpin;
                }

                // 🛑 কঠোর সেফটি চেক ১: ১ম ডিপোজিটে জোড় আইডির ব্যালেন্স ২৭০ এর উপরে ক্রস করতে না দেওয়া
                if ($isFirstDay && !$isTurnoverCompleted && $depositCount <= 1 && $isUserIdEven) {
                    $projectedBalance = ($currentWalletTotal - $betAmount) + $winAmount;
                    if ($projectedBalance > 270.00) {
                        $winAmount = max(0, 270.00 - ($currentWalletTotal - $betAmount));
                    }
                }

                // 🛑 কঠোর সেফটি চেক ২: ২য় ডিপোজিট (বেজোড়) ও ৩য় ডিপোজিট (জোড়) এর ব্যালেন্স ডাবল (২x) লক করা
                if ($isFirstDay && !$isTurnoverCompleted && ($depositCount === 2 || $depositCount === 3)) {
                    $isAllowedToWin = ($depositCount === 2 && !$isUserIdEven) || ($depositCount === 3 && $isUserIdEven);
                    if ($isAllowedToWin) {
                        $maxProfitCeiling = $lastDepositAmount * 2.0;
                        $projectedBalance = ($currentWalletTotal - $betAmount) + $winAmount;
                        if ($projectedBalance > $maxProfitCeiling) {
                            $winAmount = max(0, $maxProfitCeiling - ($currentWalletTotal - $betAmount));
                        }
                    }
                }

                $isWin = $winAmount > 0;
                $hitMultiplier = $isWin ? ($winAmount / $effectiveBetForPayout) : 0;
                $isBonusTrigger = $payoutData['bonus_triggered'] ?? false;
                $waysCount = $payoutData['ways_count'] ?? 5;
                
                if ($winAmount <= 0) {
                    $isWin = false;
                }
            }

            // =========================================================
            // 💰 লস ক্যাশব্যাক বোনাস লজিক
            // =========================================================
            $todayDepositCount = Transaction::where('user_id', $user->id)->where('type', 'deposit')->where('status', 'approved')->whereDate('created_at', Carbon::today())->count();
            
            if (!$isWin && $todayDepositCount >= 2 && $wallet && ($wallet->balance + $wallet->bonus_balance) <= 10) {
                $todaysLossLogsCount = Transaction::where('user_id', $user->id)
                    ->whereDate('created_at', Carbon::today())
                    ->whereIn('type', ['game_play', 'free_spin_play'])
                    ->where('amount', '<=', 0)
                    ->count();

                if ($todaysLossLogsCount >= 5) {
                    $showCashbackPopup = true;
                    $cashbackAmount = 60;
                }
            }
        }

        // =========================================================
        // 📊 ডাটাবেজ স্টেট আপডেট ও ট্রানজেকশন রাইট
        // =========================================================
        DB::transaction(function () use ($wallet, $betAmount, $winAmount, $isWin, $user, $gameSlug, $isFreeSpin, $showCashbackPopup, $cashbackAmount) {
            if (!$isFreeSpin) {
                if ($wallet->bonus_balance >= $betAmount) {
                    $wallet->decrement('bonus_balance', $betAmount);
                } else {
                    $remaining = $betAmount - $wallet->bonus_balance;
                    $wallet->bonus_balance = 0;
                    $wallet->decrement('balance', $remaining);
                }
            }

            if ($isWin && $winAmount > 0) {
                $wallet->increment('balance', $winAmount);
            }

            if ($showCashbackPopup && $cashbackAmount > 0) {
                $wallet->increment('bonus_balance', $cashbackAmount);
            }

            Transaction::create([
                'user_id'       => $user->id,
                'type'          => $isFreeSpin ? 'free_spin_play' : 'game_play',
                'amount'        => $isWin ? $winAmount : ($isFreeSpin ? 0 : -$betAmount),
                'sender_number' => $gameSlug,
                'status'        => 'approved'
            ]);
        });

        $freshWallet = $wallet->fresh();
        
        if ($isWin) {
            $gameMessage = "Congratulations! You won!";
        } elseif ($isNearMiss) {
            $gameMessage = "So close! Just a few steps away from the big pool!";
        }

        $turnoverStatus = $this->calculateCurrentTurnover($user->id);
        $remainingWagering = max(0, $turnoverStatus['target_turnover'] - $turnoverStatus['current_wagering']);
        $progressRatio = $turnoverStatus['progress_ratio'];

        $response = [
            'success'               => true,
            'is_win'                => $isWin,
            'multiplier'            => (float)$hitMultiplier,
            'win_amount'            => (float)$winAmount,
            'current_balance'       => (float)$freshWallet->balance,
            'current_bonus_balance' => (float)$freshWallet->bonus_balance,
            'message'               => $gameMessage,
            'turnover_status'       => "আপনার টার্নওভার সম্পূর্ণ করতে আরও  " . round($remainingWagering, 2) . " টাকার গেম খেলতে হবে।",
        ];

        if ($gameSlug === 'slots') {
            $response['data'] = [
                'grid'                  => $grid,
                'bet_amount'            => $betAmount,
                'win_amount'            => (float)$winAmount,
                'new_balance'           => (float)$freshWallet->balance,
                'multipliers_applied'   => (float)$hitMultiplier,
                'bonus_round_triggered' => $isBonusTrigger ?? false,
                'ways_to_win'           => $waysCount,
                'turnover_progress_ratio' => $progressRatio,
                'near_miss'             => [
                    'triggered' => $isNearMiss,
                    'fake_peak' => $isNearMiss ? rand(250, 350) : 0
                ],
                'cashback'              => [
                    'popup'  => $showCashbackPopup,
                    'amount' => $cashbackAmount,
                    'title'  => "10%-20% Loss Recovery Active!"
                ]
            ];
        }

        return response()->json($response);
    }

    public function calculateCurrentTurnover($userId)
    {
        $lastDeposit = Transaction::where('user_id', $userId)
            ->where('type', 'deposit')
            ->where('status', 'approved')
            ->latest()
            ->first();

        if (!$lastDeposit) {
            return ['deposit_amount' => 0, 'target_turnover' => 0, 'current_wagering' => 0, 'progress_ratio' => 0];
        }

        $depositAmount = (float)$lastDeposit->amount;
        $targetTurnover = $depositAmount * 2; 

        $currentWagering = Transaction::where('user_id', $userId)
            ->whereIn('type', ['game_play', 'free_spin_play'])
            ->where('created_at', '>=', $lastDeposit->created_at)
            ->where('amount', '<', 0)
            ->sum('amount');

        $currentWagering = abs($currentWagering); 

        $progressRatio = $targetTurnover > 0 ? ($currentWagering / $targetTurnover) : 0;

        return [
            'deposit_amount'   => $depositAmount,
            'target_turnover'  => $targetTurnover,
            'current_wagering' => $currentWagering,
            'progress_ratio'   => $progressRatio
        ];
    }

    private function generateGrid($featureBuy = false, $forceLowWin = false, $isFreeSpin = false, $isLowSymbolSpike = false)
    {
        $grid = [];
        for ($r = 0; $r < $this->reelsCount; $r++) {
            $reel = [];
            for ($row = 0; $row < $this->rows; $row++) {
                $reel[] = $this->getRandomSymbol($featureBuy, $forceLowWin, $isFreeSpin, $isLowSymbolSpike);
            }
            $grid[] = $reel;
        }
        return $grid;
    }

    private function getRandomSymbol($featureBuy, $forceLowWin, $isFreeSpin = false, $isLowSymbolSpike = false)
    {
        $pool = [];
        foreach ($this->symbols as $name => $data) {
            $weight = $data['weight'];

            if ($forceLowWin && ($name == 'SCATTER' || $name == 'WILD' || $name == 'COWBOY')) {
                $weight = 0;
            }

            for ($i = 0; $i < $weight; $i++) {
                $pool[] = $name;
            }
        }
        return $pool[array_rand($pool)];
    }

    private function calculatePayout($grid, $betAmount)
    {
        $totalWin = 0;
        $bonusTriggered = false;
        $scatterCount = 0;

        $symbolCounts = [];
        foreach ($this->symbols as $name => $data) {
            $symbolCounts[$name] = array_fill(0, $this->reelsCount, 0);
        }

        for ($r = 0; $r < $this->reelsCount; $r++) {
            for ($row = 0; $row < $this->rows; $row++) {
                $sym = $grid[$r][$row];
                $symbolCounts[$sym][$r]++;
                if ($sym === 'SCATTER') $scatterCount++;
            }
        }

        $waysCount = 0;
        foreach ($this->symbols as $name => $data) {
            if ($name === 'SCATTER') continue;

            $ways = 1;
            $matchCount = 0;
            for ($r = 0; $r < $this->reelsCount; $r++) {
                $count = $symbolCounts[$name][$r] + $symbolCounts['WILD'][$r];
                if ($count > 0) {
                    $ways *= $count;
                    $matchCount++;
                } else {
                    break;
                }
            }

            if ($matchCount >= 3) {
                $totalWin += $betAmount * $data['multiplier'] * $ways;
                $waysCount += $ways;
            }
        }

        if ($scatterCount >= 3) {
            $bonusTriggered = true;
            $totalWin += $betAmount * $this->symbols['SCATTER']['multiplier'] * $scatterCount;
        }

        $multipliersPool = [1, 1, 1, 1, 1.2, 1.5, 2];
        $hitMultiplier = $multipliersPool[array_rand($multipliersPool)];

        if ($totalWin > 0) {
            $totalWin = $totalWin * $hitMultiplier;
        }

        return [
            'total_win'       => $totalWin,
            'multipliers'     => $hitMultiplier,
            'bonus_triggered' => $bonusTriggered,
            'ways_count'      => $waysCount
        ];
    }

    public function submitWithdraw(Request $request)
    {
        $request->validate([
            'receiver_number' => 'required|numeric|digits:11',
            'amount' => 'required|numeric|min:100',
        ]);

        $user = auth()->user();
        $wallet = $user->wallet;
        $withdrawAmount = (float) $request->amount;

        if (!$wallet || $wallet->balance < $withdrawAmount) {
            return response()->json([
                'success' => false,
                'message' => 'মেইন ওয়ালেটে পর্যাপ্ত ব্যালেন্স নেই। বোনাস ব্যালেন্স উইথড্রযোগ্য নয়।'
            ], 400);
        }

        $turnoverStatus = $this->calculateCurrentTurnover($user->id);
        
        if ($turnoverStatus['deposit_amount'] > 0 && $turnoverStatus['progress_ratio'] < 1.0) {
            $remainingWagering = max(0, $turnoverStatus['target_turnover'] - $turnoverStatus['current_wagering']);
            
            return response()->json([
                'success' => false,
                'message' => "আপনার টার্নওভার এখনো অসম্পূর্ণ! উইথড্র করতে আরও " . round($remainingWagering, 2) . " টাকার গেম খেলতে হবে।"
            ], 400);
        }

        DB::transaction(function () use ($wallet, $withdrawAmount, $request, $user) {
            $wallet->decrement('balance', $withdrawAmount);

            Transaction::create([
                'user_id'         => $user->id,
                'type'            => 'withdraw',
                'amount'          => $withdrawAmount,
                'receiver_number' => $request->receiver_number,
                'status'          => 'pending',
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Withdraw request submitted successfully.'
        ], 201);
    }
}