<x-layouts.app title="Generate verification token">
<div x-data="wallet({ keyUrl: '{{ route('student.wallet.key.set') }}', credentialsUrl: '{{ route('student.wallet.credentials') }}', deliveriesUrl: '{{ route('student.wallet.deliveries') }}', ackUrl: '{{ route('student.wallet.ack', '__ID__') }}', hasServerKey: {{ $ref->wallet_public_key ? 'true' : 'false' }}, prepareUrl: '{{ route('student.tokens.prepare') }}', submitUrl: '{{ route('student.tokens.submit') }}', showUrl: '{{ route('student.tokens.show', '__ID__') }}' })" x-init="init()" class="max-w-2xl space-y-4">
    <p x-show="err" x-cloak class="rounded-lg border border-bad/30 bg-bad/10 p-3 text-sm text-bad" role="alert" x-text="err"></p>

    <div class="card text-sm text-muted">Choose what to prove. Your browser builds the zero-knowledge proof locally; only the proof and the public results leave this device. Exact values stay hidden unless you choose to reveal them.</div>

    <form x-show="state === 'locked'" x-cloak @submit.prevent="unlock()" class="card space-y-3">
        <label class="label" for="pu">Wallet passphrase</label>
        <input class="input" id="pu" type="password" x-model="pass" autocomplete="current-password" required>
        <button class="btn-primary" :disabled="busy" type="submit">Unlock wallet</button>
    </form>
    <p x-show="state === 'setup'" x-cloak class="card text-sm text-muted">No wallet found in this browser. <a class="underline" href="{{ route('student.wallet.index') }}">Open your wallet</a> to set it up or restore a backup.</p>

    <form x-show="state === 'unlocked'" x-cloak @submit.prevent="proveClaims(sel())" class="card space-y-5"
        x-data="{ cgpa: false, cgpaMode: 'threshold', year: false, lo: {{ $maxYear }}, hi: {{ $maxYear }}, inst: false, instCode: {{ array_key_first(config('academiq.institutions')) }}, deg: false, degCode: {{ array_key_first(config('academiq.degrees')) }}, dept: false, deptCode: {{ array_key_first(config('academiq.programmes')) }}, days: 30,
            sel() { const s = { expiry_days: Number(this.days) };
                if (this.cgpa) s.cgpa = { mode: this.cgpaMode };
                if (this.year) s.year = { lo: Number(this.lo), hi: Number(this.hi) };
                if (this.inst) s.institution = { code: Number(this.instCode) };
                if (this.deg) s.degree = { code: Number(this.degCode) };
                if (this.dept) s.department = { code: Number(this.deptCode) };
                return s; },
            get any() { return this.cgpa || this.year || this.inst || this.deg || this.dept; } }">
        <fieldset class="space-y-4">
            <legend class="text-lg font-bold">Claims to include</legend>

            <div class="space-y-2">
                <label class="flex items-center gap-2 font-medium"><input type="checkbox" x-model="cgpa"> CGPA</label>
                <div x-show="cgpa" x-cloak class="ml-6 space-y-1 text-sm">
                    <label class="flex items-center gap-2"><input type="radio" value="threshold" x-model="cgpaMode"> Threshold proof: verifiers see only whether you meet 2.0, 2.5, 3.0, 3.25, 3.5, 3.75, 4.0</label>
                    <label class="flex items-center gap-2"><input type="radio" value="reveal" x-model="cgpaMode"> Reveal exact value (also includes the thresholds)</label>
                </div>
            </div>

            <div class="space-y-2">
                <label class="flex items-center gap-2 font-medium"><input type="checkbox" x-model="year"> Passing year</label>
                <div x-show="year" x-cloak class="ml-6 flex flex-wrap items-end gap-3 text-sm">
                    <div><label class="label" for="lo">Between</label><input class="input w-28" id="lo" type="number" min="{{ $minYear }}" max="{{ $maxYear }}" x-model="lo"></div>
                    <div><label class="label" for="hi">and</label><input class="input w-28" id="hi" type="number" min="{{ $minYear }}" max="{{ $maxYear }}" x-model="hi"></div>
                    <p class="text-muted">Range proof. Same value in both boxes proves the exact year.</p>
                </div>
            </div>

            <div class="space-y-2">
                <label class="flex items-center gap-2 font-medium"><input type="checkbox" x-model="inst"> Institution</label>
                <select x-show="inst" x-cloak class="input ml-6 max-w-md" x-model="instCode" aria-label="Institution">@foreach(config('academiq.institutions') as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
            </div>
            <div class="space-y-2">
                <label class="flex items-center gap-2 font-medium"><input type="checkbox" x-model="deg"> Degree</label>
                <select x-show="deg" x-cloak class="input ml-6 max-w-md" x-model="degCode" aria-label="Degree">@foreach(config('academiq.degrees') as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
            </div>
            <div class="space-y-2">
                <label class="flex items-center gap-2 font-medium"><input type="checkbox" x-model="dept"> Department</label>
                <select x-show="dept" x-cloak class="input ml-6 max-w-md" x-model="deptCode" aria-label="Department">@foreach(config('academiq.programmes') as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
            </div>
        </fieldset>

        <div><label class="label" for="days">Token valid for</label>
            <select class="input max-w-xs" id="days" x-model="days"><option value="7">7 days</option><option value="30">30 days</option><option value="90">90 days</option><option value="180">180 days</option><option value="365">1 year</option></select></div>

        <p class="text-sm text-muted">Creating the token records your consent to share these results with anyone who holds it. You can revoke it at any time.</p>
        <button class="btn-primary" type="submit" :disabled="!any || tokenBusy"><span x-text="tokenBusy ? 'Generating proof…' : 'Generate token'"></span></button>
    </form>
</div>
</x-layouts.app>
