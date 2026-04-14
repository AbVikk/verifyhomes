const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

const postJson = async (url, payload) => {
    const response = await window.fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify(payload),
        credentials: 'same-origin',
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw data;
    }

    return data;
};

export const createTermsGateState = ({
    requiredSeconds = 10,
    warningDurationMs = 3200,
} = {}) => {
    const requiredMs = requiredSeconds * 1000;

    return {
        requiredSeconds,
        warningDurationMs,
        ready: false,
        accepted: false,
        completing: false,
        hasOpened: false,
        modalVisible: false,
        elapsedMs: 0,
        activeStartedAtMs: null,
        warning: null,
        warningExpiresAtMs: null,

        reset() {
            this.ready = false;
            this.accepted = false;
            this.completing = false;
            this.hasOpened = false;
            this.modalVisible = false;
            this.elapsedMs = 0;
            this.activeStartedAtMs = null;
            this.clearWarning();
        },

        setReady(value) {
            this.ready = value;

            if (value) {
                this.hasOpened = true;
                this.modalVisible = false;
                this.activeStartedAtMs = null;
                this.elapsedMs = requiredMs;
                this.clearWarning();
            }
        },

        setAccepted(value) {
            this.accepted = value;

            if (value) {
                this.clearWarning();
            }
        },

        setCompleting(value) {
            this.completing = value;
        },

        setHasOpened(value) {
            this.hasOpened = value;
        },

        setModalVisible(visible, nowMs) {
            if (this.ready) {
                this.modalVisible = false;
                this.activeStartedAtMs = null;

                return;
            }

            if (visible) {
                if (!this.modalVisible) {
                    this.modalVisible = true;
                    this.activeStartedAtMs = nowMs;
                    this.hasOpened = true;
                }

                return;
            }

            if (!this.modalVisible) {
                return;
            }

            this.captureElapsed(nowMs);
            this.modalVisible = false;
            this.activeStartedAtMs = null;
        },

        captureElapsed(nowMs) {
            if (!this.modalVisible || this.activeStartedAtMs === null) {
                return;
            }

            this.elapsedMs = Math.min(requiredMs, this.elapsedMs + Math.max(0, nowMs - this.activeStartedAtMs));
            this.activeStartedAtMs = nowMs;
        },

        isUnlocked(nowMs) {
            if (this.ready) {
                return true;
            }

            if (this.modalVisible && this.activeStartedAtMs !== null) {
                return this.elapsedMs + Math.max(0, nowMs - this.activeStartedAtMs) >= requiredMs;
            }

            return this.elapsedMs >= requiredMs;
        },

        tick(nowMs) {
            if (!this.modalVisible) {
                this.dismissWarningIfNeeded(nowMs);

                return false;
            }

            this.captureElapsed(nowMs);

            this.dismissWarningIfNeeded(nowMs);

            return this.isUnlocked(nowMs);
        },

        secondsRemaining(nowMs) {
            const elapsed = this.modalVisible && this.activeStartedAtMs !== null
                ? Math.min(requiredMs, this.elapsedMs + Math.max(0, nowMs - this.activeStartedAtMs))
                : this.elapsedMs;

            return Math.max(0, Math.ceil((requiredMs - elapsed) / 1000));
        },

        showWarning(message, nowMs) {
            this.warning = message;
            this.warningExpiresAtMs = nowMs + this.warningDurationMs;
        },

        dismissWarningIfNeeded(nowMs) {
            if (this.warning && this.warningExpiresAtMs !== null && nowMs >= this.warningExpiresAtMs) {
                this.clearWarning();
            }
        },

        clearWarning() {
            this.warning = null;
            this.warningExpiresAtMs = null;
        },
    };
};

const nowMs = () => Date.now();

const getRegistry = () => {
    window.VerifyHomesTermsGateRegistry ??= new Map();

    return window.VerifyHomesTermsGateRegistry;
};

const modalIsVisible = (modalName) => {
    if (!modalName) {
        return false;
    }

    const modal = document.querySelector(`[data-modal-name="${modalName}"]`);

    if (!modal) {
        return false;
    }

    return window.getComputedStyle(modal).display !== 'none';
};

const dispatchSyncEvents = (element) => {
    element.dispatchEvent(new Event('input', { bubbles: true }));
    element.dispatchEvent(new Event('change', { bubbles: true }));
};

const initializeTermsGates = () => {
    const registry = getRegistry();
    const roots = document.querySelectorAll('[data-terms-gate-root]');

    roots.forEach((root) => {
        if (root.dataset.termsGateInitialized === 'true') {
            return;
        }

        root.dataset.termsGateInitialized = 'true';

        const gate = root.dataset.termsGate;
        const modalName = root.dataset.termsGateModal;
        const openButton = root.querySelector('[data-terms-gate-open]');
        const summary = root.querySelector('[data-terms-gate-summary]');
        const hiddenInputs = [...root.querySelectorAll('[data-terms-gate-hidden-input]')];
        const submitButtons = [...root.querySelectorAll('[data-terms-gate-submit-button]')];
        const openUrl = root.dataset.termsGateOpenUrl;
        const completeUrl = root.dataset.termsGateCompleteUrl;
        const requiredSeconds = Number.parseInt(root.dataset.termsGateSeconds || '10', 10);
        const secondsRemaining = Number.parseInt(root.dataset.termsGateSecondsRemaining || `${requiredSeconds}`, 10);
        const isAccepted = root.dataset.termsGateAccepted === 'true'
            || hiddenInputs.some((input) => input.checked);

        if (!gate) {
            return;
        }

        if (!registry.has(gate)) {
            const state = createTermsGateState({ requiredSeconds });

            state.setReady(root.dataset.termsGateReady === 'true');
            state.setAccepted(isAccepted);

            if (!state.ready) {
                state.elapsedMs = Math.max(0, (requiredSeconds - secondsRemaining) * 1000);
                state.setHasOpened(secondsRemaining < requiredSeconds);
            }

            registry.set(gate, {
                state,
                intervalId: null,
            });
        }

        const entry = registry.get(gate);
        const { state } = entry;
        const modalRoot = document.querySelector(`[data-terms-gate-modal-content="${gate}"]`);
        const modalCheckbox = modalRoot?.querySelector('[data-terms-gate-checkbox]') ?? null;
        const modalStatus = modalRoot?.querySelector('[data-terms-gate-modal-status]') ?? null;
        const modalWarning = modalRoot?.querySelector('[data-terms-gate-modal-warning]') ?? null;

        if (root.dataset.termsGateReady === 'true') {
            state.setReady(true);
        }

        if (isAccepted) {
            state.setAccepted(true);
        }

        const renderSummary = () => {
            if (!summary) {
                return;
            }

            summary.textContent = state.accepted
                ? 'Terms accepted. You can continue with the form.'
                : 'Open the terms to read and accept them in the modal.';
        };

        const renderModalStatus = () => {
            if (!modalStatus) {
                return;
            }

            if (state.accepted) {
                modalStatus.textContent = state.completing
                    ? 'Finalizing your acceptance...'
                    : 'Terms accepted. You can close this modal and continue.';

                return;
            }

            if (state.ready) {
                modalStatus.textContent = 'You can now accept the terms and continue.';

                return;
            }

            const remaining = state.secondsRemaining(nowMs());
            const unit = remaining === 1 ? 'second' : 'seconds';

            modalStatus.textContent = `Keep this modal open for ${remaining} more ${unit} before the checkbox unlocks.`;
        };

        const renderWarning = () => {
            if (!modalWarning) {
                return;
            }

            if (!state.warning) {
                modalWarning.classList.add('opacity-0');

                window.setTimeout(() => {
                    if (!state.warning) {
                        modalWarning.classList.add('hidden');
                        modalWarning.textContent = '';
                    }
                }, 300);

                return;
            }

            modalWarning.textContent = state.warning;
            modalWarning.classList.remove('hidden');
            window.requestAnimationFrame(() => {
                modalWarning.classList.remove('opacity-0');
            });
        };

        const syncHiddenInputs = () => {
            hiddenInputs.forEach((input) => {
                if (input.checked === state.accepted) {
                    return;
                }

                input.checked = state.accepted;
                dispatchSyncEvents(input);
            });
        };

        const syncSubmitButtons = () => {
            submitButtons.forEach((button) => {
                button.disabled = !state.accepted || state.completing;
            });
        };

        const syncModalCheckbox = () => {
            if (!modalCheckbox) {
                return;
            }

            modalCheckbox.checked = state.accepted;
            modalCheckbox.disabled = !state.isUnlocked(nowMs()) || state.accepted || state.completing;
        };

        const render = () => {
            renderSummary();
            renderModalStatus();
            renderWarning();
            syncModalCheckbox();
            syncHiddenInputs();
            syncSubmitButtons();
        };

        const stopTimer = () => {
            if (entry.intervalId) {
                window.clearInterval(entry.intervalId);
                entry.intervalId = null;
            }
        };

        const completeAcceptance = async () => {
            if (state.ready || !completeUrl) {
                state.setCompleting(false);
                state.setAccepted(true);
                render();

                return;
            }

            try {
                await postJson(completeUrl, { gate });
                state.setReady(true);
                state.setCompleting(false);
                state.setAccepted(true);
                root.dataset.termsGateReady = 'true';
                render();
            } catch (error) {
                state.setCompleting(false);
                state.setAccepted(false);
                state.showWarning(error?.message || 'Please read the terms before continuing.', nowMs());
                render();
            }
        };

        const tick = () => {
            const currentNow = nowMs();
            const visible = modalIsVisible(modalName);

            state.setModalVisible(visible, currentNow);
            state.tick(currentNow);
            render();
        };

        const startTimer = () => {
            if (entry.intervalId || state.accepted || state.completing) {
                return;
            }

            entry.intervalId = window.setInterval(() => {
                tick();
            }, 250);
        };

        const showEarlyWarning = () => {
            state.showWarning('Please read the terms before continuing.', nowMs());
            renderWarning();
        };

        const openModal = () => {
            window.dispatchEvent(new CustomEvent('open-modal', { detail: modalName }));
            startTimer();
            render();
        };

        const attemptOpen = async () => {
            if (state.accepted || state.hasOpened || !openUrl) {
                openModal();

                return;
            }

            try {
                await postJson(openUrl, { gate });
                state.setHasOpened(true);
                openModal();
            } catch {
                state.showWarning('We could not open the terms right now. Please try again.', nowMs());
                renderWarning();
            }
        };

        render();

        openButton?.addEventListener('click', () => {
            attemptOpen().catch(() => {
                state.showWarning('We could not open the terms right now. Please try again.', nowMs());
                renderWarning();
            });
        });

        modalCheckbox?.addEventListener('click', (event) => {
            if (state.isUnlocked(nowMs())) {
                return;
            }

            event.preventDefault();
            showEarlyWarning();
        });

        modalCheckbox?.closest('label')?.addEventListener('click', (event) => {
            if (state.isUnlocked(nowMs())) {
                return;
            }

            event.preventDefault();
            showEarlyWarning();
        });

        modalCheckbox?.addEventListener('change', async () => {
            if (!state.isUnlocked(nowMs()) || !modalCheckbox.checked) {
                modalCheckbox.checked = state.accepted;

                return;
            }

            state.setAccepted(true);
            state.setCompleting(true);
            render();
            await completeAcceptance();
        });

        root.closest('form')?.addEventListener('submit', (event) => {
            if (state.accepted && !state.completing) {
                return;
            }

            event.preventDefault();

            if (state.completing) {
                return;
            }

            attemptOpen().then(() => {
                showEarlyWarning();
            }).catch(() => {
                state.showWarning('We could not open the terms right now. Please try again.', nowMs());
                renderWarning();
            });
        });

        window.addEventListener('modal-visibility-changed', (event) => {
            const detail = event.detail || {};

            if (detail.name !== modalName) {
                return;
            }

            state.setModalVisible(Boolean(detail.visible), nowMs());

            if (detail.visible) {
                startTimer();
            } else {
                stopTimer();
            }

            render();
        });
    });
};

if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', initializeTermsGates);
    document.addEventListener('livewire:navigated', initializeTermsGates);
}
