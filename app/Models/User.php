<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name', 
    'email', 
    'password', 
    'role', 
    'register_ip', 
    'register_device_id', 
    'last_login_ip',
    'mobile',
    'username',
    'referral_code',
    'referred_by',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function wallet()
{
    return $this->hasOne(Wallet::class);
}

public function transactions()
{
    return $this->hasMany(Transaction::class);
}

// public function referrals()
// {
//     return $this->hasMany(User::class, 'referred_by');
// }

// // অন্তত ১৫ো টাকা ডিপোজিট করা ভেরিফাইড রেফারেল সংখ্যা কাউন্ট
// // App\Models\User.php

public function referrals()
{
    // এখানে ৩য় প্যারামিটার হিসেবে 'referral_code' বলে দিতে হবে
    return $this->hasMany(User::class, 'referred_by', 'referral_code'); 
}

public function qualifiedReferralsCount()
{
    // যে সব রেফারেড ইউজার অন্তত ১৫০ টাকা ডিপোজিট করেছে এবং তা অ্যাপ্রুভড
    return $this->referrals()->whereHas('transactions', function ($query) {
        $query->where('type', 'deposit')
              ->where('status', 'approved')
              ->where('amount', '>=', 180.00);
    })->count();
}
}