<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
{
    $request->authenticate();

    $request->session()->regenerate();

    // 🛠️ লগইন সফল হওয়ার পর ইউজারের লাস্ট লগইন আইপি আপডেট করা
    $user = Auth::user();
    $user->update([
        'last_login_ip' => $request->ip()
    ]);

    // রোল অনুযায়ী ড্যাশবোর্ডে পাঠানো (ইউজার নাকি অ্যাডমিন)
    if ($user->role === 'admin') {
        return redirect()->intended(route('admin.dashboard')); // আপনার কাস্টম অ্যাডমিন রাউট
    }

    return redirect()->intended(route('dashboard', absolute: false));
}

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
