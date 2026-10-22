<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>handlemyap — @yield('title', 'Dashboard')</title>
    <script src="https://cdn.tailwindcss.com/3.4.16"></script>
</head>
<body class="h-full text-slate-800">
<div class="min-h-full">
    @auth
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-6xl mx-auto px-4 h-14 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="font-semibold text-slate-900">handle<span class="text-indigo-600">my</span>ap</span>
                <span class="text-xs text-slate-400 hidden sm:inline">AI accounts payable → PayPal</span>
            </div>
            <div class="flex items-center gap-4 text-sm">
                <a href="/app" class="text-slate-600 hover:text-slate-900">Dashboard</a>
                <a href="/settings" class="text-slate-600 hover:text-slate-900">Settings</a>
                <form method="POST" action="/logout">@csrf
                    <button class="text-slate-500 hover:text-slate-900">Sign out</button>
                </form>
            </div>
        </div>
    </nav>
    @endauth

    @if (session('status'))
        <div class="max-w-6xl mx-auto px-4 mt-4">
            <div class="rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-2 text-sm">
                {{ session('status') }}
            </div>
        </div>
    @endif

    <main class="max-w-6xl mx-auto px-4 py-6">
        @yield('content')
    </main>
</div>
</body>
</html>
