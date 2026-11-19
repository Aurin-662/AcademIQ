<x-layouts.public title="Contact">
<x-page-header title="Contact" lead="Questions about AcademiQ? Send us a message." />
<section class="mx-auto max-w-2xl px-4 py-14 sm:px-6">
    @if(session('status'))<div role="status" class="mb-6 rounded-lg border border-ok/30 bg-ok/10 p-4 text-sm text-ok">{{ session('status') }}</div>@endif
    <form method="POST" action="{{ route('contact.send') }}" class="card space-y-4" novalidate>
        @csrf
        @foreach(['name' => 'Name', 'email' => 'Email', 'subject' => 'Subject'] as $f => $l)
        <div>
            <label class="label" for="{{ $f }}">{{ $l }}</label>
            <input class="input" id="{{ $f }}" name="{{ $f }}" type="{{ $f === 'email' ? 'email' : 'text' }}" value="{{ old($f) }}" required aria-invalid="{{ $errors->has($f) ? 'true' : 'false' }}">
            @error($f)<p class="mt-1 text-sm text-bad">{{ $message }}</p>@enderror
        </div>
        @endforeach
        <div>
            <label class="label" for="message">Message</label>
            <textarea class="input" id="message" name="message" rows="5" required>{{ old('message') }}</textarea>
            @error('message')<p class="mt-1 text-sm text-bad">{{ $message }}</p>@enderror
        </div>
        <button class="btn-primary" type="submit">Send message</button>
    </form>
</section>
</x-layouts.public>
