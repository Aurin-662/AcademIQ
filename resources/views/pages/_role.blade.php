{{-- Shared role guide body. Expects: $steps [[title, text]], $sees [string], $never [string], $notes [[title, text]], $cta [label, url]. --}}
<section class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <h2 class="text-2xl">How it works</h2>
            <ol class="space-y-3">
                @foreach($steps as $i => [$title, $text])
                    <li class="card flex gap-4">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-kuet text-sm font-semibold text-white">{{ $i + 1 }}</span>
                        <div>
                            <h3 class="text-base font-semibold">{{ $title }}</h3>
                            <p class="mt-1 text-sm text-muted">{{ $text }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>

        <aside class="space-y-4">
            <div class="card">
                <h3 class="text-base font-semibold text-ok">What you see</h3>
                <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-ink">
                    @foreach($sees as $item)<li>{{ $item }}</li>@endforeach
                </ul>
            </div>
            <div class="card">
                <h3 class="text-base font-semibold text-bad">What you never see</h3>
                <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-ink">
                    @foreach($never as $item)<li>{{ $item }}</li>@endforeach
                </ul>
            </div>
        </aside>
    </div>

    <div class="mt-10 grid gap-6 md:grid-cols-3">
        @foreach($notes as [$title, $text])
            <div class="card">
                <h3 class="text-base font-semibold">{{ $title }}</h3>
                <p class="mt-2 text-sm text-muted">{{ $text }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-10">
        <a href="{{ $cta[1] }}" class="btn-primary">{{ $cta[0] }}</a>
    </div>
</section>