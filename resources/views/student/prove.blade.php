<x-layouts.app title="Generate proof">
<div x-data="wallet({ keyUrl: '{{ route('student.wallet.key.set') }}', credentialsUrl: '{{ route('student.wallet.credentials') }}', deliveriesUrl: '{{ route('student.wallet.deliveries') }}', ackUrl: '{{ route('student.wallet.ack', '__ID__') }}', hasServerKey: true, instructionUrl: '{{ route('student.proof.instruction', $requestId) }}', submitUrl: '{{ route('student.proof.submit') }}' })" x-init="init()" class="max-w-xl space-y-4">
    <p x-show="err" x-cloak class="rounded-lg border border-bad/30 bg-bad/10 p-3 text-sm text-bad" role="alert" x-text="err"></p>
    <div class="card text-sm">
        <p>Request <span class="font-mono">{{ $requestId }}</span></p>
        <p class="mt-2 text-muted">Your browser will generate a zero-knowledge proof from your private credential. Only the proof and public values are sent to AcademiQ. Your CGPA, programme, salt and issuer signature stay on this device.</p>
    </div>

    <form x-show="state === 'locked'" x-cloak @submit.prevent="unlock()" class="card space-y-3">
        <label class="label" for="pu">Wallet passphrase</label>
        <input class="input" id="pu" type="password" x-model="pass" autocomplete="current-password" required>
        <button class="btn-primary" :disabled="busy" type="submit">Unlock wallet</button>
    </form>
    <p x-show="state === 'setup'" x-cloak class="card text-sm text-muted">No wallet found in this browser. <a class="underline" href="{{ route('student.wallet.index') }}">Open your wallet</a> to set it up or restore a backup.</p>

    <div x-show="state === 'unlocked'" x-cloak class="card space-y-3">
        <button class="btn-primary" :disabled="proving || proofDone" @click="prove()"><span x-text="proving ? 'Generating proof…' : 'Generate and submit proof'"></span></button>
        <p x-show="proofStatus" x-cloak class="text-sm" :class="proofOk ? 'text-ok' : 'text-bad'" role="status" x-text="proofStatus"></p>
    </div>
</div>
</x-layouts.app>
