<x-layouts.public title="Sign in">
<section class="mx-auto max-w-md px-4 py-14 sm:px-6">
    <h1 class="mb-6 text-2xl font-bold">Sign in</h1>
    @if(session('status'))<div role="status" class="mb-4 rounded-lg border border-ok/30 bg-ok/10 p-3 text-sm text-ok">{{ session('status') }}</div>@endif
    <form method="POST" action="{{ route('login') }}" class="card space-y-4">
        @csrf
        <x-field name="email" label="Email" type="email" autocomplete="username" />
        <x-field name="password" label="Password" type="password" autocomplete="current-password" />
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remember" value="1"> Remember me</label>
        <button class="btn-primary w-full" type="submit">Sign in</button>
        <p class="text-sm text-muted"><a class="underline" href="{{ route('password.request') }}">Forgot password?</a> · <a class="underline" href="{{ route('register') }}">Create account</a></p>
    </form>
</section>
</x-layouts.public>
