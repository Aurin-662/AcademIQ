<x-layouts.app title="Verify a token">
<div class="max-w-xl space-y-4">
    <form method="POST" action="{{ route('employer.verify.open') }}" class="card space-y-3">
        @csrf
        <h2 class="text-lg font-bold">Enter token or link</h2>
        <div><label class="label" for="input">Token ID or verification link</label>
            <input class="input font-mono" id="input" name="input" value="{{ old('input') }}" placeholder="ACQ-XXXX-XXXX-XXXX-XXXX" autocomplete="off" required>
            @error('input')<p class="mt-1 text-sm text-bad">{{ $message }}</p>@enderror</div>
        <button class="btn-primary" type="submit">Open</button>
    </form>

    <div class="card space-y-3" x-data="qrscan({ showUrl: '{{ route('employer.verify.show', '__ID__') }}' })" x-init="$watch('on', v => v || stop())" @beforeunload.window="stop()">
        <h2 class="text-lg font-bold">Scan a QR code</h2>
        <p x-show="err" x-cloak class="rounded-lg border border-bad/30 bg-bad/10 p-3 text-sm text-bad" role="alert" x-text="err"></p>
        <video x-ref="video" x-show="on" x-cloak class="w-full rounded-lg bg-navy" playsinline muted aria-label="Camera preview"></video>
        <div class="flex flex-wrap gap-2">
            <button type="button" class="btn-ghost" x-show="!on" @click="start()">Scan with camera</button>
            <button type="button" class="btn-ghost" x-show="on" x-cloak @click="stop()">Stop camera</button>
            <label class="btn-ghost cursor-pointer">Upload QR image<input class="sr-only" type="file" accept="image/*" @change="scanFile($event)"></label>
        </div>
        <p class="text-sm text-muted">Scanning happens in your browser. Only the token ID from the QR opens the check.</p>
    </div>
</div>
</x-layouts.app>
