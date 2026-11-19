import Alpine from 'alpinejs';
import wallet from './wallet';
import { qrtools, qrscan } from './qr';
import { batchProgress } from './bulk';
import { studentLookup } from './lookup';

window.Alpine = Alpine;
Alpine.data('wallet', wallet);
Alpine.data('qrtools', qrtools);
Alpine.data('qrscan', qrscan);
Alpine.data('batchProgress', batchProgress);
Alpine.data('studentLookup', studentLookup);
Alpine.start();
