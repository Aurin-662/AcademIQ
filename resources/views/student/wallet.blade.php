<x-layouts.app title="Credential wallet">
<div x-data="wallet({ keyUrl: '{{ route('student.wallet.key.set') }}', credentialsUrl: '{{ route('student.wallet.credentials') }}', deliveriesUrl: '{{ route('student.wallet.deliveries') }}', ackUrl: '{{ route('student.wallet.ack', '__ID__') }}', hasServerKey: {{ $ref->wallet_public_key ? 'true' : 'false' }} })" x-init="init()">
    <p x-show="err" x-cloak class="mb-4 rounded-lg border border-bad/30 bg-bad/10 p-3 text-sm text-bad" role="alert" x-text="err"></p>
    <p x-show="msg" x-cloak class="mb-4 rounded-lg border border-ok/30 bg-ok/10 p-3 text-sm text-ok" role="status" x-text="msg"></p>

    <div x-show="state === 'loading'" class="card text-sm text-muted">Opening wallet…</div>

    <form x-show="state === 'setup'" x-cloak @submit.prevent="setup()" class="card max-w-lg space-y-4">
        <h2 class="text-lg font-bold">Set up your wallet</h2>
        <p class="text-sm text-muted">Your credentials are stored encrypted in this browser. The passphrase never leaves your device and KUET cannot recover it. If you lose both the passphrase and a backup, KUET must re-issue your credentials. Use the same browser each time, and export a backup.</p>
        @if($ref->wallet_public_key)<p class="rounded-lg bg-warn/15 p-3 text-sm text-warn">A wallet key is already registered for your account, but this browser has no wallet. Restore a backup below, or set up a new wallet (this discards undelivered credentials).</p>@endif
        <div><label class="label" for="p1">Passphrase (12+ characters)</label><input class="input" id="p1" type="password" x-model="pass" autocomplete="new-password" required></div>
        <div><label class="label" for="p2">Confirm passphrase</label><input class="input" id="p2" type="password" x-model="pass2" autocomplete="new-password" required></div>
        <button class="btn-primary" :disabled="busy" type="submit"><span x-text="busy ? 'Setting up…' : 'Create wallet'"></span></button>
    </form>

    <form x-show="state === 'locked'" x-cloak @submit.prevent="unlock()" class="card max-w-lg space-y-4">
        <h2 class="text-lg font-bold">Unlock wallet</h2>
        <div><label class="label" for="pu">Passphrase</label><input class="input" id="pu" type="password" x-model="pass" autocomplete="current-password" required></div>
        <button class="btn-primary" :disabled="busy" type="submit"><span x-text="busy ? 'Unlocking…' : 'Unlock'"></span></button>
    </form>

    <div x-show="state === 'unlocked'" x-cloak>
        <div class="mb-4 flex flex-wrap gap-2">
            <button class="btn-ghost" @click="refresh()">Check for new credentials</button>
            <button class="btn-ghost" @click="exportBackup()">Export encrypted backup</button>
            <button class="btn-ghost" @click="lock()">Lock</button>
        </div>
        <div class="space-y-3">
            <template x-for="r in rows" :key="r.credential_id">
                <div class="card flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <a class="font-mono text-sm font-semibold underline" :href="'{{ url('/student/credentials') }}/' + r.credential_id" x-text="r.credential_id"></a>
                        <p class="text-xs text-muted">Issued <span x-text="r.issue_date"></span> · v<span x-text="r.version"></span> · <span x-text="r.issuer"></span></p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="badge" :class="r.status === 'active' ? 'bg-ok/10 text-ok' : 'bg-bad/10 text-bad'" x-text="r.status"></span>
                        <span class="badge bg-slate-soft text-muted" x-show="!r.pkg">Package missing</span>
                        <span class="badge bg-warn/15 text-warn" x-show="r.pkg && !r.inSync">Older version held</span>
                        <button class="btn-ghost !py-1" x-show="r.pkg" @click="exportBackup(r.credential_id)">Download package</button>
                    </div>
                </div>
            </template>
            <p x-show="rows.length === 0" class="card text-sm text-muted">No credentials yet. When KUET issues one it will appear after you unlock the wallet.</p>
        </div>
    </div>

    <div class="card mt-8 max-w-lg">
        <h2 class="font-bold">Restore from backup</h2>
        <p class="mt-1 text-sm text-muted">Select an <code>.aqbackup</code> file exported from your wallet. You will need its passphrase.</p>
        <input class="mt-3 text-sm" type="file" accept=".aqbackup,application/json" @change="restore($event)" aria-label="Backup file">
    </div>
</div>
</x-layouts.app>
