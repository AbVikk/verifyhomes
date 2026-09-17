const paymentCompleteEvent = 'verifyhomes:payment-complete';

const isSafeReturnUrl = (returnUrl) => {
    try {
        return new URL(returnUrl, window.location.origin).origin === window.location.origin;
    } catch {
        return false;
    }
};

const handlePaymentComplete = (payload) => {
    if (!payload || payload.type !== paymentCompleteEvent || !isSafeReturnUrl(payload.returnUrl)) {
        return;
    }

    window.location.assign(payload.returnUrl);
};

window.addEventListener('message', (event) => {
    if (event.origin !== window.location.origin) {
        return;
    }

    handlePaymentComplete(event.data);
});

if ('BroadcastChannel' in window) {
    const channel = new BroadcastChannel('verifyhomes-payment-complete');

    channel.addEventListener('message', (event) => handlePaymentComplete(event.data));
    window.addEventListener('beforeunload', () => channel.close(), { once: true });
}
