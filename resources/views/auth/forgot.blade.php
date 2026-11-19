<x-layouts.public title="Reset password">
<section class="mx-auto max-w-md px-4 py-14 sm:px-6">
    <h1 class="mb-6 text-2xl font-bold">Reset password</h1>
    @if(session('status'))<div role="status" class="mb-4 rounded-lg border border-ok/30 bg-ok/10 p-3 text-sm text-ok">{{ session('status') }}</div>@endif
    <form method="POST" action="{{ route('password.email') }}" class="card space-y-4">
        @csrf
        <x-field name="email" label="Email" type="email" />
        <button class="btn-primary w-full" type="submit">Send reset link</button>
    </form>
</section>
</x-layouts.public>
