// QR helpers. The QR encodes ONLY the verification URL (which contains the token id); never a proof or any credential data.
// Libraries load on demand so pages without QR stay small.

const download = (href, name) => Object.assign(document.createElement('a'), { href, download: name }).click();

// Holder side: show the QR, download it as PNG, or download a CV-ready badge (QR + token + claims summary).
export const qrtools = (cfg) => ({
    qr: '',
    async init() {
        const QR = await import('qrcode');
        this.qr = await QR.toDataURL(cfg.link, { width: 512, margin: 2, errorCorrectionLevel: 'M' });
    },
    downloadQr() { download(this.qr, `${cfg.token}-qr.png`); },
    async downloadBadge() {
        const W = 900, H = 520, c = Object.assign(document.createElement('canvas'), { width: W, height: H }), g = c.getContext('2d');
        g.fillStyle = '#ffffff'; g.fillRect(0, 0, W, H);
        g.fillStyle = '#10243A'; g.fillRect(0, 0, W, 86);
        g.fillStyle = '#ffffff'; g.font = '700 34px sans-serif'; g.fillText('AcademIQ', 36, 54);
        g.fillStyle = '#18A999'; g.font = '600 18px sans-serif'; g.fillText('Zero-knowledge verified academic credential', 190, 52);
        const img = await new Promise((res) => { const i = new Image(); i.onload = () => res(i); i.src = this.qr; });
        g.drawImage(img, 36, 120, 300, 300);
        g.fillStyle = '#172B3A'; g.font = '700 30px monospace'; g.fillText(cfg.token, 370, 160);
        g.font = '600 20px sans-serif'; g.fillText('Claims proven', 370, 210);
        g.font = '18px sans-serif'; g.fillStyle = '#334155';
        cfg.claims.slice(0, 6).forEach((l, i) => g.fillText('• ' + l, 370, 244 + i * 30));
        g.fillStyle = '#64748B'; g.font = '16px sans-serif';
        g.fillText('Valid until ' + cfg.expires, 370, 450);
        g.fillText('Scan the QR or open ' + cfg.link.replace(/^https?:\/\//, ''), 36, 470);
        g.fillText('Verifiers sign in, then see PASS / FAIL only. Hidden values stay private.', 36, 495);
        download(c.toDataURL('image/png'), `${cfg.token}-badge.png`);
    },
});

// Verifier side: camera scan or uploaded image -> token id -> opens the criteria page. Decoding is local (jsQR).
export const qrscan = (cfg) => ({
    on: false, err: '', stream: null, timer: null, found: [], // found: collect mode (bulk): tokens scanned this session, deduplicated
    async decode(src, w, h) {
        const canvas = Object.assign(document.createElement('canvas'), { width: w, height: h });
        const g = canvas.getContext('2d', { willReadFrequently: true });
        g.drawImage(src, 0, 0, w, h);
        const { default: jsQR } = await import('jsqr');
        return jsQR(g.getImageData(0, 0, w, h).data, w, h)?.data ?? null;
    },
    open(text) {
        const m = String(text).toUpperCase().match(/ACQ(?:-[0-9A-Z]{4}){4}/);
        if (!m) { this.err = 'That QR code is not an AcademiQ token.'; return false; }
        if (cfg.collect) { if (!this.found.includes(m[0])) this.found.push(m[0]); this.err = ''; return true; } // keep scanning
        this.stop();
        window.location.href = cfg.showUrl.replace('__ID__', m[0]);
        return true;
    },
    async start() {
        this.err = '';
        try {
            this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
            const v = this.$refs.video;
            v.srcObject = this.stream;
            await v.play();
            this.on = true;
            this.timer = setInterval(async () => {
                if (!v.videoWidth) return;
                const text = await this.decode(v, v.videoWidth, v.videoHeight);
                if (text) this.open(text);
            }, 250);
        } catch { this.err = 'Camera unavailable. Allow camera access or upload a QR image.'; this.stop(); }
    },
    stop() {
        clearInterval(this.timer);
        this.stream?.getTracks().forEach((t) => t.stop());
        this.stream = null; this.on = false;
    },
    async scanFile(ev) {
        this.err = '';
        for (const f of ev.target.files) {
            try {
                const bmp = await createImageBitmap(f);
                const text = await this.decode(bmp, bmp.width, bmp.height);
                if (!text) this.err = 'No QR code found in ' + f.name + '.'; else this.open(text);
            } catch { this.err = 'Could not read ' + f.name + '.'; }
        }
        ev.target.value = '';
    },
});
