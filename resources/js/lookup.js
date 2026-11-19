// Issuer form (1.2): student lookup fills name + department from the student record. The issuer still confirms every value.
export const studentLookup = (cfg) => ({
    msg: '', bad: false,
    async check(ref) {
        ref = ref.trim();
        if (!ref) { this.msg = ''; return; }
        try {
            const r = await fetch(cfg.url.replace('__REF__', encodeURIComponent(ref)), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!r.ok) { this.bad = true; this.msg = 'No active student with this reference.'; return; }
            const d = await r.json();
            this.bad = !d.wallet_ready;
            this.msg = (d.name || 'Unnamed') + (d.department ? ' · ' + d.department : '') + (d.wallet_ready ? '' : ' · wallet not set up yet');
            if (d.programme_code) document.getElementById('programme_code').value = d.programme_code;
        } catch { this.msg = ''; }
    },
});
