@props(['title' => '', 'subtitle' => null])
@php
    $u = auth()->user();
    $nav = match (true) {
        $u->hasRole('admin') => ['Registry' => ['admin.dashboard' => 'Overview', 'admin.credentials' => 'Credentials', 'admin.import' => 'Bulk issue', 'admin.students' => 'Students'], 'Administration' => ['admin.issuers' => 'Issuers', 'admin.keys' => 'Verification keys', 'admin.users' => 'Users', 'admin.requests' => 'Request log', 'admin.audit' => 'Audit log']],
        $u->hasRole('issuer') => ['Registry' => ['admin.dashboard' => 'Overview', 'admin.credentials' => 'Credentials', 'admin.import' => 'Bulk issue', 'admin.students' => 'Students']],
        $u->hasRole('student') => ['My credentials' => ['student.dashboard' => 'Dashboard', 'student.wallet.index' => 'Wallet', 'student.tokens.index' => 'Verification tokens', 'student.requests.index' => 'Consent requests']],
        $u->hasRole('employer') => ['Verification' => ['employer.dashboard' => 'Dashboard', 'employer.verify.index' => 'Verify a token', 'employer.bulk.index' => 'Bulk verification', 'employer.requests.create' => 'New request']],
        default => ['Tasks' => ['external.dashboard' => 'My tasks']],
    };
    $active = fn ($r) => request()->routeIs($r) || request()->routeIs(preg_replace('/\.(dashboard|index)$/', '', $r).'.*') && ! in_array($r, ['admin.dashboard', 'employer.dashboard', 'student.dashboard'], true);
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}AcademiQ</title>
    <link rel="icon" href="{{ asset('images/logo-placeholder.svg') }}">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Source+Serif+4:wght@600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen" x-data="{ nav: false }">
<div class="flex min-h-screen">
    <aside class="fixed inset-y-0 left-0 z-30 flex w-64 -translate-x-full flex-col bg-navy text-slate-300 transition lg:static lg:translate-x-0" :class="nav && '!translate-x-0'">
        <a href="{{ route('home') }}" class="flex items-center gap-3 border-b border-white/10 px-5 py-5">
            <img src="{{ asset('images/logo-placeholder.svg') }}" alt="" class="h-9 w-9">
            <span><span class="block font-display text-lg font-bold leading-none text-white">AcademiQ</span><span class="mt-1 block text-[11px] leading-none text-slate-400">KUET Credential Services</span></span>
        </a>
        <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-5" aria-label="Dashboard">
            @foreach($nav as $group => $items)
            <div>
                <p class="px-3 pb-2 text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500">{{ $group }}</p>
                @foreach($items as $r => $l)
                <a href="{{ route($r) }}" @if($active($r)) aria-current="page" @endif class="block rounded-md border-l-2 px-3 py-2 text-sm font-medium transition hover:bg-white/5 hover:text-white {{ $active($r) ? 'border-teal bg-white/10 text-white' : 'border-transparent' }}">{{ $l }}</a>
                @endforeach
            </div>
            @endforeach
        </nav>
        <div class="border-t border-white/10 px-5 py-4 text-xs text-slate-400">
            <p class="font-medium text-slate-200">{{ $u->name }}</p>
            <p>{{ $u->roleEnum()->label() }}</p>
        </div>
    </aside>
    <div class="flex min-w-0 flex-1 flex-col">
        <header data-app class="flex items-center justify-between border-b border-slate-soft bg-white px-4 py-3 sm:px-8">
            <button class="rounded-md p-2 lg:hidden" @click="nav = !nav" aria-label="Toggle navigation"><svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>
            <p class="hidden text-sm text-muted lg:block">Khulna University of Engineering &amp; Technology</p>
            <div class="flex items-center gap-3">
                @php($unread = \App\Models\UserNotification::where('user_id', (string) $u->getKey())->whereNull('read_at')->count())
                <a href="{{ route('notifications') }}" class="relative rounded-md p-2 hover:bg-slate-soft" aria-label="Notifications{{ $unread ? ', '.$unread.' unread' : '' }}">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10 21a2 2 0 0 0 4 0"/></svg>
                    @if($unread)<span class="absolute -right-0.5 -top-0.5 grid h-4 min-w-4 place-items-center rounded-full bg-bad px-1 text-[10px] font-bold text-white">{{ $unread > 9 ? '9+' : $unread }}</span>@endif
                </a>
                <span class="hidden text-sm font-medium sm:inline">{{ $u->name }}</span>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn-ghost !py-1.5">Sign out</button></form>
            </div>
        </header>
        <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8 sm:px-8">
            @if(session('status'))<div role="status" class="mb-5 rounded-md border border-ok/30 bg-ok/10 p-3 text-sm text-ok">{{ session('status') }}</div>@endif
            <div class="mb-8 border-b border-slate-soft pb-5">
                <h1 class="text-2xl sm:text-3xl">{{ $title }}</h1>
                @if($subtitle)<p class="mt-1.5 max-w-2xl text-sm text-muted">{{ $subtitle }}</p>@endif
            </div>
            {{ $slot }}
        </main>
    </div>
</div>
</body>
</html>
