// Bulk verification progress. With a queue worker the page only polls; with the sync queue driver it drives the batch
// forward in small server-side chunks, so no single request runs long. Reloads into the results table when done.
const csrf = () => document.querySelector('meta[name=csrf-token]')?.content;
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

export const batchProgress = (cfg) => ({
    p: cfg.p, err: '',
    get pct() { return this.p.total ? Math.round((this.p.processed / this.p.total) * 100) : 100; },
    async init() {
        while (this.p.status !== 'done') {
            try {
                const r = await fetch(cfg.sync ? cfg.stepUrl : cfg.progressUrl, { method: cfg.sync ? 'POST' : 'GET', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() }, credentials: 'same-origin' });
                if (!r.ok) throw new Error(r.status === 429 ? 'Slowing down (rate limit)…' : 'Server error ' + r.status);
                this.p = await r.json();
                this.err = '';
                if (!cfg.sync) await sleep(1500);
            } catch (e) { this.err = e.message; await sleep(3000); }
        }
        window.location.reload();
    },
});
