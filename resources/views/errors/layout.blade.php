<x-layouts.public :title="$code.' '.$heading">
<section class="mx-auto max-w-lg px-4 py-24 text-center">
    <p class="font-display text-6xl font-extrabold text-teal">{{ $code }}</p>
    <h1 class="mt-4 text-2xl font-bold">{{ $heading }}</h1>
    <p class="mt-3 text-muted">{{ $message }}</p>
    <div class="mt-8 flex justify-center gap-3">
        <a href="{{ url('/') }}" class="btn-primary">Back to home</a>
        @auth<a href="{{ route('dashboard') }}" class="btn-ghost">My dashboard</a>@endauth
    </div>
</section>
</x-layouts.public>
