<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700;900&family=Rajdhani:wght@500;600;700&display=swap');
        
        body {
            font-family: 'Rajdhani', sans-serif;
            background-color: #0d0f17;
            color: #e2e8f0;
        }
        .font-orbitron { font-family: 'Orbitron', sans-serif; }
        .neon-card { background: linear-gradient(135deg, #161926 0%, #0d0f17 100%); border: 1px solid #2d3748; }
        .neon-card:hover { border-color: #ef4444; }
    </style>
</head>
<body class="bg-[#0d0f17] antialiased">

<div class="flex h-screen overflow-hidden">

    <!-- ==================== SIDEBAR ==================== -->
    <aside class="w-64 bg-[#121520] border-r border-gray-800 flex flex-col justify-between hidden md:flex">
        <div>
            <!-- Logo Section -->
            <div class="h-20 flex items-center px-6 border-b border-gray-800">
                <i class="fa-solid fa-shield-halved font-orbitron text-2xl text-red-500 mr-3"></i>
                <span class="font-orbitron text-xl font-bold tracking-wider text-white">ADMIN<span class="text-red-500">PRO</span></span>
            </div>

            <!-- Navigation Links -->
            <nav class="mt-6 px-4 space-y-2">
                <a href="#overview" class="flex items-center px-4 py-3 text-red-500 bg-red-500/10 rounded-lg border-l-4 border-red-500 font-semibold transition">
                    <i class="fa-solid fa-chart-line w-6"></i>
                    <span>Dashboard Overview</span>
                </a>
                <a href="#pending-trans" class="flex items-center px-4 py-3 text-gray-400 hover:text-white hover:bg-gray-800/50 rounded-lg font-semibold transition">
                    <i class="fa-solid fa-clock-rotate-left w-6"></i>
                    <span>Pending Requests</span>
                </a>
                <a href="#transactions" class="flex items-center px-4 py-3 text-gray-400 hover:text-white hover:bg-gray-800/50 rounded-lg font-semibold transition">
                    <i class="fa-solid fa-receipt w-6"></i>
                    <span>Transaction Logs</span>
                </a>
            </nav>
        </div>

        <!-- Sidebar Footer / Logout -->
        <div class="p-4 border-t border-gray-800">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full flex items-center justify-center gap-2 py-2.5 px-4 bg-red-600/20 text-red-500 border border-red-600/30 rounded-lg hover:bg-red-600 hover:text-white font-bold transition duration-200">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    <span>LOGOUT</span>
                </button>
            </form>
        </div>
    </aside>

    <!-- ==================== MAIN CONTENT ==================== -->
    <div class="flex-1 flex flex-col overflow-y-auto">

        <!-- Top Navigation Bar -->
        <header class="h-20 bg-[#121520]/80 backdrop-blur-md border-b border-gray-800 px-8 flex items-center justify-between sticky top-0 z-50">
            <div class="flex items-center gap-4">
                <h1 class="text-xl font-orbitron font-bold text-white tracking-wide">ADMIN COMMAND CENTER</h1>
            </div>

            <!-- Admin Profit Display Header -->
            <div class="flex items-center gap-6">
                <div class="bg-gradient-to-r from-emerald-950/60 to-emerald-900/30 border border-emerald-500/40 px-5 py-2 rounded-xl flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-emerald-500/20 flex items-center justify-center text-emerald-400">
                        <i class="fa-solid fa-sack-dollar text-xl"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wider">Net Revenue Balance</p>
                        <p class="text-lg font-orbitron font-bold text-emerald-400">৳{{ number_format($netProfit ?? 0, 2) }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-3 border-l border-gray-800 pl-6">
                    <div class="w-10 h-10 rounded-full bg-red-600/20 border border-red-500/50 flex items-center justify-center font-bold text-red-500">
                        {{ strtoupper(substr(Auth::user()->name ?? 'A', 0, 1)) }}
                    </div>
                    <div>
                        <p class="text-sm font-bold text-white">{{ Auth::user()->name ?? 'Administrator' }}</p>
                        <p class="text-xs text-emerald-500 flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span> Active</p>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Body Content -->
        <main class="p-8 space-y-8">

            <!-- Flash Session Alerts -->
            @if(session('success'))
                <div class="bg-emerald-900/40 border border-emerald-500 text-emerald-300 px-4 py-3 rounded-xl flex items-center justify-between">
                    <span><i class="fa-solid fa-circle-check mr-2"></i> {{ session('success') }}</span>
                    <button onclick="this.parentElement.remove()"><i class="fa-solid fa-xmark"></i></button>
                </div>
            @endif
            @if(session('error'))
                <div class="bg-red-900/40 border border-red-500 text-red-300 px-4 py-3 rounded-xl flex items-center justify-between">
                    <span><i class="fa-solid fa-triangle-exclamation mr-2"></i> {{ session('error') }}</span>
                    <button onclick="this.parentElement.remove()"><i class="fa-solid fa-xmark"></i></button>
                </div>
            @endif

            <!-- OVERVIEW STAT CARDS -->
            <section id="overview" class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Total Deposits -->
                <div class="neon-card rounded-2xl p-6 relative overflow-hidden">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-gray-400 text-sm font-semibold uppercase">Total Approved Deposits</p>
                            <h3 class="text-3xl font-orbitron font-bold text-white mt-2">৳{{ number_format($totalDeposits ?? 0, 2) }}</h3>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-blue-500/10 border border-blue-500/30 flex items-center justify-center text-blue-400">
                            <i class="fa-solid fa-wallet text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4 border-t border-gray-800 pt-3 text-xs text-gray-500">Overall deposit volume across system</div>
                </div>

                <!-- Total Withdrawals -->
                <div class="neon-card rounded-2xl p-6 relative overflow-hidden">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-gray-400 text-sm font-semibold uppercase">Total Approved Withdraws</p>
                            <h3 class="text-3xl font-orbitron font-bold text-white mt-2">৳{{ number_format($totalWithdraws ?? 0, 2) }}</h3>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-purple-500/10 border border-purple-500/30 flex items-center justify-center text-purple-400">
                            <i class="fa-solid fa-money-bill-transfer text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4 border-t border-gray-800 pt-3 text-xs text-gray-500">Payouts completed to user accounts</div>
                </div>

                <!-- Net Profit Margin -->
                <div class="neon-card rounded-2xl p-6 relative overflow-hidden border-emerald-500/30">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-gray-400 text-sm font-semibold uppercase">Platform Net Revenue</p>
                            <h3 class="text-3xl font-orbitron font-bold text-emerald-400 mt-2">৳{{ number_format($netProfit ?? 0, 2) }}</h3>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-emerald-400">
                            <i class="fa-solid fa-chart-pie text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4 border-t border-gray-800 pt-3 text-xs text-gray-500">Net platform revenue balance</div>
                </div>
            </section>

            <!-- PENDING TRANSACTIONS (DEPOSITS & WITHDRAWS) -->
            <section id="pending-trans" class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Pending Deposits -->
                <div class="neon-card rounded-2xl p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-lg font-orbitron font-bold text-white flex items-center gap-2">
                            <i class="fa-solid fa-arrow-down-left text-blue-400"></i> Pending Deposits
                        </h2>
                        <span class="bg-blue-500/20 text-blue-400 text-xs px-3 py-1 rounded-full border border-blue-500/30 font-bold">
                            {{ count($pendingDeposits ?? []) }} Waiting
                        </span>
                    </div>

                    <div class="space-y-4 max-h-[350px] overflow-y-auto pr-2">
                        @forelse($pendingDeposits as $deposit)
                            <div class="bg-[#121520] p-4 rounded-xl border border-gray-800 flex items-center justify-between">
                                <div>
                                    <p class="font-bold text-white">৳{{ number_format($deposit->amount, 2) }}</p>
                                    <p class="text-xs text-gray-400">User ID: #{{ $deposit->user_id }} | Sender: {{ $deposit->sender_number ?? 'N/A' }}</p>
                                    <p class="text-[10px] text-gray-500 mt-1">{{ $deposit->created_at->diffForHumans() }}</p>
                                </div>
                                <div class="flex gap-2">
                                    <form method="POST" action="{{ route('admin.transaction.status', ['id' => $deposit->id, 'status' => 'approved']) }}">
                                        @csrf
                                        <button type="submit" class="bg-emerald-600/20 hover:bg-emerald-600 text-emerald-400 hover:text-white border border-emerald-500/40 px-3 py-1.5 rounded-lg text-xs font-bold transition">Approve</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.transaction.status', ['id' => $deposit->id, 'status' => 'rejected']) }}">
                                        @csrf
                                        <button type="submit" class="bg-red-600/20 hover:bg-red-600 text-red-400 hover:text-white border border-red-500/40 px-3 py-1.5 rounded-lg text-xs font-bold transition">Reject</button>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-8 text-gray-500 text-sm">No pending deposits currently</div>
                        @endforelse
                    </div>
                </div>

                <!-- Pending Withdraws -->
                <div class="neon-card rounded-2xl p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-lg font-orbitron font-bold text-white flex items-center gap-2">
                            <i class="fa-solid fa-arrow-up-right text-purple-400"></i> Pending Withdrawals
                        </h2>
                        <span class="bg-purple-500/20 text-purple-400 text-xs px-3 py-1 rounded-full border border-purple-500/30 font-bold">
                            {{ count($pendingWithdraws ?? []) }} Waiting
                        </span>
                    </div>

                    <div class="space-y-4 max-h-[350px] overflow-y-auto pr-2">
                        @forelse($pendingWithdraws as $withdraw)
                            <div class="bg-[#121520] p-4 rounded-xl border border-gray-800 flex items-center justify-between">
                                <div>
                                    <p class="font-bold text-white">৳{{ number_format($withdraw->amount, 2) }}</p>
                                    <p class="text-xs text-gray-400">User ID: #{{ $withdraw->user_id }} | Number: {{ $withdraw->sender_number ?? 'N/A' }}</p>
                                    <p class="text-[10px] text-gray-500 mt-1">{{ $withdraw->created_at->diffForHumans() }}</p>
                                </div>
                                <div class="flex gap-2">
                                    <form method="POST" action="{{ route('admin.transaction.status', ['id' => $withdraw->id, 'status' => 'approved']) }}">
                                        @csrf
                                        <button type="submit" class="bg-emerald-600/20 hover:bg-emerald-600 text-emerald-400 hover:text-white border border-emerald-500/40 px-3 py-1.5 rounded-lg text-xs font-bold transition">Approve</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.transaction.status', ['id' => $withdraw->id, 'status' => 'rejected']) }}">
                                        @csrf
                                        <button type="submit" class="bg-red-600/20 hover:bg-red-600 text-red-400 hover:text-white border border-red-500/40 px-3 py-1.5 rounded-lg text-xs font-bold transition">Reject</button>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-8 text-gray-500 text-sm">No pending withdrawals currently</div>
                        @endforelse
                    </div>
                </div>
            </section>

<!-- TRANSACTION LOGS & SEARCH -->
<section id="transactions" class="neon-card rounded-2xl p-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <h2 class="text-lg font-orbitron font-bold text-white flex items-center gap-2">
            <i class="fa-solid fa-list-check text-emerald-400"></i> System Transaction Logs
        </h2>

        <!-- Search Form -->
        <form method="GET" action="{{ route('admin.dashboard') }}" class="flex items-center gap-2">
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm"></i>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search ID, Type, Status..." class="bg-[#121520] border border-gray-700 text-white text-sm rounded-lg pl-9 pr-4 py-2 focus:outline-none focus:border-red-500 w-64">
            </div>
            <button type="submit" class="bg-gray-800 hover:bg-gray-700 text-white text-xs px-4 py-2.5 rounded-lg font-bold transition">Search</button>
            @if(request('search'))
                <a href="{{ route('admin.dashboard') }}" class="text-xs text-gray-400 hover:text-white underline">Clear</a>
            @endif
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-[#121520] text-gray-400 uppercase text-xs">
                <tr>
                    <th class="py-3 px-4">ID</th>
                    <th class="py-3 px-4">User</th>
                    <th class="py-3 px-4">Type</th>
                    <th class="py-3 px-4">Amount</th>
                    <th class="py-3 px-4">Sender/Reference</th>
                    <th class="py-3 px-4">Status</th>
                    <th class="py-3 px-4">Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-800/60 text-gray-300">
                @forelse($transactions as $trx)
                    <tr class="hover:bg-gray-800/30">
                        <td class="py-3 px-4 font-mono text-xs">#{{ $trx->id }}</td>
                        <td class="py-3 px-4 font-bold text-white">{{ $trx->user->name ?? 'User #'.$trx->user_id }}</td>
                        <td class="py-3 px-4">
                            <span class="uppercase text-[10px] px-2 py-0.5 rounded font-bold 
                                {{ $trx->type === 'deposit' ? 'bg-blue-500/20 text-blue-400' : '' }}
                                {{ $trx->type === 'withdraw' ? 'bg-purple-500/20 text-purple-400' : '' }}">
                                {{ $trx->type }}
                            </span>
                        </td>
                        <td class="py-3 px-4 font-bold">৳{{ number_format($trx->amount, 2) }}</td>
                        <td class="py-3 px-4 text-xs font-mono text-gray-400">{{ $trx->sender_number ?? 'N/A' }}</td>
                        <td class="py-3 px-4">
                            <span class="capitalize text-xs font-bold 
                                {{ $trx->status === 'approved' ? 'text-emerald-400' : '' }}
                                {{ $trx->status === 'pending' ? 'text-amber-400' : '' }}
                                {{ $trx->status === 'rejected' ? 'text-red-400' : '' }}">
                                {{ $trx->status }}
                            </span>
                        </td>
                        <td class="py-3 px-4 text-xs text-gray-500">{{ $trx->created_at ? $trx->created_at->format('M d, Y h:i A') : 'N/A' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-6 text-center text-gray-500">No deposit or withdraw transactions found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination Links -->
    @if(isset($transactions) && method_exists($transactions, 'links'))
        <div class="mt-6">
            {{ $transactions->links() }}
        </div>
    @endif
</section>

        </main>
    </div>
</div>

</body>
</html>