@php($links = ['about' => 'About', 'how-it-works' => 'How it works', 'students' => 'For students', 'employers' => 'For employers', 'privacy' => 'Privacy & security', 'contact' => 'Contact'])
<header class="sticky top-0 z-40 border-b border-slate-soft bg-white/90 backdrop-blur" x-data="{ open: false }">
    <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3 sm:px-6">
        <a href="{{ route('home') }}" class="flex items-center gap-2.5">
            <img src="{{ asset('images/logo-placeholder.svg') }}" alt="" class="h-9 w-9">
            <span class="font-display text-lg font-extrabold text-navy">Academi<span class="text-teal">Q</span></span>
        </a>
        <nav class="hidden items-center gap-6 text-sm font-medium text-muted lg:flex" aria-label="Main">
            @foreach($links as $r => $l)
                <a href="{{ route($r) }}" class="transition hover:text-navy {{ request()->routeIs($r) ? 'text-navy' : '' }}">{{ $l }}</a>
            @endforeach
        </nav>
        <div class="hidden items-center gap-2 lg:flex">
            @auth
                <a href="{{ route('dashboard') }}" class="btn-primary !py-2">Dashboard</a>
            @else
                <a href="{{ route('login') }}" class="btn-ghost !py-2">Sign in</a>
                <a href="{{ route('register') }}" class="btn-primary !py-2">Register</a>
            @endauth
        </div>
        <button class="rounded-lg p-2 lg:hidden" @click="open = !open" :aria-expanded="open" aria-label="Toggle menu">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        </button>
    </div>
    <nav x-show="open" x-cloak x-transition class="border-t border-slate-soft px-4 py-3 lg:hidden" aria-label="Mobile">
        @foreach($links as $r => $l)
            <a href="{{ route($r) }}" class="block rounded-lg px-2 py-2 text-sm font-medium text-ink hover:bg-slate-soft">{{ $l }}</a>
        @endforeach
        @auth
            <a href="{{ route('dashboard') }}" class="block rounded-lg px-2 py-2 text-sm font-semibold text-kuet">Dashboard</a>
        @else
            <a href="{{ route('login') }}" class="block rounded-lg px-2 py-2 text-sm font-semibold text-kuet">Sign in</a>
            <a href="{{ route('register') }}" class="block rounded-lg px-2 py-2 text-sm font-semibold text-kuet">Register</a>
        @endauth
    </nav>
</header>
