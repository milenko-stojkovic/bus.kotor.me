import jsQR from 'jsqr';

window.jsQR = jsQR;
window.dispatchEvent(new CustomEvent('limoJsQrReady'));
