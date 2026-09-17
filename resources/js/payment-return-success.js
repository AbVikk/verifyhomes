const root = document.querySelector('[data-payment-return-success]');

if (root) {
    const returnUrl = root.dataset.returnUrl || '';
    const payload = {
        type: 'verifyhomes:payment-complete',
        returnUrl,
    };

    const isSafeReturnUrl = () => {
        try {
            return new URL(returnUrl, window.location.origin).origin === window.location.origin;
        } catch {
            return false;
        }
    };

    if (isSafeReturnUrl()) {
        if ('BroadcastChannel' in window) {
            const channel = new BroadcastChannel('verifyhomes-payment-complete');
            channel.postMessage(payload);
            channel.close();
        }

        if (window.opener && !window.opener.closed) {
            window.opener.postMessage(payload, window.location.origin);
        }

        // A script-opened checkout closes here; same-tab and blocked-close cases use this safe redirect.
        window.setTimeout(() => window.location.replace(returnUrl), window.opener ? 1200 : 300);
        window.setTimeout(() => window.close(), 100);
    }
}
