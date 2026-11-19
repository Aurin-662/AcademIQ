// Holder-side wallet. All private credential data stays in this browser, encrypted at rest.
// - Delivery key: ECDH P-256 keypair. Public key is registered with the server; private key is wrapped under the passphrase.
// - Packages: AES-GCM under a key derived from the passphrase (PBKDF2-SHA256). Never stored in plaintext, never sent to the server.

const enc = new TextEncoder();
const dec = new TextDecoder();
const b64 = (buf) => btoa(String.fromCharCode(...new Uint8Array(buf)));
const unb64 = (s) => Uint8Array.from(atob(s), (c) => c.charCodeAt(0));
const ITER = 600000;

const idb = () => new Promise((res, rej) => {
    const r = indexedDB.open('academiq-wallet', 1);
    r.onupgradeneeded = () => { r.result.createObjectStore('meta'); r.result.createObjectStore('packages', { keyPath: 'id' }); };
    r.onsuccess = () => res(r.result);
    r.onerror = () => rej(r.error);
});
const tx = async (store, mode, fn) => {
    const db = await idb();
    return new Promise((res, rej) => {
        const t = db.transaction(store, mode);
        const out = fn(t.objectStore(store));
        t.oncomplete = () => res(out?.result);
        t.onerror = () => rej(t.error);
    });
};

async function deriveKey(pass, salt) {
    const base = await crypto.subtle.importKey('raw', enc.encode(pass), 'PBKDF2', false, ['deriveKey']);
    return crypto.subtle.deriveKey({ name: 'PBKDF2', salt, iterations: ITER, hash: 'SHA-256' }, base, { name: 'AES-GCM', length: 256 }, false, ['encrypt', 'decrypt']);
}
async function seal(key, bytes) {
    const iv = crypto.getRandomValues(new Uint8Array(12));
    return { iv: b64(iv), ct: b64(await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, key, bytes)) };
}
const open = (key, o) => crypto.subtle.decrypt({ name: 'AES-GCM', iv: unb64(o.iv) }, key, unb64(o.ct));

async function openEnvelope(priv, env) {
    const epk = await crypto.subtle.importKey('raw', unb64(env.epk), { name: 'ECDH', namedCurve: 'P-256' }, false, []);
    const secret = await crypto.subtle.deriveBits({ name: 'ECDH', public: epk }, priv, 256);
    const hk = await crypto.subtle.importKey('raw', secret, 'HKDF', false, ['deriveKey']);
    const key = await crypto.subtle.deriveKey({ name: 'HKDF', hash: 'SHA-256', salt: new Uint8Array(0), info: enc.encode('academiq-delivery-v1') }, hk, { name: 'AES-GCM', length: 256 }, false, ['decrypt']);
    return JSON.parse(dec.decode(await crypto.subtle.decrypt({ name: 'AES-GCM', iv: unb64(env.iv) }, key, unb64(env.ct))));
}

const csrf = () => document.querySelector('meta[name=csrf-token]')?.content;
const api = async (url, method = 'GET', body) => {
    const r = await fetch(url, { method, headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() }, body: body ? JSON.stringify(body) : undefined, credentials: 'same-origin' });
    if (!r.ok) throw Object.assign(new Error((await r.json().catch(() => ({}))).message || r.statusText), { status: r.status });
    return r.json();
};

export default (cfg) => ({
    cfg,
    state: 'loading', // loading | setup | locked | unlocked
    pass: '', pass2: '', busy: false, msg: '', err: '',
    key: null, priv: null, local: [], server: [],

    async init() {
        const meta = await tx('meta', 'readonly', (s) => s.get('main'));
        this.state = meta ? 'locked' : 'setup';
    },

    async setup() {
        this.err = '';
        if (this.pass.length < 12) return (this.err = 'Passphrase must be at least 12 characters.');
        if (this.pass !== this.pass2) return (this.err = 'Passphrases do not match.');
        this.busy = true;
        try {
            const kp = await crypto.subtle.generateKey({ name: 'ECDH', namedCurve: 'P-256' }, true, ['deriveBits']);
            const salt = crypto.getRandomValues(new Uint8Array(16));
            this.key = await deriveKey(this.pass, salt);
            const wrapped = await seal(this.key, await crypto.subtle.exportKey('pkcs8', kp.privateKey));
            const pub = b64(await crypto.subtle.exportKey('raw', kp.publicKey));
            const replace = this.cfg.hasServerKey;
            if (replace && !confirm('Your account already has a wallet key registered elsewhere. Replacing it discards undelivered credentials and you will need KUET to re-issue them. Continue?')) return;
            await api(this.cfg.keyUrl, 'POST', { public_key_b64: pub, replace });
            await tx('meta', 'readwrite', (s) => s.put({ salt: b64(salt), wrapped, pub }, 'main'));
            this.priv = kp.privateKey;
            this.pass = this.pass2 = '';
            this.state = 'unlocked';
            await this.refresh();
        } catch (e) { this.err = e.message; } finally { this.busy = false; }
    },

    async unlock() {
        this.err = '';
        this.busy = true;
        try {
            const meta = await tx('meta', 'readonly', (s) => s.get('main'));
            this.key = await deriveKey(this.pass, unb64(meta.salt));
            const pkcs8 = await open(this.key, meta.wrapped).catch(() => { throw new Error('Wrong passphrase.'); });
            this.priv = await crypto.subtle.importKey('pkcs8', pkcs8, { name: 'ECDH', namedCurve: 'P-256' }, false, ['deriveBits']);
            this.pass = '';
            this.state = 'unlocked';
            await this.refresh();
        } catch (e) { this.err = e.message; } finally { this.busy = false; }
    },

    lock() { this.key = this.priv = null; this.local = []; this.state = 'locked'; },

    async refresh() {
        this.err = '';
        try {
            this.server = await api(this.cfg.credentialsUrl);
            const pending = await api(this.cfg.deliveriesUrl);
            for (const d of pending) {
                const pkg = await openEnvelope(this.priv, d.envelope);
                await tx('packages', 'readwrite', async (s) => s.put({ id: `${pkg.credential_id}:${pkg.credential_version}`, credential_id: pkg.credential_id, version: pkg.credential_version, ...(await seal(this.key, enc.encode(JSON.stringify(pkg)))) }));
                // Already acknowledged by a concurrent refresh? Then the package is stored; ignore the 404.
                await api(this.cfg.ackUrl.replace('__ID__', d.id), 'POST').catch((e) => { if (e.status !== 404) throw e; });
                this.msg = `Received ${pkg.credential_id} v${pkg.credential_version}.`;
            }
            const rows = await tx('packages', 'readonly', (s) => s.getAll());
            const pk = [];
            for (const r of rows) pk.push(JSON.parse(dec.decode(await open(this.key, r))));
            this.local = pk;
        } catch (e) { this.err = e.message; }
    },

    // Latest local package + server status per credential.
    get rows() {
        return this.server.map((s) => {
            const pkg = this.local.filter((p) => p.credential_id === s.credential_id).sort((a, b) => b.credential_version - a.credential_version)[0];
            return { ...s, pkg, inSync: pkg && pkg.credential_version === s.version && pkg.commitment === s.commitment };
        });
    },

    proving: false, proofDone: false, proofStatus: '', proofOk: false,

    // Builds the witness from the private package and proves in-browser. Only {proof, publicSignals} are sent.
    async prove() {
        this.err = this.proofStatus = '';
        this.proving = true;
        try {
            await this.refresh();
            const instr = await api(this.cfg.instructionUrl);
            const row = this.rows.find((r) => r.status === 'active' && r.inSync);
            if (!row) throw new Error('No current, active credential package in this wallet. Check the wallet page.');
            const p = row.pkg;
            const [credential_id, student_reference, cgpa_x100, programme_code, degree_category, issue_date, credential_version, salt] = p.fields;
            const input = {
                credential_id, student_reference, cgpa_x100, programme_code, degree_category, issue_date, credential_version, salt,
                R8x: p.signature.R8[0], R8y: p.signature.R8[1], S: p.signature.S,
                commitment: p.commitment, Ax: p.issuer.pub[0], Ay: p.issuer.pub[1],
                request_id: instr.request_id_field, claim_type: String(instr.claim_type), param: String(instr.param),
            };
            const snarkjs = await import('snarkjs');
            let out;
            try {
                out = await snarkjs.groth16.fullProve(input, instr.wasm_url, instr.zkey_url);
            } catch (e) {
                throw new Error('Cannot prove this claim: your credential does not satisfy it.');
            }
            const res = await fetch(this.cfg.submitUrl, {
                method: 'POST', credentials: 'same-origin',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify({ request_id: instr.request_id, credential_id: p.credential_id, circuit_version: instr.circuit_version, proof: out.proof, public_signals: out.publicSignals }),
            });
            const body = await res.json().catch(() => ({}));
            this.proofOk = body.result === 'TRUE';
            this.proofDone = ['TRUE', 'FALSE'].includes(body.result);
            this.proofStatus = `Result: ${body.result || 'ERROR'}. ${body.message || ''}`;
        } catch (e) { this.err = e.message; } finally { this.proving = false; }
    },

    // Claim-set token (Phase 2). sel = {cgpa?:{mode}, year?:{lo,hi}, institution?:{code}, degree?:{code}, department?:{code}, expiry_days}.
    // Private inputs stay in this browser; only {proof, publicSignals} are sent.
    tokenBusy: false,
    async proveClaims(sel) {
        this.err = '';
        this.tokenBusy = true;
        try {
            await this.refresh();
            const row = this.rows.find((r) => r.status === 'active' && r.inSync && r.pkg?.commitment_v2);
            if (!row) throw new Error('No current credential with claim support in this wallet. Ask KUET to re-issue your credential.');
            const p = row.pkg;
            const instr = await api(this.cfg.prepareUrl, 'POST', { ...sel, credential_id: p.credential_id });
            const [credential_id, student_reference, cgpa_x100, programme_code, degree_category, issue_date, credential_version, salt] = p.fields;
            const names = ['bind', 'sel_cgpa', 'sel_year', 'sel_inst', 'sel_deg', 'sel_prog', 'rev_cgpa', 'year_lo', 'year_hi', 'inst_expected', 'deg_expected', 'prog_expected'];
            const input = {
                credential_id, student_reference, cgpa_x100, programme_code, degree_category, issue_date, credential_version, salt,
                institution_code: String(p.profile.institution_code), passing_year: String(p.profile.passing_year),
                R8x: p.signature_v2.R8[0], R8y: p.signature_v2.R8[1], S: p.signature_v2.S,
                commitment_v2: p.commitment_v2, Ax: p.issuer.pub[0], Ay: p.issuer.pub[1],
                ...Object.fromEntries(names.map((n, i) => [n, instr.public_inputs[i]])),
            };
            const snarkjs = await import('snarkjs');
            const out = await snarkjs.groth16.fullProve(input, instr.wasm_url, instr.zkey_url).catch(() => { throw new Error('Could not generate the proof from your credential.'); });
            await api(this.cfg.submitUrl, 'POST', { token_id: instr.token_id, credential_id: p.credential_id, proof: out.proof, public_signals: out.publicSignals });
            window.location.href = this.cfg.showUrl.replace('__ID__', instr.token_id);
        } catch (e) { this.err = e.message; this.tokenBusy = false; }
    },

    async exportBackup(credentialId) {
        const meta = await tx('meta', 'readonly', (s) => s.get('main'));
        let pk = await tx('packages', 'readonly', (s) => s.getAll());
        if (credentialId) pk = pk.filter((p) => p.credential_id === credentialId);
        const blob = new Blob([JSON.stringify({ format: 'academiq-wallet-backup-v1', meta, packages: pk })], { type: 'application/json' });
        const a = Object.assign(document.createElement('a'), { href: URL.createObjectURL(blob), download: `academiq-${credentialId || 'wallet'}.aqbackup` });
        a.click();
        URL.revokeObjectURL(a.href);
    },

    async restore(ev) {
        this.err = '';
        try {
            const f = JSON.parse(await ev.target.files[0].text());
            if (f.format !== 'academiq-wallet-backup-v1') throw new Error('Not an AcademiQ backup.');
            const existing = await tx('meta', 'readonly', (s) => s.get('main'));
            if (existing && existing.pub !== f.meta.pub && !confirm('This backup belongs to a different wallet key. Replace the current wallet?')) return;
            await tx('meta', 'readwrite', (s) => s.put(f.meta, 'main'));
            for (const p of f.packages) await tx('packages', 'readwrite', (s) => s.put(p));
            this.lock();
            this.msg = 'Backup restored. Unlock with the passphrase it was created with.';
        } catch (e) { this.err = e.message; }
    },
});
